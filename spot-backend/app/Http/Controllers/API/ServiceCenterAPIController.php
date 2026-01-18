<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\ContactRequest;
use App\Http\Services\NoticeService;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use stdClass;
use App\Notifications\ContactNotification;

class ServiceCenterAPIController extends AppBaseController
{
    private $noticeService;

    public function __construct()
    {
        $this->noticeService = new NoticeService();
    }

    public function sendContact(ContactRequest $request)
    {
        $user = $request->user();
        $user->notify(new ContactNotification($request));
        return $this->sendResponse("", __('messages.send_contact_success'));
    }

    public function getNotices(Request $request)
    {
        $keyword = $request->input('key', '');
        $limit = $request->input('limit', Consts::DEFAULT_PER_PAGE);
        $data = $this->noticeService->getNotices($keyword, $limit);
        return $this->sendResponse($data);
    }

    public function getNotice($id)
    {
        $data = $this->noticeService->getNotice($id);
        return $this->sendResponse($data);
    }

    public function createNotice(Request $request)
    {
        $params = new stdClass();
        $params->title = $request->input('title', '');
        $params->content = $request->input('content', '');

        $data = $this->noticeService->createNotice($params);
        return $this->sendResponse($data);
    }

    public function updateNotice(Request $request, $id)
    {
        $data = Notice::where('id', $id)->update($request->all());
        return $this->sendResponse($data);
    }

    public function removeNotice(Request $request, $id)
    {
        $data = Notice::where('id', $id)->delete();
        return $this->sendResponse($data);
    }
}
