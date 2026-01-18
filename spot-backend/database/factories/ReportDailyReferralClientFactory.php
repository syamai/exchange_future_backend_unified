<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use App\Models\ReportDailyReferralClient;

class ReportDailyReferralClientFactory extends Factory
{
    protected $model = ReportDailyReferralClient::class;

    public function definition()
    {
        $userId = $this->faker->unique()->numberBetween(1000, 9999);
        $uid = $this->faker->unique()->randomNumber(8);
        $registeredAt = Carbon::now()->subMonths(rand(1, 12))->timestamp * 1000;

        // Sinh thời gian daily trong tuần trước
        $reportedAt = Carbon::now()
            ->subWeek()
            ->startOfWeek()
            ->addDays(rand(0, 6))
            ->setTime(rand(0, 23), rand(0, 59))
            ->timestamp * 1000;

        return [
            'user_id' => $userId,
            'uid' => $uid,
            'referral_client_referrer_total' => rand(0, 20),
            'referral_client_registration_at' => $registeredAt,
            'referral_client_rate' => $this->faker->randomFloat(4, 0, 0.05),
            'referral_client_trade_volume_value' => $this->faker->randomFloat(10, 1000, 100000),
            'referral_client_commission_value' => $this->faker->randomFloat(10, 0, 500),
            'referral_client_tier' => rand(0, 3),
            'reported_at' => $reportedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
