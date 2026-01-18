<?php

namespace App\Jobs;

use App\Models\AffiliateTrees;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DB;
use Exception;
use Log;

class UpdateAffiliateTrees implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $data = [];
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
        DB::beginTransaction();
        try {
            $user = User::find($this->userId);
            if ($user->referrer_id) {//add AffiliateTrees when user has referrer_id
                AffiliateTrees::where('user_id', $this->userId)->delete();
                
                $referrers = AffiliateTrees::where('user_id', $user->referrer_id)
                    ->orderBy('level', 'asc')
                    ->get();

                AffiliateTrees::create([
                    'user_id' => $this->userId,
                    'referrer_id' => $user->referrer_id,
                    'level' => 1
                ]);
                foreach ($referrers as $referrer) {
                    AffiliateTrees::create([
                        'user_id' => $this->userId,
                        'referrer_id' => $referrer->referrer_id,
                        'level' => $referrer->level + 1
                    ]);
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
    }
}
