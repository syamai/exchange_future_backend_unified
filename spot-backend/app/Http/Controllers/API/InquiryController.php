<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\InquiryService;
use App\Models\Inquiry;
use App\Models\InquiryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;

class InquiryController extends AppBaseController
{
    private InquiryService $inquiryService;

    public function __construct(InquiryService $inquiryService)
    {
        $this->inquiryService = $inquiryService;
    }

    public function getInquiries(Request $request)
    {
        try {
            $userId = $request->user() ? $request->user()->id : null;
            if (!$userId) {
                return $this->sendError( __('exception.user_not_found'));
            }
            $params = $request->all();
            $data = $this->inquiryService->getInquiries($params, $userId);


            return $this->sendResponse($data);
        } catch (Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function getInquiry(Request $request, $id)
    {
        try {
            $userId = $request->user()->id;
            $inquiry = Inquiry::with(['reply:id,name', 'inquiryType:id,name'])
                ->where([
                    'user_id' => $userId,
                    'id' => $id
                ])
                ->first();
            if (!$inquiry) {
                return $this->sendError( __('exception.not_found'));
            }

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

    public function insertInquiries(Request $request)
    {
        try {
            $inputs = $request->only(['type_id', 'title', 'question']);
            $inputs['user_id'] = $request->user()->id;
            $inputs['status'] = Consts::INQUIRY_STATUS_PENDING;

            $validator = Validator::make($inputs, [
                'type_id' => 'required|integer|exists:inquiry_types,id',
                'title' => 'required|string',
                'question' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError(['errors' => $validator->errors()]);
            }

            $inquiry = Inquiry::create($inputs);

            return $this->sendResponse($inquiry);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }
}
