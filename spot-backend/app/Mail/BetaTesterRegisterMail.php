<?php

namespace App\Mail;

use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BetaTesterRegisterMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $user;

    public function __construct($user)
    {
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
        $locale = (new UserService())->getUserLocale($user->id);
        $subject = __('emails.register_beta_tester.subject', [], $locale);

        return $this->view('emails.beta_tester_request_register')
                    ->subject($subject)
                    ->to(@$user->email)
                    ->with([
                        'email' => @$user->email,
                        'locale' => $locale,
                        'user' => @$user
                    ]);
    }
}
