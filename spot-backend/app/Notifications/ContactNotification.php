<?php
/**
 * Created by PhpStorm.
 * User: amanpuri
 * Date: 06/06/2019
 * Time: 15:46
 */

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Services\MasterdataService;
use Illuminate\Support\Facades\Log;

class ContactNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $request;
    private $userService;

    public function __construct($request)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->request = $request;
        $this->userService = new UserService();
    }

    public function toMail($notifiable)
    {
        $name = $this->request->input('name');
        $email = $this->request->input('email');
        $phone = $this->request->input('phone');
        $content = $this->request->input('message');
        $emailConfig = MasterdataService::getOneTable('settings')->where('key', Consts::SETTING_CONTACT_EMAIL)->first();

        if ($emailConfig) {
            return new \App\Mail\Contact($content, $email, $name, $phone);
        } else {
            Log::error("Contact email is not set, please update master data");
        }
    }

    public function toTelegram($notifiable): string
    {
        return $this->getMessage($notifiable);
    }

    public function toLine($notifiable): string
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable): string
    {
        $locale = $this->userService->getUserLocale($notifiable->id);
        $message = __('emails.contact.line_1', [], $locale)
            . " \n " . __('emails.contact.line_2', [], $locale);

        return $message;
    }
}
