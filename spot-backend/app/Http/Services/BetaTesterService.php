<?php

namespace App\Http\Services;

use App\Consts;
use App\Events\BetaTesterStatusChanged;
use App\Models\EnableTradingSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BetaTesterService
{
    public function registerBetaTester($userId, $inputs): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }
        $tradingSetting = EnableTradingSetting::where('email', $user->email)
            ->where('coin', $inputs['coin'])
            ->where('currency', $inputs['currency'])
            ->first();

        if (!$tradingSetting) {
            $tradingSetting = EnableTradingSetting::create([
                'currency' => $inputs['currency'],
                'coin' => $inputs['coin'],
                'email' => $user->email,
                'enable_trading' => Consts::WAITING_TRADING,
            ]);
        } elseif ($tradingSetting->enable_trading == Consts::DISABLE_TRADING) {
            $tradingSetting->enable_trading = Consts::WAITING_TRADING;
        }

        $tradingSetting->save();
        $this->sendBetaTesterStatusChanged($user->id, $tradingSetting);
        return $tradingSetting;
    }

    public function ignoreBetaTester($userId, $inputs): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $tradingSetting = EnableTradingSetting::where('email', $user->email)
            ->where('coin', $inputs['coin'])
            ->where('currency', $inputs['currency'])
            ->first();

        if (!$tradingSetting) {
            $tradingSetting = EnableTradingSetting::create([
                'currency' => $inputs['currency'],
                'coin' => $inputs['coin'],
                'email' => $user->email,
                'enable_trading' => Consts::IGNORE_TRADING,
            ]);
        } elseif ($tradingSetting->enable_trading == Consts::DISABLE_TRADING) {
            $tradingSetting->enable_trading = Consts::IGNORE_TRADING;
        }

        $tradingSetting->ignore_expired_at = now()->addDays(7)->timestamp * 1000;

        $tradingSetting->save();
        $this->sendBetaTesterStatusChanged($user->id, $tradingSetting);
        return $tradingSetting;
    }

    public function sendBetaTesterStatusChanged($userId, $data)
    {
        event(new BetaTesterStatusChanged($userId, $data));
    }

    /**
     * @param $request
     * @param $inputs
     * @return mixed
     * @throws \Exception
     */
    public function register($request, $inputs): mixed
    {
        $userId = $request->user()->id;
        $ignoreTester = $request->get('ignore_tester');

        DB::connection('master')->beginTransaction();

        try {
            if ($ignoreTester) {
                $result = $this->ignoreBetaTester($userId, $inputs);
            } else {
                $result = $this->registerBetaTester($userId, $inputs);
            }
            DB::connection('master')->commit();

            return $result;
        } catch (\Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }
}
