<?php

namespace App\Mail;

use App\Consts;
use App\Models\User;
use App\Models\UserAntiPhishing;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WithdrawalVerifyAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $transaction;
    protected $userService;

    /**
     * Create a new message instance.
     *
     * @param $transaction
     */
    public function __construct($transaction)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->transaction = $transaction;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $date = (string)Carbon::now('UTC');
        $user = User::find($this->transaction->user_id);
        $locale = $user->getLocale();

        $token = $this->transaction->transaction_id;
        $currency = strtoupper($this->transaction->currency);

        $url = $this->getUrl($currency, $token);

        $subject = __('emails.withdraw_verify.subject', [ // Withdraw verification
            'APP_NAME' => ucfirst(env('APP_NAME')),
            'date' => "$date"
        ], $locale);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

        return $this->view('emails.withdraw_verify_alert')
            ->subject($subject)
            ->to($user->email)
            ->with([
                'amount' => BigNumber::new(abs($this->transaction->amount))->toString(),
                'fee' => BigNumber::new($this->transaction->fee)->toString(),
                'currency' => $currency,
                'toAddress' => $this->transaction->to_address,
                'locale' => $locale,
                'url' => $url,
                'token' => $token,
                'name' => $user->name,
                'date' => $date,
                'email' => $user->email,
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }

    private function getUrl($currency, $token)
    {
        return config('app.web') . Consts::ROUTE_WITHDRAWAL_VERIFY . $currency . Consts::ROUTE_WITHDRAWAL_TOKEN . $token;
    }
}
