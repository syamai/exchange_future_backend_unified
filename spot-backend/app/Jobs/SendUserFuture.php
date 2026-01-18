<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendUserFuture implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->data) {
                $url = env('FUTURE_API_URL', 'http://localhost:3000') . env('FUTURE_USER_SYNC', '');
                $res = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('FUTURE_SECRET_KEY', '')
                ])->post($url, $this->data);
                Log::info("SYNC USER: " . $res);
            }
        } catch (\Exception $e) {
            logger()->error("Send user". $this->data['id'] ." error:" . $e->getMessage());
            throw $e;
        }
    }
}
