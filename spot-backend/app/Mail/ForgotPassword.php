<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\User;
use App\Models\UserAntiPhishing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $email;
    protected $token;
    protected $userService;

    public function __construct($email, $token)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->email = $email;
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = User::where('email', $this->email)->first();
        if (!$user) {
            return;
        }

        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($user->id);
        $subject = __('emails.confirmation_reset_password.subject', [], $locale);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

        return  $this->view('emails.confirmation_reset_password')
            ->subject($subject)
            ->to($this->email)
            ->with([
                'token' => $this->token,
                'locale' => $locale,
                'email' => $this->email,
                'user' => $user,
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
