<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;
use App\Consts;
use App\Mail\LoginNewDeviceMail;
use Carbon\Carbon;

class LoginNewDevice extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $device;
    protected $code;
    public $message;
    public $locale;

    public function __construct($device, $code)
    {
        $this->queue = Consts::QUEUE_HANDLE_MAIL;
        $this->device = $device;
        $this->code = $code;
    }
    public function toLine($notifiable)
    {
        $message = $this->getMessage();
        return $message;
    }

    public function toTelegram($notifiable)
    {
        $message = $this->getMessage();
        return $message;
    }

    public function toMail($notifiable)
    {
        return new LoginNewDeviceMail($this->device, $this->code);
    }

    private function getMessage()
    {
        $date = (string)Carbon::now('UTC');
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);
        $this->message = "\n" . __('emails.login_new_device.attemped_access', [], $locale)
            . "\n" . __('emails.login_new_device.email', [], $locale)
            . ": ". $this->device->user->email
            . "\n" . __('emails.login_new_device.device', [], $locale)
            . ": " . $this->device->operating_system
            . "\n" . __('emails.login_new_device.time', [], $locale)
            . ": " . $date . " (UTC)"
            . "\n" . __('emails.login_new_device.ip', [], $locale)
            . ": " . $this->device->latest_ip_address
            . "\n" . __('emails.login_new_device.legitimate_activity', [], $locale)
            . "\n" . grant_device_url($this->code);
        return $this->message;
    }
}
