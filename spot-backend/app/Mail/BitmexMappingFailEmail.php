<?php

namespace App\Mail;

use App\Consts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BitmexMappingFailEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $email;
    protected $id;
    protected $msg;

    public function __construct($email, $id, $msg)
    {
        $this->queue = Consts::QUEUE_NORMAL_MAIL;
        $this->email = $email;
        $this->id = $id;
        $this->msg = $msg;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $date = (string)Carbon::now('UTC');
        $subject = '【Exchange】Covered Order Notice: Order cannot covered on Bitmex'. ' - [' . $date . '] (UTC)' ;
        ;

        return $this->view('emails.bitmex_mapping_fail')
                    ->subject($subject)
                    ->to($this->email)
                    ->with([
                        'email' => $this->email,
                        'id'  => $this->id,
                        'msg' => $this->msg,
                    ]);
    }
}
