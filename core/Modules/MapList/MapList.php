<?php

namespace EvoSC\Modules\MapList;

use EvoSC\Classes\Cache;
use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\DB;
use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\MapController;
use EvoSC\Controllers\QueueController;
use EvoSC\Exceptions\UnauthorizedException;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Map;
use EvoSC\Models\MapQueue;
use EvoSC\Models\Player;
use EvoSC\Modules\Dedimania\Dedimania;
use EvoSC\Modules\LocalRecords\LocalRecords;
use Exception;
use Illuminate\Support\Collection;

class MapList extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        ManiaLinkEvent::add('maplist.show_queue', [self::class, 'showQueue']);
        ManiaLinkEvent::add('maplist.disable', [self::class, 'disableMapEvent'], 'map_disable');
        ManiaLinkEvent::add('maplist.delete', [self::class, 'deleteMapPermEvent'], 'map_delete');
        ManiaLinkEvent::add('map.fav.add', [self::class, 'favAdd']);
        ManiaLinkEvent::add('map.fav.remove', [self::class, 'favRemove']);

        Hook::add('MapPoolUpdated', [self::class, 'sendUpdatedMapList']);
        Hook::add('MapQueueUpdated', [self::class, 'mapQueueUpdated']);
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);
        Hook::add('GroupChanged', [self::class, 'playerConnect']);

        ChatCommand::add('/maps', [self::class, 'searchMap'], 'Open map-list.')
            ->addAlias('/list');
        ChatCommand::add('/disabled', [self::class, 'mleShowDisableMaps'], 'Open disabled map-list.');
        ChatCommand::add('/jukebox', [self::class, 'showMapQueue'], 'Open jukebox/map-queue.')
            ->addAlias('/queue')
            ->addAlias('/jb');
    }

    public static function playerConnect(Player $player)
    {
        self::sendFavorites($player);
        self::sendUpdatedMapList($player);
        self::mapQueueUpdated(MapQueue::all());
        self::sendRecordsJson($player);
        Template::show($player, 'MapList.map-queue');
        Template::show($player, 'MapList.map-widget');
        Template::show($player, 'MapList.map-list');
    }

    public static function sendFavorites(Player $player)
    {
        $favorites = $player->favorites()->where('enabled', true)->pluck('uid')->toJson();

        Template::show($player, 'MapList.update-favorites', compact('favorites'), false, 20);
    }

    public static function sendRecordsJson(Player $player)
    {
        $locals = DB::table(LocalRecords::TABLE)->where('Player', '=', $player->id)->orderBy('Rank')->pluck('Rank', 'Map')->toJson();
        $dedis = DB::table(Dedimania::TABLE)->where('Player', '=', $player->id)->orderBy('Rank')->pluck('Rank', 'Map')->toJson();

        Template::show($player, 'MapList.update-records', compact('locals', 'dedis'), false, 20);
    }

    public static function searchMap(Player $player, $cmd, $query = "")
    {
        Template::show($player, 'MapList.update-search-query', compact('query'), false, 20);
    }

    /**
     * @param Player $player
     * @param null $cmd
     * @param int $page
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function mleShowDisableMaps(Player $player, $cmd = null, int $page = 1)
    {
        $perPage = 22;
        $maps = Map::whereEnabled(0)->skip(($page - 1) * $perPage)->take($perPage)->get();
        $pages = ceil(Map::whereEnabled(0)->count() / $perPage);

        Template::show($player, 'MapList.disabled-maps', ['maps' => $maps, 'pages' => $pages, 'page' => $page]);
    }

    /**
     * @param Player $player
     * @param string $mapUid
     * @throws UnauthorizedException
     */
    public static function mleEnableMap(Player $player, string $mapUid, int $page)
    {
        authorize($player, 'map_add');

        try {
            $map = Map::whereUid($mapUid)->firstOrFail();
        } catch (Exception $e) {
            dangerMessage('Failed to enable map ', secondary($mapUid))->send($player);
            return;
        }

        MapController::enableMap($player, $map);
        self::mleShowDisableMaps($player, null, $page);
    }

    /**
     * Player add favorite map
     *
     * @param Player $player
     * @param string $mapUid
     */
    public static function favAdd(Player $player, string $mapUid)
    {
        $player->favorites()->attach(Map::getByUid($mapUid)->id);
        self::sendFavorites($player);
    }

    /**
     * Player remove favorite map
     *
     * @param Player $player
     * @param string $mapUid
     */
    public static function favRemove(Player $player, string $mapUid)
    {
        $player->favorites()->detach(Map::getByUid($mapUid)->id);
        self::sendFavorites($player);
    }

    /**
     * @param Player|null $player
     */
    public static function sendUpdatedMapList(Player $player = null)
    {
        $maps = self::getMapList();
        $mapAuthors = self::getMapAuthors($maps->pluck('a'))->keyBy('id');

        if ($player) {
            Template::show($player, 'MapList.update-map-list', [
                'maps' => $maps->chunk(100),
                'mapAuthors' => $mapAuthors->toJson(),
            ], false, 2);
        } else {
            Template::showAll('MapList.update-map-list', [
                'maps' => $maps->chunk(100),
                'mapAuthors' => $mapAuthors->toJson(),
            ], 2);
        }
    }

    private static function getMapList(): Collection
    {
        return DB::table('maps')
            ->select('maps.id', 'name', 'author', 'uid', 'cooldown', 'mx_id')
            ->where('enabled', '=', 1)
            ->get()
            ->transform(function ($data) {
                $voteAverage = 0;

                if (Cache::has('mx-details/' . $data->mx_id)) {
                    $mxDetails = Cache::get('mx-details/' . $data->mx_id);

                    if ($mxDetails->RatingVoteCount > 0) {
                        $voteAverage = $mxDetails->RatingVoteAverage;
                    }
                }

                $authorTime = -1;
                if (Cache::has('gbx/' . $data->uid)) {
                    $gbx = Cache::get('gbx/' . $data->uid);
                    $authorTime = $gbx->AuthorTime;
                }

                return [
                    'id' => (string)$data->id,
                    'name' => $data->name,
                    'a' => $data->author,
                    'r' => sprintf('%.1f', $voteAverage),
                    'uid' => $data->uid,
                    'c' => $data->cooldown,
                    'author_time' => $authorTime
                ];
            });
    }

    /**
     * @param $authorIds
     * @return Collection
     */
    private static function getMapAuthors($authorIds): Collection
    {
        return DB::table('players')
            ->select('NickName as nick', 'Login as login', 'id')
            ->whereIn('id', $authorIds)
            ->get()
            ->transform(function ($player) {
                return [
                    'nick' => $player->nick,
                    'login' => $player->login,
                    'id' => $player->id,
                ];
            });
    }

    /**
     * @param Player $player
     * @param $mapUid
     */
    public static function disableMapEvent(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->get()->first();

        if ($map) {
            QueueController::dropMap($player, $map->uid);
            MapController::disableMap($player, $map);
        }
    }

    /**
     * @param Player $player
     * @param $mapUid
     */
    public static function deleteMapPermEvent(Player $player, $mapUid)
    {
        $map = Map::whereUid($mapUid)->first();

        if ($map) {
            QueueController::dropMap($player, $map->uid);
            MapController::deleteMap($player, $map);
        }
    }

    /**
     * Send updated map queue to everyone
     *
     * @param Collection $queueItems
     */
    public static function mapQueueUpdated(Collection $queueItems)
    {
        $mapQueue = $queueItems->map(function (MapQueue $item) {
            return [
                'id' => $item->map->id,
                'uid' => $item->map->uid,
                'name' => $item->map->name,
                'author' => $item->map->author->NickName,
                'login' => $item->player->Login,
                'nick' => $item->player->NickName,
            ];
        })->filter();

        Template::showAll('MapList.update-map-queue', compact('mapQueue'));
    }

    /**
     * @param Player $player
     */
    public static function showMapQueue(Player $player)
    {
        Template::show($player, 'MapList.show-queue', null, false);
    }
}