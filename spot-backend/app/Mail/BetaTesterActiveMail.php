<?php

namespace App\Mail;

use App\Http\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BetaTesterActiveMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $user;
    protected $pair;

    public function __construct($user, $pair)
    {
        $this->user = $user;
        if ($pair) {
            $this->pair = $pair;
        } else {
            $pair = 'ETH/BTC';
        }
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
        $subject = __('emails.beta_tester_active.subject', [], $locale);
        $adminEmail = 'exchange@sotatek.io';

        return $this->view('emails.beta_tester_active')
                    ->subject($subject)
                    ->to(@$user->email)
                    ->with([
                        'email' => @$user->email,
                        'pair' => $this->pair,
                        'locale' => $locale,
                        'adminEmail' => $adminEmail,
                        'user' => @$user
                    ]);
    }
}
