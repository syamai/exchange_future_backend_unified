<?php

namespace App\Http\Controllers\Partner;

use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Http\Request;

class AuthController extends AccessTokenController
{
    public function login(ServerRequestInterface $request)
    {
        return parent::issueToken($request);
    }

    public function getProfile (Request $request) {
        $user = $request->user();
        $levelWithF0 = $user->affiliateTreeUsers->max('level');
        $data = [
            "canSetCommission" => true,
            "accountId" => $user->id,
            "affiliateProfileId" => $user->id,
            "email" => $user->email,
            "name" => $user->name,
            "inviteCode" => $user->referrer_code,
            "invitedById" => $user->referrer_id,
            "invitedByEmail" => $user->referrerUser->email ?? '',
            "isPartner" => true,
            "status" => $user->status,
            "rateCommission" => $user->userRate->commission_rate ?? 0,
            "rootRefAccountId" => $user->affiliateTreeUsers->where('level', $levelWithF0)->first()->referrer_id ?? $user->id,
            "levelWithF0" => $levelWithF0,
            "phoneNumber" => $user->phone_no,
            "id" => $user->id,
            "createdAt" => $user->created_at,
            "updatedAt" => $user->updated_at,
        ];
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $data
        ], 200);
    }
}
