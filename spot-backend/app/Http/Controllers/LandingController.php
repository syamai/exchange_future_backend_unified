<?php

namespace App\Http\Controllers;

use App\Http\Services\MasterdataService;
use Illuminate\Http\Request;
use App\Utils;
use Illuminate\Support\Facades\Log;

class LandingController extends Controller
{


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userLocale = Utils::setLocale($request);
        $dataVersion = MasterdataService::getDataVersion();
        return view('welcome')->with('dataVersion', $dataVersion)->with('userLocale', $userLocale);
    }

    public function getWhitePaper($lang = 'en')
    {
        try {
            // storage/app/public/white-paper/AMANPURI_WP20_en.pdf
            $fileName = "AMANPURI_WP20_{$lang}.pdf";
            $filePath = public_path('/white-paper-resources/' . $fileName);
            $headers = [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"'
            ];

            return response()->file($filePath, $headers);
        } catch (\Exception $e) {
            Log::error($e);
            abort(404);
        }
    }
}
