<?php

namespace EvoSC\Modules\ServerStats;

use EvoSC\Classes\DB;
use EvoSC\Classes\Server;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Module;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class ServerStats extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'onPlayerJoin']);
        Hook::add('PlayerDisconnect' [self::class, 'onPlayerLeave']);

        Hook::add('BeginMap', [self::class, 'beginMap']);

        DB::table('server-stats')->insert([
            'Title' => Server::getServerName(),
            'MaxPlayers' => Server::getMaxPlayers()['CurrentValue'],
            'CurrentPlayers' => 0,
            'CurrentMapName' => "None",
        ]);
    }

    /**
     * @param Player $player
     */
    public static function onPlayerJoin(Player $player)
    {
        DB::table('server-stats')->update([
            'CurrentPlayers' => count(Server::getPlayerList()),
        ]);
    }

    /**
     * @param Player $player
     */
    public static function onPlayerLeave(Player $player)
    {
        DB::table('server-stats')->update([
            'CurrentPlayers' => count(Server::getPlayerList()),
        ]);
    }

    /**
     */
    public static function beginMap()
    {
        DB::table('server-stats')->update([
            'CurrentMapName' => Server::getCurrentMapInfo()->name,
        ]);
    }
}