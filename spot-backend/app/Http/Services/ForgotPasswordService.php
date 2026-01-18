<?php

namespace App\Http\Services;

use App\Mail\ForgotPassword;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ForgotPasswordService
{
    public function getResetPasswordByToken($token)
    {
        return DB::table('password_resets')->where('token', $token)->first();
    }

    public function getResetPasswordByTokenAndEmail($token, $email)
    {
        return DB::table('password_resets')->where('token', $token)->where('email', $email)->first();
    }

    public function save($email, $token)
    {
        $currentTime = Carbon::now();

        return DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => $currentTime,
        ]);
    }

    public function deleteByToken($token)
    {
        return DB::table('password_resets')->where('token', '=', $token)->delete();
    }

    public function deleteByEmail($email)
    {
        return DB::table('password_resets')->where('email', '=', $email)->delete();
    }

    public function saveAndSendLinkToEmail($email, $user)
    {

        $token = $this->createTokenSha256($email);
        $save = $this->save($email, $token);
        $user->notify(new ResetPassword($token));
//        Mail::queue(new ForgotPassword($email, $token));
    }

    public function createTokenSha256($email)
    {
        $uid = uniqid(Str::random(60), true);

        $timeMillisecond = microtime(true);

        $currentTime = Carbon::now();

        $timeStringCurrent = $currentTime->format('YmdHisAT');

        $tokenString = $email . $timeStringCurrent . $timeMillisecond . $uid;

        return hash('sha256', $tokenString);
    }

    public function validateExpiredLink($createdTime)
    {

        $isExpired = false;

        $currentTime = Carbon::now();

        $timeSetPassword = $currentTime->diffInSeconds($createdTime);

        if ($timeSetPassword > 86400) {
            return !$isExpired;
        }
        return $isExpired;
    }

    public function validateExpiredForm($currentTime, $createdTIme)
    {
        $isExpired = false;

        $timeSetPassword = $currentTime->diffInSeconds($createdTIme);

        if ($timeSetPassword > 86400) {
            return !$isExpired;
        }
        return $isExpired;
    }

    public function expiredToken($token)
    {
        $expiredTime = Carbon::now()->subDays(1)->format('Y-m-d H:i:s');

        return DB::table('password_resets')->where('token', $token)->update(['created_at' => $expiredTime]);
    }
}
