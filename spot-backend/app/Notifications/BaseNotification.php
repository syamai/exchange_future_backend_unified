<?php

namespace App\Notifications;

use App\Channels\TelegramChannel;
use App\Consts;
use App\Channels\LineChannel;
use Illuminate\Support\Str;
use Illuminate\Notifications\Notification;

class BaseNotification extends Notification
{

    public function via($notifiable)
    {
        logger()->info('BaseNotification: via');
        return $this->getAvailableChannels($notifiable);
    }

    public function mapChannel($channel)
    {
        logger()->info('BaseNotification: mapChannel: '.$channel);
        switch ($channel) {
            case Consts::LINE_CHANNEL:
                return LineChannel::class;
            case Consts::TELEGRAM_CHANNEL:
                return TelegramChannel::class;
            default:
                return $channel;
        }
    }

    public function getAvailableChannels($notifiable)
    {
        logger()->info('BaseNotification: getAvailableChannels');
        if (!$notifiable->userNotificationSettings) {
            return ['mail'];
        }

        logger()->info('Has mail');

        $drivers = $notifiable->userNotificationSettings
            ->where('is_enable', true)
            ->whereIn('channel', Consts::NOTIFICATION_CHANNELS)
            ->where('auth_key', '<>', '');

        $channels = $drivers->pluck('channel')->push('mail');

        $channels = $channels->filter(function ($channel) {
            return method_exists($this, $method = 'to' . Str::studly($channel));
        });

        return $channels->map(function ($channel) {
            return $this->mapChannel($channel);
        })->all();
    }
}
