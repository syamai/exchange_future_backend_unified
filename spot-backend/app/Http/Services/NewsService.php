<?php

namespace App\Http\Services;

use App\Models\News;
use App\Consts;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NewsService
{
    const NEWS_CACHE_LIVE_TIME = 600; // 10 minutes
    const NEWS_COUNT_CACHE_KEY = "News:count";
    const NEWS_INFO_CACHE_KEY = "News:info";

    /**
     * NewsService constructor.
     */
    public function __construct()
    {
    }

    public function getNews($params)
    {
        $locale = @$params['locale'] ?? 'en-us';
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return News::orderBy('created_at', 'desc')->where('locale', $locale)->paginate($limit);
    }

    public function getCountUnRead($params): int
    {
        $locale = @$params['locale'] ?? 'en-us';
        $news = News::query();

        $userId = $params['userId'];
        if ($userId) {
            $news = $news->where('locale', $locale)->whereHas('users', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('is_read', Consts::NEWS_UNREAD);
            });
        } else {
            $news = $news->where('locale', $locale);
        }
        $news = $news->count();

        return $news;
    }

    public function changeNewsStatus($news, $userId, $status)
    {
        $news->users()->syncWithoutDetaching([
            $userId => ['is_read' => $status]
        ]);

        return $news;
    }

    /**
     * ======================================
     * User News Info Functions
     * ======================================
     */
    public function getUserNewsInfo($params): array
    {
        $locale = @$params['locale'] ?? 'en-us';
        $userId = @$params['userId'];

        // TODO: Try Load by Cache


        $totalNewsCount = $this->getNewsCount($locale);
        $newsReaded = [];
        $newsReadedCount = 0;
        if ($userId) {
            $newsReaded = DB::table('news_user')->select('news_id')
                            ->join('news', 'news.id', '=', 'news_user.news_id')
                            ->where('locale', $locale)
                            ->where('user_id', $userId)
                            ->where('is_read', Consts::NEWS_READ)
                            ->get()->pluck('news_id');
            $newsReadedCount = count($newsReaded);
        }

        $result = [
            'total_news_count' => $totalNewsCount,
            'count_read' => $newsReadedCount,
            'count_unread' => ($totalNewsCount - $newsReadedCount),
            'news_read_ids' => $newsReaded,
        ];

        return $result;
    }


    /**
     * ======================================
     * News Count Functions
     * ======================================
     */
    public function getNewsCount($locale, $loadCache = true)
    {
        $result = $loadCache ? $this->loadCacheCountNews($locale, $loadCache) : null;
        if (!$result) {
            $result = News::where('locale', $locale)->count();
            $this->saveCacheCountNews($locale, $result);
        }

        return $result;
    }

    public function getNewsCountCacheKey($locale): string
    {
        return static::NEWS_COUNT_CACHE_KEY.':'.$locale;
    }

    public function saveCacheCountNews($locale, $result): void
    {
        $key = $this->getNewsCountCacheKey($locale);
        Cache::put($key, $result, static::NEWS_CACHE_LIVE_TIME);
    }

    public function loadCacheCountNews($locale, $loadCache = true)
    {
        $key = $this->getNewsCountCacheKey($locale);

        return Cache::get($key);
    }
}
