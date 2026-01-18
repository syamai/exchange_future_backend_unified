<?php

namespace App\Mail;

use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WithdrawalCanceledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $user;
    public $transaction;
    protected $userService;

    public function __construct($user, $transaction)
    {
        $this->user = $user;
        $this->transaction = $transaction;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = $this->user;
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($user->id);

        $date = (string)Carbon::now('UTC');
        /*$subject = __('emails.withdrawal_canceled.subject', [], $locale);
        $createdAt = Carbon::createFromTimestamp($this->transaction->created_at / 1000)
                        ->format('Y-m-d H:i:s');
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

        return $this->view('emails.withdrawal_canceled')
                    ->subject($subject . ' ' . $date . ' (UTC)')
                    ->to(@$user->email)
                    ->with([
                        'createdAt' => $createdAt,
                        'email' => @$user->email,
                        'transaction' => $this->transaction,
                        'locale' => $locale,
                        'anti_phishing_code' => $antiPhishingCode,
                    ]);*/

		$subject = __('emails.withdrawal_canceled_new.subject', [], $locale);
		$createdAt = Carbon::createFromTimestamp($this->transaction->created_at / 1000)
			->format('Y-m-d H:i:s');
		$antiPhishingCode = UserAntiPhishing::where([
			'is_active' => true,
			'user_id' => $user->id,
		])->first();

		return $this->view('emails.withdrawal_canceled_new')
			->subject($subject . ' ' . $date . ' (UTC)')
			->to(@$user->email)
			->with([
				'createdAt' => $createdAt,
				'email' => @$user->email,
				'transaction' => $this->transaction,
				'locale' => $locale,
				'anti_phishing_code' => $antiPhishingCode,
			]);
    }
}
