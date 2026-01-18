<?php

namespace App\Console\Commands;

use App\Http\Services\ReferralService;
use App\Models\AffiliateTrees;
use App\Models\ReportDailyReferralClient;
use App\Models\User;
use App\Models\UserRates;
use App\Models\UserSamsubKyc;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateReportDailyReferralClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referral:client-report-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report daily referral client';

    /**
     * Execute the console command.
     *
     * @return int
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
        Log::channel('user_rates_referral_client')->info("START- {$this->description} =====================");
        AffiliateTrees::where('level', 1)
        ->select('referrer_id')
        ->distinct()
        ->orderBy('referrer_id')
        ->chunk($chunkSize, function ($referrerRows) {
            $reported_at = Carbon::now()->subDay()->startOfDay()->timestamp * 1000;
            $start = Carbon::createFromTimestampMs($reported_at);
            $end = $start->copy()->endOfDay();
            // dd($start, $end);
            foreach ($referrerRows as $row) {
                $referrerId = $row->referrer_id;
                try {
                    // refferals 
                    $userIds = AffiliateTrees::where('referrer_id', $referrerId)
                        ->where('level', 1)
                        ->where('created_at','<=', $end)
                        ->pluck('user_id')
                        ->unique()
                        ->values()
                        ->all();

                    if (empty($userIds)) {
                        continue;
                    }

                    // refferals - KYC
                    $usersKYC = UserSamsubKyc::whereIn('user_id', $userIds)
                        ->where('status', UserSamsubKyc::VERIFIED_STATUS)
                        ->pluck('user_id')
                        ->unique()
                        ->values()
                        ->all();

                    // refferals - Trade pass
                    $users_pass_trade = $this->referralService->userReferralPassTrade($userIds, $referrerId);

                    //refferals - Commission data
                    $data = $this->referralService->reportReferralCommissionRanking('SUBDAILY', $userIds, $referrerId);

                    // Referrer info
                    $owner = User::find($referrerId);
                    $ownerRate = UserRates::find($referrerId);

                    if (!$owner) {
                        continue;
                    }

                    ReportDailyReferralClient::create([
                        'uid' => $owner->uid,
                        'user_id' => $owner->id,
                        'referral_client_referrer_total' => count($userIds),
                        'referral_client_referrer_pass_trade_in' => $users_pass_trade,
                        'referral_client_registration_at' => $owner->registered_at ? Carbon::parse($owner->registered_at)->timestamp * 1000 : null,
                        'referral_client_rate' => $ownerRate->referral_client_rate ?? 0,
                        'referral_client_trade_volume_value' => $data['volume'] ?? 0,
                        'referral_client_commission_value' => $data['commission'] ?? 0,
                        'referral_client_tier' => $ownerRate->referral_client_level ?? 0,
                        'reported_at' => $reported_at,
                        'created_at' => now()
                    ]);

                    Log::channel('user_rates_referral_client')->info("[referral:client-report-daily]", [
                        'referrerId' => $referrerId,
                        'referrals' => $userIds,
                        'referralsKYC' => $usersKYC,
                        'data' => $data,
                        'reported_at' => Carbon::createFromTimestampMs($reported_at)->toDateString()
                    ]);
                } catch (\Throwable $e) {
                    Log::channel('user_rates_referral_client')->error('Error in [referral:client-report-daily] job', [
                        'time' => Carbon::now()->toDateTimeString(),
                        'referrerId' => $referrerId,
                        'message' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ]);
                }
            }
        });

        Log::channel('user_rates_referral_client')->info("END=====================");

        $this->info("{$logTime} END-[{$this->signature}] {$this->description} =====================");
    }
}
