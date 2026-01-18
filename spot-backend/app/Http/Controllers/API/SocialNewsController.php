<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;

use App\Models\NewsNotification;
use App\Models\SocialNew;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SocialNewsController extends AppBaseController
{

    public function getListSocialNews(Request $request)
    {

        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $socials = SocialNew::filter($input)
            ->where([
                'status' => Consts::NEWS_NOTIFICATION_STATUS_POSTED,
                'is_pin' => 0
            ])
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        $socials->setCollection($socials->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title_en,
                'content' => $item->content_en,
                'title_vi' => $item->title_vi,
                'content_vi' => $item->content_vi,
                'link_page' => $item->link_page,
                'domain_name' => $item->domain_name,
                'thumbnail_full_url' => $item->thumbnail_full_url,
                'thumbnail_url' => $item->thumbnail_url,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        }));

        return $this->sendResponse($socials);
    }

    public function getListPinSocialNews(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $socials = SocialNew::filter($input)
            ->where([
                'status' => Consts::NEWS_NOTIFICATION_STATUS_POSTED,
                'is_pin' => 1
            ])
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        $data = $socials->map(function ($item) {
            return [
                'id' => $item->id,
                'link_page' => $item->link_page,
                'domain_name' => $item->domain_name,
                'thumbnail_full_url' => $item->thumbnail_full_url,
                'thumbnail_url' => $item->thumbnail_url,
                'title' => $item->title_en,
                'content' => $item->content_en,
                'title_vi' => $item->title_vi,
                'content_vi' => $item->content_vi,
                'content_vi' => $item->content_vi,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return $this->sendResponse($data);
    }
}
