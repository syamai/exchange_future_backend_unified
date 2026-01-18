<?php

namespace App\Notifications;

use App\Mail\ForgotPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Session;
use App\Consts;

class ResetPassword extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public $token;
    public $locale;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($token)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */

    public function toLine($notifiable): array|string
    {
        return $this->getMessage();
    }

    public function toTelegram($notifiable)
    {
        $message = $this->getMessage();
        return $message;
    }

    private function getMessage(): string
    {
        $locale = Session::get('user.locale', Consts::DEFAULT_USER_LOCALE);
        return "\n" . __('notifications.confirmation_reset_password.receiving_text', [], $locale)
            . "\n" . __('notifications.confirmation_reset_password.please_click', [], $locale) .
            "\n" . reset_password_url($this->token) .
            "\n" . __('notifications.confirmation_reset_password.valid_24h', [], $locale) .
            "\n" . __('notifications.confirmation_reset_password.please_confirm', [], $locale);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return ForgotPassword
     */
    public function toMail($notifiable): ForgotPassword
    {
        return new ForgotPassword($notifiable->email, $this->token);
    }
}
