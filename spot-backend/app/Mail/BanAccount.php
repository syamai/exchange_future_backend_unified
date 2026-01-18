<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BanAccount extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $user;
    protected $userService;

    public function __construct($user)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->user = $user;
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

        $subject = __('emails.ban_account.subject', [], $locale);
        $contactLink = config('app.zendesk_domain');
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

        return  $this->view('emails.ban_account')
                    ->subject($subject)
                    ->to(@$user->email)
                    ->with([
                        'contactLink' => $contactLink,
                        'email' => @$user->email,
                        'locale' => $locale,
                        'user' => @$user,
                        'anti_phishing_code' => $antiPhishingCode,
                    ]);
    }
}
