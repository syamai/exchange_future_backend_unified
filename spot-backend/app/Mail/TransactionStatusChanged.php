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

class TransactionStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $email;
    protected $transaction;
    protected $userService;

    public function __construct($email, $transaction)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->email = $email;
        $this->transaction = $transaction;
        $this->userService = new UserService();
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = User::find($this->transaction->user_id);
        $locale = $this->userService->getUserLocale($user->id);
        $account = __('emails.account_info', [
                'bank' => $this->transaction->bank_name,
                'accountNumber' => $this->getHiddenAccountNumber($this->transaction->account_no),
                'name' => $this->transaction->account_name
            ], $locale);

        $date = (string)Carbon::now('UTC');
        $type = BigNumber::new($this->transaction->amount)->comp(0) > 0 ? 'deposit' : 'withdrawal';

        $result = $this->transaction->status == Consts::TRANSACTION_STATUS_SUCCESS ? 'approved' : 'rejected';
        $title = $this->getTitle($type, $result, $locale);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $this->transaction->user_id,
        ])->first();

        return  $this->view('emails.transaction_status_changed')
                    ->subject($title . ' ' . $date . ' (UTC)')
                    ->to($this->email)
                    ->with([
                        'title' => $title,
                        'type' => $type,
                        'result' => $result,
                        'email' => $user->email,
                        'account' => $account,
                        'amount'  => number_format(abs($this->transaction->amount)),
                        'coin' => 'USD',
                        'date' => $date,
                        'locale' => $locale,
                        'transaction' => $this->transaction,
                        'user' => $user,
                        'anti_phishing_code' => $antiPhishingCode,
                    ]);
    }

    private function getHiddenAccountNumber($accountNo)
    {
        if (!$accountNo) {
            return $accountNo;
        }

        $length = strlen($accountNo);
        if ($length > 4) {
            $accountNo = substr($accountNo, 0, 2) . str_repeat('*', $length - 4)
                . substr($accountNo, $length - 2);
        }
        return $accountNo;
    }

    private function getTitle($type, $result, $locale)
    {
        $titleKey = '';
        if ($type == 'deposit') {
            if ($result == 'approved') {
                $titleKey = 'emails.deposit_withdraw_usd_alerts.deposit.approved.title';
            } elseif ($result == 'rejected') {
                $titleKey = 'emails.deposit_withdraw_usd_alerts.deposit.rejected.title';
            }
        } elseif ($type == 'withdrawal') {
            if ($result == 'approved') {
                $titleKey = 'emails.deposit_withdraw_usd_alerts.withdrawal.approved.title';
            } elseif ($result == 'rejected') {
                $titleKey = 'emails.deposit_withdraw_usd_alerts.withdrawal.rejected.title';
            }
        }
        return __($titleKey, ['APP_NAME' => config('app.name')], $locale);
    }
}
