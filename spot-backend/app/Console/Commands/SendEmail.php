<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send {to} {subject} {content}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $address = $this->argument('to');
        $subject = $this->argument('subject');
        $content = $this->argument('content');

        Mail::send([], [], function ($message) use ($address, $subject, $content) {
            $message->to($address)
                ->subject($subject)
                ->html($content);
        });
    }
}
