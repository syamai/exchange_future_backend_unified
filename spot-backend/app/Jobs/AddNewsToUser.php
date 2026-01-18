<?php

namespace App\Jobs;

use App\Consts;
use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AddNewsToUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
//        $news = News::all();
//        foreach ($news as $newsRecord) {
//            $newsRecord->users()->syncWithoutDetaching([
//                $this->userId => ['is_read' => Consts::NEWS_UNREAD],
//            ]);
//        }
    }
}
