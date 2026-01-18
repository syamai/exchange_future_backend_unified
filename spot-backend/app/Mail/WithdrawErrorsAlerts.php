<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\User;
use App\Models\UserAntiPhishing;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WithdrawErrorsAlerts extends Mailable
{
    use Queueable, SerializesModels;

    public $transaction;
    protected $userService;
    protected $currency;

    /**
     * Create a new message instance.
     *
     * @param $transaction
     * @param $currency
     */
    public function __construct($transaction, $currency)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->transaction = $transaction;
        $this->currency = $currency;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->userService = new UserService();
        $user = User::find($this->transaction->user_id);
        $locale = $this->userService->getUserLocale($user->id);

        $date = (string)Carbon::now('UTC');
        $title = __('emails.withdraw_errors_alerts.title', [], $locale);
        $total = BigNumber::new(abs($this->transaction->amount))->add($this->transaction->fee)->toString();
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

        return $this->view('emails.withdraw_errors_alerts')
            ->subject($title . ' ' . $date . ' (UTC)')
            ->to($user->email)
            ->with([
                'title' => $title,
                'amount' => BigNumber::new($total)->toString(),
                'coin' => strtoupper($this->currency),
                'locale' => $locale,
                'email' => $user->email,
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
