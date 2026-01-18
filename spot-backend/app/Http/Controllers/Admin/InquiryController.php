<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;

class InquiryController extends AppBaseController
{
    public function getInquiries(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $email = $request->email ?? '';
        $data = Inquiry::userWithWhereHas($email)
            ->with(['inquiryType:id,name', 'reply:id,name'])
            ->filter($input)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return $this->sendResponse($data);
    }

    public function getInquiry(Request $request, $id)
    {
        $inquiry = Inquiry::with(['reply:id,name', 'inquiryType:id,name'])
            ->where([
                'id' => $id
            ])
            ->first();
        if (!$inquiry) {
            return $this->sendError(__('exception.not_found'));
        }
        return $this->sendResponse($inquiry);
    }

    public function updateInquiry(Request $request, $id)
    {
        try {
            $replyId = $request->user()->id;
            $inputs = $request->only(['answer']);
            $validator = Validator::make($inputs, [
                'answer' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError(['errors' => $validator->errors()]);
            }
            $inquiry = Inquiry::find($id);
            if (!$inquiry) {
                return $this->sendError(__('exception.not_found'));
            }

            if ($inquiry->status != Consts::INQUIRY_STATUS_PENDING) {
                return $this->sendError(__('Inquiry has been processed'));
            }

            $inquiry->update([
                'answer' => $request->answer ?? '',
                'reply_id' => $replyId,
                'reply_at' => Carbon::now(),
                'status' => Consts::INQUIRY_STATUS_REPLIED
            ]);

            return $this->sendResponse($inquiry);
        } catch (Exception $exception) {
            return $this->sendError($exception->getMessage());

        }

    }

    public function getInquiryType()
    {
        $inquiryType = InquiryType::all();
        return $this->sendResponse($inquiryType);
    }
}
