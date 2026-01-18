<?php

namespace App\Mail;

use App\Http\Services\UserService;
use App\Models\User;
use App\Models\UserAntiPhishing;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendVoucherForUser extends Mailable
{
    use Queueable, SerializesModels;

    protected $userVoucher;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->userVoucher = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $userService = new UserService();
        $locale = $userService->getUserLocale($this->userVoucher['user_id']);
        $title = __('emails.vouchers.send_voucher_title', ['APP_NAME' => config('app.name'), 'amount' => BigNumber::new($this->userVoucher['amount'])->toString(), 'currency' => strtoupper($this->userVoucher['currency'])], $locale);
        $user = User::findOrFail($this->userVoucher['user_id']);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

        return  $this->view('emails.send_voucher_for_user')
            ->subject($title)
            ->to($user->email)
            ->with([
                'user' => $this->userVoucher,
                'email' => $user->email,
                'locale' => $locale,
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
