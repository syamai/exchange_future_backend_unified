<?php

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class Marketing extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public $content;
    public $email;
    public $title;
    public $fromEmail;
    protected $userService;

    public function __construct($content, $email, $title, $fromEmail = null)
    {
        $this->queue = Consts::QUEUE_MARKETING_MAIL;
        $this->content = $content;
        $this->email = $email;
        $this->title = $title;
        $this->fromEmail = $fromEmail;
    }

    public function toMail($notifiable)
    {
        return new \App\Mail\Marketing($this->content, $this->email, $this->title, $this->fromEmail);
    }

    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    public function toTelegram($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $message = "\n" . $this->title . "\n" . trim(strip_tags(str_replace('<', ' <', $this->content)));
        return $message;
    }
}
