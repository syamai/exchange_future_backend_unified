<?php

namespace App\Console\Commands;

use App\Http\Services\NewsService;
use GuzzleHttp\Client;
use App\Models\News;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Consts;
use App\Events\ZendeskNewsUpdated;

use Illuminate\Console\Command;

class GetNewsZendesk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_news_zendesk:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get News from Zendesk to save database';



    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {
        $server_support = config('app.env');
        if ($server_support == 'production') {
            $categoryID = Consts::ANNOUCEMENT_ID;
            $domainZendesk = Consts::DOMAIN_SUPPORT;
        } else {
            $categoryID = Consts::ANNOUCEMENT_ID_TEST;
            $domainZendesk = Consts::DOMAIN_SUPPORT_TEST;
        }

        $client = new Client();

        $locales = ["en-us","ja","kr","zh-tw"];
        $BASE_URL = "{$domainZendesk}/api/v2/help_center/";

        DB::beginTransaction();

        try {
            foreach ($locales as $locale) {
                $url = $BASE_URL .$locale . '/sections/' . $categoryID . '/articles.json';
                $res = $client->request('GET', $url);
                $articles = json_decode($res->getBody(), true)["articles"];
                foreach ($articles as $article) {
                    $isExist = News::where('article_id', $article["id"])->where('locale', $article["locale"])->first();
                    if (count($isExist)) {
                        if ($isExist->title != $article["title"]) {
                            $isExist->title = $article["title"];
                            $isExist->save();
                        }
                        continue;
                    };
                    $data = [
                        "article_id" => $article["id"],
                        "title" => $article["title"],
                        "url" => $article["html_url"],
                        "locale" => $article["locale"],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];

                    $newsCreated = News::create($data);

                    if ($newsCreated) {
                        event(new ZendeskNewsUpdated($data));
                    }
                }
            }
            DB::commit();
            $newService = new NewsService;
            $locales = ['en', 'en-us', 'ja', 'ko', 'zh'];
            foreach ($locales as $item) {
                Cache::forget($newService->getNewsCountCacheKey($item));
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function sendError($error)
    {
        return [
            'success' => false,
            'message' => $error,
        ];
    }
}
