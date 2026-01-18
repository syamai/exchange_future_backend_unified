<?php

namespace App\Mail;

use App\Consts;
use App\Order;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Contact extends Mailable
{
    use Queueable, SerializesModels;

    public $content;
    public $email;
    public $name;
    public $phone;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($message, $email, $name, $phoneNumber)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->content = $message;
        $this->email = $email;
        $this->name = $name;
        $this->phone = $phoneNumber;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $date = (string)Carbon::now('UTC');
        $subject = __('service_center.send_contact_subject') . ' [' . $date . '] (UTC)';

        return $this->from($this->email, $this->name)
                    ->subject($subject)
                    ->view('vendor.service_center.contact')
                    ->with([
                        'name' => $this->name,
                        'email' => $this->email,
                        'content' => $this->content,
                        'phone' => $this->phone,
                    ]);
    }
}
