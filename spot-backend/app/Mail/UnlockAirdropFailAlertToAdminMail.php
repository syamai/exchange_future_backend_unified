<?php

namespace App\Mail;

use App\Http\Services\UserService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UnlockAirdropFailAlertToAdminMail extends Mailable
{
    use Queueable, SerializesModels;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $adminEmail;
    public $userEmail;
    public $userId;
    public $amount;
    protected $userService;

    /**
     * Create a new message instance.
     *
     * @param $transaction
     * @param $currency
     */
    public function __construct($adminEmail, $userEmail, $userId, $amount)
    {
        $this->adminEmail = $adminEmail;
        $this->userEmail = $userEmail;
        $this->userId = $userId;
        $this->amount = $amount;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($this->userId);
        $date = (string)Carbon::now('UTC');
        $title = __('emails.unlock_airdrop_fail.title', [], $locale);

        return  $this->view('emails.unlock_airdrop_fail_alert_to_admin')
            ->subject($title . ' ' . $date . ' (UTC)')
            ->to($this->adminEmail)
            ->with([
                'title' => $title,
                'amount'  => $this->amount,
                'email' => $this->userEmail,
                'locale' => $locale
            ]);
    }
}
