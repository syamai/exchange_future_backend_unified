<?php

namespace App\Notifications;

use App\Consts;
use App\Http\Services\UserService;
use App\Mail\AdminKyc as MailableAdminKyc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminKyc extends BaseNotification implements ShouldQueue
{
    use Queueable;

    private $userKyc;
    private $userService;

    public function __construct($userKyc)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        logger()->info('=================AdminKyc();');
        $this->userKyc = $userKyc;
        $this->userService = new UserService();
    }

    public function toMail()
    {
        $jsonData = null;
        try {
            $jsonData = json_decode($this->userKyc);
        } catch (\Exception $e) {
            $jsonData = $this->userKyc;
        }
        return new MailableAdminKyc($jsonData);
    }

    public function toLine($notifiable)
    {
        return $this->getMessage($notifiable);
    }

    private function getMessage($notifiable)
    {
        $locale = $this->userService->getUserLocale($notifiable->id);
        $message = __('emails.confirm_kyc.line_1', [], $locale)
            . "\n" . __('emails.confirm_kyc.line_3', [], $locale)
            . "\n" . login_url()

            . "\n \n" . ' *' . __('emails.confirm_kyc.line_4', [], $locale)
            . "\n" . ' *' . __('emails.confirm_kyc.line_5', [], $locale)

            . "\n \n" . __('emails.confirm_kyc.line_6', [], $locale)

            . "\n \n" . '  - ' . __('emails.confirm_kyc.line_7', [], $locale)
            . "\n" . '  - ' . __('emails.confirm_kyc.line_8', [], $locale)
            . "\n" . '  - ' . __('emails.confirm_kyc.line_9', [], $locale)
            . "\n" . '  - ' . __('emails.confirm_kyc.line_10', [], $locale)
            . "\n" . '  - ' . __('emails.confirm_kyc.line_11', [], $locale)
            . "\n" . '  - ' . __('emails.confirm_kyc.line_12', [], $locale)
            . "\n" . '  - ' . __('emails.confirm_kyc.line_13', [], $locale)
            . "\n \n" . __('emails.confirm_kyc.line_14', [], $locale);

        return $message;
    }

    public function toTelegram($notifiable)
    {
        return $this->getMessage($notifiable);
    }
}
