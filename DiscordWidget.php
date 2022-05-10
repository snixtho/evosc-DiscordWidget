<?php


namespace EvoSC\Modules\DiscordWidget;

use EvoSC\Classes\Cache;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Timer;
use Exception;

class DiscordWidget extends Module implements ModuleInterface
{
    static $channels = [];
    static $widget = [];

    /**
     * @inheritDoc
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'displayWidget']);
        Timer::create('UpdateDiscordWidget', [self::class, 'update'], '5m', true);
    }

    public static function updateWidget() {
        $url = config('discord-widget.url');
        try {
            $response = RestClient::get($url);

            if ($response->getStatusCode() != 200) {
                Log::error('Failed to fetch discord widget information: '. $result->getReasonPhrase);
                return null;
            }

            self::$widget = json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Log::error('Failed to fetch discord widget information: ' . $e->getCode());
        }

        return null;
    }

    public static function update() {
        self::updateChannels();

        $channels = self::$channels;
        $widget = self::$widget;

        Template::showAll('DiscordWidget.widget', compact('channels', 'widget'));
    }

    public static function updateChannels()
    {
        self::updateWidget();
        $newChannels = [];

        foreach (config('discord-widget.channels') as $configChannel) {
            foreach (self::$widget->channels as $widgetChannel) {
                if ($widgetChannel->id == $configChannel->id) {
                    $numConnected = 0;
                    foreach (self::$widget->members as $widgetMember) {
                        if ($widgetMember->channel_id != null && $widgetMember->channel_id == $widgetChannel->id) {
                            $numConnected++;
                        }
                    }

                    array_push($newChannels, [
                        'connected' => $numConnected,
                        'name' => $widgetChannel->name,
                        'invite' => $configChannel->invite,
                        'id' => $widgetChannel->id
                    ]);
                }
            }
        }

        self::$channels = $newChannels;
    }

    public static function displayWidget(Player $player)
    {
        self::updateChannels();
        // $channelConfigs = config('discord-widget.channels');
        $channels = self::$channels;
        $widget = self::$widget;

        Template::show($player, 'DiscordWidget.widget', compact('channels', 'widget'));
    }
}