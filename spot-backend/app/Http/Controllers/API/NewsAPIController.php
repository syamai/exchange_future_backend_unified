<?php

namespace App\Http\Controllers\API;

use App\Events\NewsStateUpdated;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\NewsService;
use App\Models\News;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NewsAPIController extends AppBaseController
{
    private $newsService;

    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;

        // Check has token to apply middleware
        $token = request()->headers->get('Authorization', '');
        if (str_contains($token, 'Bearer')) {
            $this->middleware('auth:api');
        }
    }

    public function getNews(Request $request)
    {
        $params = $request->all();
        $params['userId'] = $request->user() ? $request->user()->id : null;
        $data = $this->newsService->getNews($params);
        return $this->sendResponse($data);
    }

    public function getUserNewsInfo(Request $request)
    {
        $params = $request->all();
        $params['userId'] = $request->user() ? $request->user()->id : null;
        $data = $this->newsService->getUserNewsInfo($params);

        return $this->sendResponse($data);
    }

    public function getCountUnRead(Request $request)
    {
        $params = $request->all();
        $params['userId'] = $request->user() ? $request->user()->id : null;
        $data = $this->newsService->getCountUnRead($params);

        return $this->sendResponse($data);
    }

    public function changeNewsStatus(Request $request, $newsId, $status)
    {
        $userId = $request->user()->id;
        $news = News::with(['users' => function ($q) use ($userId, $newsId) {
            $q->select('id')->where('user_id', $userId)->where('news_id', $newsId);
        }])->find($newsId);

        if (!$news) {
            throw new HttpException(422, __('exception.not_found'));
        }

        if ($userId) {
            $news = $this->newsService->changeNewsStatus($news, $userId, $status);
            event(new NewsStateUpdated($news, $userId, $status));
        }

        return $this->sendResponse($news);
    }
}
