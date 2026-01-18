<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Client;
use App\Http\Controllers\AppBaseController;
use App\Models\News;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use \Firebase\JWT\JWT;
use Carbon\Carbon;
use App\Consts;

class ZendeskAPIController extends AppBaseController
{
    const TIME_CACHE = 15;
    public function getArticles($categoryID)
    {
        $categoryID = escapse_string($categoryID);
        $client = new Client();
        $domainZendesk = Consts::DOMAIN_SUPPORT;
        $locales = ["en-us","ja","kr","zh-tw"];
        $BASE_URL = "{$domainZendesk}/api/v2/help_center/";
        $key = 'article' . $categoryID;
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        DB::beginTransaction();
        try {
            foreach ($locales as $locale) {
                $url = $BASE_URL .$locale . '/sections/' . $categoryID . '/articles.json';
                $res = $client->request('GET', $url);
                $articles = json_decode($res->getBody(), true)["articles"];
                foreach ($articles as $article) {
                    $isExist = News::where('article_id', $article["id"])->where('locale', $article["locale"])->value('id');
                    if ($isExist) {
                        continue;
                    }
                    $data = [
                        "article_id" => $article["id"],
                        "title" => $article["title"],
                        "url" => $article["html_url"],
                        "locale" => $article["locale"],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                    News::create($data)->save();
                    DB::commit();
                }
            }
            return ;
        } catch (ConnectException $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
    private function redirectWithoutLogin(Request $request): RedirectResponse
    {
        $location = $request->has('return_to') ? $request->return_to : Consts::DOMAIN_SUPPORT;
        return redirect()->to($location);
    }
    public function getSupportLogin(Request $request): JsonResponse|RedirectResponse
    {
        $key        = config('app.zendesk_key');
        if (empty($key)) {
            return $this->redirectWithoutLogin($request);
        }
        $now        = time();
        $token      = array(
            'jti'   => md5($now . rand()),
            'iat'   => $now,
            'name'  => $request->get('email'),
            'email' => $request->get('email'),
        );
        $domain = Consts::DOMAIN_SUPPORT;
        $jwt = JWT::encode($token, $key, Consts::DEFAULT_JWT_ALGORITHM);
        $location = "{$domain}/access/jwt?jwt={$jwt}";
        return $this->sendResponse($location);
    }
}
