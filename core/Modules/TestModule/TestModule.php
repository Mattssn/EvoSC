<?php


namespace EvoSC\Modules\TestModule;


use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Controllers\TemplateController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\CountDown\CountDown;
use EvoSC\Modules\EvoCupInfo\EvoCupInfo;
use EvoSC\Modules\ForceTeam\ForceTeam;
use EvoSC\Modules\HideScript\HideScript;
use EvoSC\Modules\InputSetup\InputSetup;
use EvoSC\Modules\MatchMakerWidget\MatchMakerWidget;
use EvoSC\Modules\MatchSettingsManager\MatchSettingsManager;
use EvoSC\Modules\SpeedoMeter\SpeedoMeter;
use EvoSC\Modules\TeamInfo\TeamInfo;
use Illuminate\Support\Collection;

class TestModule extends Module implements ModuleInterface
{
    /**
     * @inheritDoc
     */
    public static function start(string $mode, $isBoot = false)
    {
        InputSetup::add('test_stuff', 'Trigger TestModule::testStuff', [self::class, 'testStuff'], 'X', 'ma');

        ManiaLinkEvent::add('asdf', [self::class, 'sendaction_for_Controller']);
    }

    public static function testStuff(Player $player = null)
    {
        TemplateController::loadTemplates();
        MatchSettingsManager::showOverview($player);
    }

    public static function sendTestManialink(Player $player)
    {
        Template::show($player, 'TestModule.test');
    }

    public static function sendaction_for_Controller(Player $player, Collection $formData = null)
    {
        var_dump($player->Login, $formData);
    }
}