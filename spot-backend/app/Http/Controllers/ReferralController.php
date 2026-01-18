<?php

namespace App\Http\Controllers;

use App\Exports\ReferralBuildExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Carbon\Carbon;
use App\Http\Services\UserService;
use App\Utils;
use Illuminate\Support\Facades\Auth;
use App\Http\Services\ReferralService;

class ReferralController extends AppBaseController
{
    private UserService $userService;
    private ReferralService $referralService;

    /**
     * ReferralController constructor.
     * @param ReferralService $referralService
     */
    public function __construct(ReferralService $referralService)
    {
        $this->userService = new UserService();
        $this->referralService = $referralService;
    }

    private function buildExcelFile(array $rows, string $fileName): bool
    {
        return ExcelFacade::store(new ReferralBuildExport($rows, $fileName), $this->getFilePath(), null, Excel::CSV);
    }

    public function exportToCSVReferralFriends(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $userReferralFriends = $this->userService->getAllReferrer(Auth::id());
        $rows = [];
        //Insert title column
        $rows[] = [__('Email'),  __('Date')];
        $timzoneOffset = $request->input('timezone_offset', Carbon::now()->offset);

        foreach ($userReferralFriends as $userReferralFriend) {
            $rows[] = array(
                $userReferralFriend->email,
                Carbon::parse($userReferralFriend->created_at)->subMinutes($timzoneOffset)
            );
        }
        $fileName = 'ReferralFriends_' . Utils::currentMilliseconds();
        return ExcelFacade::download(new ReferralBuildExport($rows, $fileName), $fileName, Excel::CSV);
    }

    public function exportCSVCommissionHistory(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $params = $request->all();
        $timzoneOffset = $request->input('timezone_offset', Carbon::now()->offset);
        $userReferralCommissions = $this->userService->getUserReferralCommission(Auth::id(), $params);
        $rows = [];
        //Insert title column
        $rows[] = [__('Commission'), __('Currency'), __('Rate'), __('Email'),  __('Date')];

        foreach ($userReferralCommissions as $userReferralCommission) {
            $rows[] = array(
                $userReferralCommission->amount,
                $userReferralCommission->coin,
                $userReferralCommission->commission_rate,
                $userReferralCommission->email,
                Carbon::parse($userReferralCommission->created_at)->subMinutes($timzoneOffset)
            );
        }

        $fileName = 'CommissionHistory_' . Utils::currentMilliseconds();
        return ExcelFacade::download(new ReferralBuildExport($rows, $fileName), $fileName, Excel::CSV);
    }

    public function getReferralSetting(): \Illuminate\Http\JsonResponse
    {
        try {
            $referralSetting = $this->referralService->getReferralSettings();
            return $this->sendResponse($referralSetting);
        } catch (\Exception $e) {
            Log::error($e);
            $this->sendError($e);
        }
    }
}
