<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelescopeController extends Controller
{
    public function loginView() {
        if(Auth::guard('telescope')->check()) {return redirect()->intended('/telescope');}
        return view('auth.login');
    }

    public function login(Request $request) {
        $credentials = $request->only('email', 'password');

        if (Auth::guard('telescope')->attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended('/telescope');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }
}
