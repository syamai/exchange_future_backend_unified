<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\InquiryService;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Inquiry;
use App\Models\InquiryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class FaqController extends AppBaseController
{
    public function getCategories(Request $request)
    {
        $input = $request->all();
        $data = FaqCategory::with(['subCategories'])
            ->filter($input)
            ->where('status', 'enable')
            ->orderBy('created_at', 'desc')
            ->get();


        return $this->sendResponse($data);
    }

    public function getFaqs(Request $request)
    {
        $input = $request->all();
        /*$data = Faq::with(['category', 'subCategory'])
            ->filter($input)
            ->orderBy('created_at', 'desc')
            ->get();*/

        $data = Faq::filter($input)
            ->where(['status' => 'enable'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse($data);
    }
}
