<?php

namespace App\Http\Controllers;

use App\Http\Services\MasterdataService;
use Illuminate\Http\Request;
use App\Utils;

class TermsController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): \Illuminate\Http\Response
    {
        $userLocale = Utils::setLocale($request);
        $dataVersion = MasterdataService::getDataVersion();
        return view('terms')->with('dataVersion', $dataVersion)->with('userLocale', $userLocale);
    }
}
