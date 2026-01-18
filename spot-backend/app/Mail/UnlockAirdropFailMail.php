<?php

namespace App\Mail;

use App\Http\Services\UserService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UnlockAirdropFailMail extends Mailable
{
    use Queueable, SerializesModels;
    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $amount;
    public $email;
    public $userId;
    protected $userService;

    /**
     * Create a new message instance.
     *
     * @param $transaction
     * @param $currency
     */
    public function __construct($amount, $email, $userId)
    {
        $this->amount = $amount;
        $this->email = $email;
        $this->userId = $userId;
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

        return  $this->view('emails.unlock_airdrop_fail')
            ->subject($title . ' ' . $date . ' (UTC)')
            ->to($this->email)
            ->with([
                'title' => $title,
                'amount'  => $this->amount,
                'email' => $this->email,
                'locale' => $locale,
                'user' => User::find($this->userId)
            ]);
    }
}
