<?php

namespace App\Mail;

use App\Consts;
use App\Http\Services\UserService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels as SerializesModelsAlias;

class AdminKyc extends Mailable
{
    use Queueable, SerializesModelsAlias;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $userKyc;
    protected $userService;

    public function __construct($userKyc)
    {
        $this->queue = Consts::QUEUE_NEEDS_CONFIRM_MAIL;
        $this->userKyc = $userKyc;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = User::find($this->userKyc->user_id);
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($user->id);

        $date = (string)Carbon::now('UTC');
        $status = $this->getStatus($this->userKyc->status, $locale);
        $subject = __('emails.confirm_kyc.subject', ['status' => $status], $locale);
        $link_login = login_url();

        return $this->view('emails.admin_confirm_kyc')
            ->subject($subject)
            ->to($user->email)
            ->with([
                'status' => $status,
                'name' => @$this->userKyc->full_name,
                'date' => $date,
                'locale' => $locale,
                'link_login' => $link_login,
                'subject' => $subject,
                'email' => @$this->userKyc->user->email,
            ]);
    }

    private function getStatus($status, $locale)
    {
        $statusKey = '';
        if ($status == 'verified') {
            $statusKey = 'emails.kyc_verified';
        } elseif ($status == 'rejected') {
            $statusKey = 'emails.kyc_rejected';
        }
        return __($statusKey, [], $locale);
    }
}
