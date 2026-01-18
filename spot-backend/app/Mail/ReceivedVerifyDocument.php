<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceivedVerifyDocument extends Mailable
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
        $date = (string)Carbon::now('UTC');

        $user = $this->user;
        $userService = new UserService();
        $locale = $userService->getUserLocale($user->id);
        $title = __('emails.received_verify_document.subject', [], $locale);
        $subject = $title . ' - [' . $date . '] (UTC)' ;

        return  $this->view('emails.email_received_verify_document')
            ->subject($subject)
            ->to($this->user->email)
            ->with([
                'locale' => $locale,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'user' => $this->user
            ]);
    }
}
