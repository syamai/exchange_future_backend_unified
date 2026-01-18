<?php

namespace App\Mail;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChangeColdWalletSetting extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $changeAddress;
    public $changedEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email, $changedAddress, $changedEmail)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->email = $email;
        $this->changeAddress = $changedAddress;
        $this->changedEmail = $changedEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $locale = Consts::DEFAULT_USER_LOCALE;
        $subject = __('emails.change_coldWallet_setting.subject', [], $locale);

        return $this->view('emails.admin_change_coldWallet_setting')
                    ->subject($subject)
                    ->to($this->email)
                    ->with([
                        'email' => $this->email,
                        'changedAddress' => $this->changeAddress,
                        'changedEmail' => $this->changedEmail,
                        'locale' => $locale,
                    ]);
    }
}
