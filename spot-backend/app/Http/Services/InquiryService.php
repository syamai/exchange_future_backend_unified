<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\Inquiry;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InquiryService
{
    public function getInquiries($params, $userId = null)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);


        return Inquiry::with(['reply:id,name', 'inquiryType:id,name'])
            ->when($userId, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

    }
}
