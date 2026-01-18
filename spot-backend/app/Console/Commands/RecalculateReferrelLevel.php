<?php

namespace App\Console\Commands;

use App\Http\Services\ReferralService;
use App\Models\AffiliateTrees;
use App\Models\ReferrerRecentActivitiesLog;
use App\Models\User;
use App\Models\UserRates;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateReferrelLevel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referral:set-level';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate level referral';

    /**
     * Execute the console command.
     *
     * @return int
     */
    /**
     * Class constructor.
     */
    private $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;

        parent::__construct();
    }
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================");

        $chunkSize = config('constants.chunk_limit_checkpoint');
        Log::channel('user_rates_referral_client')->info("{$logTime} START-[{$this->signature}] {$this->description} =====================");

        AffiliateTrees::where('level', 1)
            ->select('referrer_id')
            ->distinct()
            ->orderBy('referrer_id')
            ->chunk($chunkSize, function ($referrerRows) {
                $reported_at = Carbon::now()->subDay()->startOfDay()->timestamp * 1000;
                foreach ($referrerRows as $row) {
                    $referrerId = $row->referrer_id;
                    // refferals 
                    $userIds = AffiliateTrees::where('referrer_id', $referrerId)
                        ->where('level', 1)
                        ->pluck('user_id')
                        ->unique()
                        ->values()
                        ->all();

                    if (empty($userIds)) {
                        continue;
                    }
                    try {
                        $data = $this->referralService->referralLevel($userIds);
                        // Referrer info
                        $referrer = User::find($referrerId);
                        $userRate = UserRates::find($referrerId);
                        if (!$referrer) {
                            Log::channel('user_rates_referral_client')->warning('Referrer user not found', [
                                'referrerId' => $referrerId,
                            ]);
                            continue;
                        }

                        $wasCreated = !$userRate;
                        $currentLevel = $userRate->referral_client_level ?? 0;

                        $shouldLog = $wasCreated || $currentLevel != $data['level'];
                        if ($shouldLog) {
                            $data_log = [
                                'user_id' => $referrer->id,
                                'type'    => 'referral',
                                'target'  => $referrer->referrer_code,
                                'actor'   => 'role:admin',
                                'log_at'  =>  $reported_at,
                            ];

                            if ($wasCreated) {
                                $data_log['activities'] = config('constants.referrer_message.tier_created');
                                $data_log['details'] = "Initial level: {$data['label']}";
                            } elseif ($currentLevel < $data['level']) {
                                $data_log['activities'] = config('constants.referrer_message.tier_up');
                                $data_log['details'] = "{$data['label']} unlocked";
                            } else {
                                $data_log['activities'] = config('constants.referrer_message.tier_down');
                                $data_log['details'] = "{$data['label']} downgraded";
                            }

                            ReferrerRecentActivitiesLog::create($data_log);
                        }

                        // Upsert
                        UserRates::updateOrCreate(
                            ['id' => $referrerId],
                            [
                                'referral_client_level' => $data['level'],
                                'referral_client_rate'  => $data['rate'],
                                'referral_client_at'    => $reported_at,
                            ]
                        );


                        //Log info chi tiết
                        Log::channel('user_rates_referral_client')->info("Updated user rate", [
                            'referrerId' => $referrerId,
                            'userIds' => $userIds,
                            'data' => $data,
                        ]);
                    } catch (\Throwable $e) {
                        Log::channel('user_rates_referral_client')->error('Error in [referrel:set-level] job', [
                            'referrerId' => $referrerId,
                            'userIds' => $userIds,
                            'message' => $e->getMessage(),
                            'line' => $e->getLine(),
                            'file' => $e->getFile(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });
        Log::channel('user_rates_referral_client')->info("{$logTime} END-[{$this->signature}] {$this->description} ==========job completed===========\n");

        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ==========job completed===========");
    }
}
