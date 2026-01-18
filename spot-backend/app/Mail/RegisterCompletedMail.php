<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels as SerializesModelsAlias;

class RegisterCompletedMail extends Mailable
{
    use Queueable, SerializesModelsAlias;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $user;

    public function __construct($user)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        logger()->info("REGISTER COMPLETE MAIL ========== " . json_encode($this->user));
        $user = User::find($this->user->id);
        $userService = new UserService();
        $locale = $userService->getUserLocale($user->id);
        $linkLogin = login_url();
        $subject = __('emails.register_completed.subject', [], $locale);

        return $this->view('emails.register_completed')
            ->subject($subject)
            ->to($user->email)
            ->with([
                'email' => $user->email,
                'linkLogin' => $linkLogin,
                'locale' => $locale,
                'user' => $user
            ]);
    }
}
