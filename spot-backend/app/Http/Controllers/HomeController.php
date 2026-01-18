<?php

namespace App\Http\Controllers;

use App\Consts;
use App\Models\User;
use App\Utils;
use App\Http\Services\MasterdataService;
use App\Http\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
//        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('layouts.app');
    }

    public function getWebview(Request $request)
    {
        return view('layouts.webview');
    }

    public function showNotFoundPage()
    {
        return view('errors.404');
    }

    public function authUrl(Request $request)
    {
        $url = $request->input('url');
        return redirect($url);
    }
}
