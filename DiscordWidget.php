<?php


namespace EvoSC\Modules\DiscordWidget;

use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Timer;
use EvoSC\Classes\ManiaLinkEvent;
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

    public static function updateChannels($withCache=false)
    {
        if ($withCache && count(self::$channels) > 0)
            return;

        self::updateWidget();
        $newChannels = [];

        foreach (config('discord-widget.channels') as $configChannel) {
            foreach (self::$widget->channels as $widgetChannel) {
                if ($widgetChannel->id == $configChannel->id) {
                    $numConnected = 0;
                    foreach (self::$widget->members as $widgetMember) {
                        if (property_exists($widgetMember, 'channel_id') && $widgetMember->channel_id == $widgetChannel->id) {
                            $numConnected++;
                        }
                    }

                    array_push($newChannels, [
                        'connected' => $numConnected,
                        'name' => $widgetChannel->name,
                        'invite' => $configChannel->invite,
                        'id' => $configChannel->id
                    ]);
                }
            }
        }

        self::$channels = $newChannels;
    }

    public static function update() {
        self::updateChannels();

        $channels = collect(self::$channels)->map(function($item, $key) {
            return [
                'name' => $item['name'],
                'connected' => $item['connected'],
                'id' => $item['id']
            ];
        });

        $presenceCount = self::$widget->presence_count;

        Template::showAll('DiscordWidget.widget-update', compact('channels', 'presenceCount'));
    }

    public static function displayWidget(Player $player)
    {
        self::updateChannels(true);
        $channels = self::$channels;
        $widget = [
            'instant_invite' => self::$widget->instant_invite,
            'presence_count' => self::$widget->presence_count
        ];

        Template::show($player, 'DiscordWidget.widget', compact('channels', 'widget'));
    }
}