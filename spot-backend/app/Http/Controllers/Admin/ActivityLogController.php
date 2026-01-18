<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\ActivityLog;
use App\Models\NewsNotification;
use App\Models\NewsNotificationCategory;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ActivityLogController extends AppBaseController
{

    public function index(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 10);
        $data = ActivityLog::with(['object', 'admin:id,name,email,role'])
            ->filter($input)
            ->when(!empty($request['sort']) && !empty($request['sort_type']),
                function ($query) use ($request) {
                    $query->orderBy($request['sort'], $request['sort_type']);
                },
                function ($query) use ($request) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->paginate($limit);

        return $this->sendResponse($data);
    }

}
