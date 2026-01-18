<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\Chatbot;
use App\Models\ChatbotCategory;
use App\Models\ChatbotType;
use Illuminate\Http\Request;

class ChatbotController extends AppBaseController
{
    public function getTypes(Request $request)
    {
        $data = ChatbotType::select(['id', 'name'])
            ->orderBy('updated_at', 'desc')
            ->get();


        return $this->sendResponse($data);
    }

    public function getCategories(Request $request)
    {
        $input = $request->all();
        $data = ChatbotCategory::with(['subCategories'])
            ->filter($input)
            ->where('status', Consts::ENABLE_STATUS)
            ->orderBy('updated_at', 'desc')
            ->get();


        return $this->sendResponse($data);
    }

    public function getData(Request $request)
    {
        $input = $request->all();

        $data = Chatbot::filter($input)
            ->where(['status' => Consts::ENABLE_STATUS])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse($data);
    }
}
