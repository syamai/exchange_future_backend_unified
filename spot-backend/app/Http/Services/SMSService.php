<?php

namespace App\Http\Services;

use Twilio\Rest\Client;

class SMSService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    }

    public function sendOTP($phone, $otp)
    {
        return $this->twilio->verify->v2->services(env("TWILIO_VERIFY_SERVICE_SID"))
            ->verifications->create($phone, 'sms',['CustomCode' => $otp]);
//        return $this->twilio->messages->create($phone, [
//            'from' => env('TWILIO_PHONE_NUMBER'),
//            'body' => config('app.name') . " OTP verify services: $otp"
//        ]);
    }
}