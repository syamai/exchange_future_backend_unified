<?php

namespace App\Mail;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels as SerializesModelsAlias;

class MemoryAlertMail extends Mailable
{
    use Queueable, SerializesModelsAlias;

    private $admin;
    private $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct($admin, $data)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->admin = $admin;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $locale = $this->admin->locale;
        $subject = __('emails.memory_alert.subject', [], $locale);
        $threshold = config('monitor.memory.threshold');

        return $this->view('emails.memory_alert')
            ->subject($subject)
            ->to($this->admin->email)
            ->with([
                'email' => $this->admin->email,
                'locale' => $locale,
                'threshold' => $threshold,
                'data' => $this->data,
            ]);
    }
}
