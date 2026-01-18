<?php

namespace Transaction\Mails;

use App\Models\User;
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
        $url = route('verify.withdraw', $this->transaction->transaction_id);
        $subject = __('emails.withdraw_verify.subject', [ // Withdraw verification
            'APP_NAME' => env('APP_NAME'),
            'date' => "$date"
        ], $locale);

        return $this->view('emails.withdraw_verify_alert')
            ->subject($subject)
            ->to($user->email)
            ->with([
                'amount' => BigNumber::new(abs($this->transaction->amount))->toString(),
                'fee' => BigNumber::new($this->transaction->fee)->toString(),
                'currency' => strtoupper($this->transaction->currency),
                'toAddress' => $this->transaction->to_address,
                'locale' => $locale,
                'url' => $url,
                'token' => $this->transaction->transaction_id,
                'name' => $user->name,
                'date' => $date,
                'email' => $user->email,
            ]);
    }
}
