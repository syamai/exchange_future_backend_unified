<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\User;
use App\Consts;
use App\Http\Services\ReferrerService;
use App\Models\MultiReferrerDetails;

class UpdateReferrerDetail implements ShouldQueue
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
            logger()->info("UPDATE REFERRER DETAIL =========" . json_encode($this->userId));
            $newRecord = MultiReferrerDetails::where('user_id', $this->userId)->first();
            if ($newRecord) {
                return $newRecord;
            }
            $referrerIdLv1 = $this->getReferrerId($this->userId);
            logger()->info("referrerIdLv1 ========= " . json_encode($referrerIdLv1));
            if ($referrerIdLv1) {
                $newRecord = $this->createReferrerDetailRecord($this->userId, $referrerIdLv1);
                $this->updateReferrerNumber($newRecord);
            } else {
                MultiReferrerDetails::create([
                    'user_id' => $this->userId
                ]);
            }
            DB::commit();
            return $newRecord;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createReferrerDetailRecord($userId, $referrerIdLv1)
    {
        $this->data = [
            'user_id' => $userId,
            'referrer_id_lv_1' => $referrerIdLv1
        ];
        $levelNeedFind = 2;
        $this->findRemainReferrerId($referrerIdLv1, $levelNeedFind, $this->data);
        return MultiReferrerDetails::create($this->data);
    }

    public function findRemainReferrerId($userId, $levelNeedFind, $data)
    {
        if ($levelNeedFind <= Consts::NUMBER_OF_LEVELS) {
            $field = 'referrer_id_lv_' . $levelNeedFind;
            $record = MultiReferrerDetails::where('user_id', $userId)->first();
            if ($record) {
                $userIdInNextLevel = $record->referrer_id_lv_1;
                $data[$field] = $userIdInNextLevel;
                $this->data = $data;
                $levelNeedFind = $levelNeedFind + 1;
                $this->findRemainReferrerId($userIdInNextLevel, $levelNeedFind, $this->data);
            }
        }
    }

    public function getReferrerId($id)
    {
        logger()->info("getReferrerId id ========== " . json_encode($id));
        $user = User::where('id', $id)->first();
        logger()->info("getReferrerId user ========== " . json_encode($user));

        return $user->referrer_id;
    }

    public function updateReferrerNumber($record)
    {
        for ($i = 1; $i < 6; $i++) {
            $field = 'referrer_id_lv_' . $i;
            for ($j = $i; $j < 6; $j++) {
                $increaseAt = 'number_of_referrer_lv_' . $j;
                MultiReferrerDetails::where('user_id', $record->$field)->increment($increaseAt, 1);
            }
        }
    }
}
