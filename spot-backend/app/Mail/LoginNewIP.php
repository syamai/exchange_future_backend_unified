<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginNewIP extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $device;

    public function __construct($device)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->device = $device;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $userService = new UserService();
        $locale = $userService->getUserLocale(@$this->device->user_id);
        $title = __('emails.new_login.new_ip_title', [], $locale);
        $date = (string)Carbon::now('UTC');
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $this->device->user->id,
        ])->first();

        return  $this->view('emails.email_login_newIP')
                    ->subject($title . ' ' . $date . ' (UTC)')
                    ->to($this->device->user->email)
                    ->with([
                        'email' => $this->device->user->email,
                        'device'  => $this->device->operating_system,
                        'browse' => $this->device->platform,
                        'ip_address' => $this->device->latest_ip_address,
                        'username' => $this->device->user->name,
                        'time' => $date . ' (UTC)',
                        'locale' => $locale,
                        'user' => $this->device->user,
                        'anti_phishing_code' => $antiPhishingCode,
                    ]);
    }
}
