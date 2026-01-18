<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Services\UserService;

class MarginDeleverageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userId;
    public $symbol;
    protected $userService;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($userId, $symbol)
    {
        $this->userId = $userId;
        $this->symbol = $symbol;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->userService = new UserService();
        $locale = $this->userService->getUserLocale($this->userId);
        $email = $this->userService->getEmailByUserId($this->userId);

        $title = __('emails.margin_deleverage.title');
        $marginExchangeUrl = margin_exchange_url($this->symbol);

        return $this->subject($title)
            ->to($email)
            ->view('emails.margin_deleverage')
            ->with([
                'title' => $title,
                'email' => $email,
                'userLocale' => $locale,
                'marginExchangeUrl' => $marginExchangeUrl,
                'user' => User::find($this->userId)
            ]);
    }
}
