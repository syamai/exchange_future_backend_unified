<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Utils;

class NoticeAPIController extends AppBaseController
{
    public function getBannerNotices(): JsonResponse
    {
        $timeNow = Utils::currentMilliseconds();
        $notices = Notice::where([
            ['started_at', '<=', $timeNow],
            ['ended_at', '>=', $timeNow]
        ])
            ->orderBy('started_at', 'desc')
            ->take(4)
            ->get();
        return $this->sendResponse($notices);
    }
}
