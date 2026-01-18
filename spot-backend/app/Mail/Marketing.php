<?php

namespace App\Mail;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Marketing extends Mailable
{
    use Queueable, SerializesModels;

    public $content;
    public $email;
    public $title;
	public $fromEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($message, $email, $title, $fromEmail = null)
    {
        $this->queue = Consts::QUEUE_MARKETING_MAIL;
        $this->content = $message;
        $this->email = $email;
        $this->title = $title;
		$this->fromEmail = $fromEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mailer = $this->subject($this->title)
            ->to($this->email)
            ->view('emails.marketing')
            ->with(
                ["title" => $this->title],
                ["content" => $this->content]
            );
		if ($this->fromEmail) {
			$mailer->from($this->fromEmail, env('APP_NAME'));
		}

		return $mailer;
    }
}
