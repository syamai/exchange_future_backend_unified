<?php

namespace App\Mail;

use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use Carbon\Carbon;
use http\Client\Curl\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailVerifyAntiPhishing extends Mailable
{
    use Queueable, SerializesModels;

    private $user;
    private $type;
    private $code;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $code, $type)
    {
        $this->user = $user;
        $this->type = $type;
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $userService = new UserService();
        $locale = $userService->getUserLocale($this->user->id);
        $title = $this->type == 'create' ? __('emails.anti_phishing.title_create', ['APP_NAME' => config('app.name')], $locale) : __('emails.anti_phishing.title_change', ['APP_NAME' => config('app.name')], $locale);
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $this->user->id,
        ])->first();

        return  $this->view('emails.verify_anti_phishing')
            ->subject($title)
            ->to($this->user->email)
            ->with([
                'email' => $this->user->email,
                'code'  => $this->code,
                'locale' => $locale,
                'type' => $this->type == 'create' ? 'create' : 'change',
                'text' => $this->type == 'create' ? 'create an' : 'change your',
                'anti_phishing_code' => $antiPhishingCode,
            ]);
    }
}
