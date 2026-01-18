<?php

namespace App\Mail;

use App\Consts;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DepositUsdAlertsToAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public $transaction;
    public $email;

    /**
     * Create a new message instance.
     *
     * @param $email
     * @param $transaction
     */
    public function __construct($email, $transaction)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->transaction = $transaction;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = User::find($this->transaction->user_id);
        $locale = Consts::DEFAULT_USER_LOCALE;
        $date = (string)Carbon::now('UTC');
        $title = __('emails.new_deposit_title', [], $locale);

        return  $this->view('emails.deposit_usd_alerts_to_admin')
            ->subject($title . ' ' . $date . ' (UTC)')
            ->to($this->email)
            ->with([
                'title' => $title,
                'user_email' => $user->email,
                'locale' => $locale
            ]);
    }
}
