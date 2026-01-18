<?php

namespace App\Http\Controllers\PartnerAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function getProfile(Request $request) {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }
}
