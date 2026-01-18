<?php

namespace App\Http\Services;

use App\Models\Leaderboard;
use App\Models\Settings;

class LeaderboardService
{
    public function getTopTradingVolume($params)
    {
        $key = $this->getKeySort();
        $query = Leaderboard::join('users', 'trading_volume_ranking_total.user_id', '=', 'users.id')
        ->join('user_security_settings', 'user_security_settings.id', '=', 'trading_volume_ranking_total.user_id')
        ->where('user_security_settings.identity_verified', '=', 1)
        ->when(array_key_exists('coin', $params), function ($query) use ($params, $key) {
            return $query->where('trading_volume_ranking_total.coin', strtolower($params['coin']))->orderBy($key, 'desc');
        })
        ->when(array_key_exists('type', $params), function ($query) use ($params) {
            return $query->where('trading_volume_ranking_total.type', $params['type']);
        })
        ->select(
            'trading_volume_ranking_total.user_id',
            'trading_volume_ranking_total.email',
            'trading_volume_ranking_total.coin',
            'users.name',
            'users.fake_name',
            "trading_volume_ranking_total.{$key} as btc_volume",
            'user_security_settings.use_fake_name'
        )
        ->when(!empty($params['sort']), function ($query) use ($params) {
            $query->orderBy($params['sort'], $params['sort_type'] ?? 'desc');
        }, function ($query) use ($key) {
            $query->orderBy("btc_volume", 'desc')->limit(25);
        });

        $result = $query->get();
        foreach ($result as $item) {
            if ($item->use_fake_name === 1) {
                $item->name = $item->fake_name;
            } else {
                $item->name = $item->email;
            }
        }

        return $result;
    }

    public function getKeySort()
    {
        $key = 'btc_volume';
        $value = Settings::where('key', 'self_trading_volume')->first();
        if (!$value) {
            return $key;
        }
        if ($value->value == 0) {
            $key = 'trading_volume';
        }
        return $key;
    }
}
