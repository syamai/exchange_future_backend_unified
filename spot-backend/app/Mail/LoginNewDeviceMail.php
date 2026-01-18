<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\UserAntiPhishing;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginNewDeviceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $device;
    protected $code;
    public $locale;

    public function __construct($device, $code)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->device = $device;
        $this->code = $code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = config('app.name');
        $userService = new UserService();
        $locale = $userService->getUserLocale(@$this->device->user_id);
        $title = __('emails.login_new_device.subject', [], $locale);
        $date = (string)Carbon::now('UTC');
        $antiPhishingCode = UserAntiPhishing::where([
            'is_active' => true,
            'user_id' => $this->device->user->id,
        ])->first();

        return  $this->view('emails.email_login_newDevice')
                    ->subject($title . ' ' . $date . ' (UTC)')
                    ->to($this->device->user->email)
                    ->with([
                        'email' => $this->device->user->email,
                        'device'  => $this->device->operating_system,
                        'browse' => $this->device->platform,
                        'ip_address' => $this->device->latest_ip_address,
                        'username' => $this->device->user->name,
                        'code' => $this->code,
                        'time' => $date . ' (UTC)',
						'locale' => $locale,
						'user_locale' => $locale,
                        'user' => $this->device->user,
                        'appName' => $appName,
                        'anti_phishing_code' => $antiPhishingCode,
                    ]);
    }
}
