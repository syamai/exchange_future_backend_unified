<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\User;
use App\Models\UserAntiPhishing;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChangePassword extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $email;
    protected $userService;

    public function __construct($email)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->email = $email;
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
        $subject = __('emails.change_password.subject', [], $locale);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $user->id,
        ])->first();

		$updatedAt = Carbon::create($user->updated_at)
			->format('Y-m-d H:i:s');


        return  $this->view('emails.change_password')
            ->subject($subject)
            ->to($this->email)
            ->with([
                'locale' => $locale,
                'email' => $this->email,
                'user' => $user,
				'updatedAt' => $updatedAt,
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
