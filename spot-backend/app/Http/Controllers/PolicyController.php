<?php

namespace App\Http\Controllers;

use App\Http\Services\MasterdataService;
use Illuminate\Http\Request;
use App\Utils;

class PolicyController extends Controller
{
    /**
     * Show the application dashboard.
     *
     */
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $userLocale = Utils::setLocale($request);
        $dataVersion = MasterdataService::getDataVersion();
        return view('policy')->with('dataVersion', $dataVersion)->with('userLocale', $userLocale);
    }
}
