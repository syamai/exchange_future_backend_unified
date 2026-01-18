<?php

namespace Database\Factories;

use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition()
    {
        return [
            'creator_id' => rand(1, 100),
            'owner_id' => rand(1, 100),
            'symbol' => 'XBTUSD',
            'currency' => 'XBt',
            'underlying' => 'XBt',
            'quote_currency' => 'XBT',
            'unrealised_pnl' => '-500',
            'unrealised_cost' => '0.01',
            'risk_limit' => '200',
            'leverage' => rand(1, 100),
            'opening_qty' => rand(1, 1000),
            'opening_cost' => rand(1, 1000),
            'current_qty' => rand(1, 1000),
            'current_cost' => rand(1, 1000),
            'realised_cost' => rand(1, 1000),
            'init_margin' => rand(1, 1000),
            'maint_margin' => rand(1, 1000),
            'margin_call_price' => rand(1, 1000),
            'liquidation_price' => rand(1, 10000),
            'bankrupt_price' => rand(1, 1000),
            'commission' => rand(1, 1000),
            'avg_cost_price' => rand(1, 1000),
            'mark_price' => rand(1, 12000),
            'mark_value' => rand(1, 1000),
            'entry_price' => rand(1, 10000),
            'entry_value' => rand(1, 1000),
            'multiplier' => -1,
            'liquidation_progress' => 0,
            'created_at' => Carbon::now(),
//      'risk_value' => rand(1,1000),
//      'avg_entry_price' => rand(1,1000),
//      'settle_value' => rand(1,1000),
//      'close_by' => rand(1,1000),
//      'is_closed' => false
//      'realised_pnl' => rand(1,1000),
//      'opening_timestamp' => Carbon::now(),
        ];
    }
}
