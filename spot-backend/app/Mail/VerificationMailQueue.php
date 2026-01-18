<?php

namespace App\Mail;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationMailQueue extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $email;
    protected $code;
    protected $userLocale;

    public function __construct($email, $code, $userLocale)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->email = $email;
        $this->code = $code;
        $this->userLocale = $userLocale;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = config('app.name');
        $locale = $this->userLocale;
        $subject = __('emails.registed.confirmation_email_subject', ['APP_NAME' => $appName], $locale);

        return $this->view('emails.confirmation_email_content')
                    ->subject($subject)
                    ->to($this->email)
                    ->with([
                        'email' => $this->email,
                        'code'  => $this->code,
                        'userLocale' => $locale,
                        'locale' => $locale,
                        'appName' => $appName,
                    ]);
    }
}
