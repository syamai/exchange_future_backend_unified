<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;

class BlogController extends AppBaseController
{

    public function getCategories(Request $request)
    {
        $input = $request->all();
        $data = BlogCategory::filter($input)
            ->where('status', Consts::ENABLE_STATUS)
            ->orderBy('updated_at', 'desc')
            ->get();


        return $this->sendResponse($data);
    }

    public function getPins(Request $request)
    {
        $input = $request->all();

        $data = Blog::with('category:id,title_en,title_vi')->filter($input)
            ->where(['status' => Consts::STATUS_POSTED, 'is_pin' => true])
            ->orderBy('updated_at', 'desc')
            ->get();

        //$data->setCollection($this->getDataList($data));
        $data = $this->getDataList($data);

        return $this->sendResponse($data);
    }

    public function getData(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = Blog::with('category:id,title_en,title_vi')->filter($input)
            ->where(['status' => Consts::STATUS_POSTED])
            ->orderBy('updated_at', 'desc')
            ->paginate($limit);

        $data->setCollection($this->getDataList($data));

        return $this->sendResponse($data);
    }

    private function getDataList($data) {
        return $data->map(function ($item) {
            return [
                'id' => $item->id,
                'static_url' => $item->static_url,
                'thumbnail' => $item->thumbnail_full_url,
                'title_en' => $item->title_en,
                'seo_title_en' => $item->seo_title_en,
                'meta_keywords_en' => $item->meta_keywords_en,
                'seo_description_en' => $item->seo_description_en,
                'title_vi' => $item->title_vi,
                'seo_title_vi' => $item->seo_title_vi,
                'meta_keywords_vi' => $item->meta_keywords_vi,
                'seo_description_vi' => $item->seo_description_vi,
                'category' => $item->category,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });
    }

    public function getDetail($id, Request $request) {
        $object = Blog::with('category:id,title_en,title_vi')->find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

		return $this->getDetailInfo($object);
	}

	public function getDetailSlug($slug) {
		$object = Blog::with('category:id,title_en,title_vi')->where('static_url', $slug)->first();
		if (!$object) {
			return $this->sendError(__('exception.not_found'));
		}

		return $this->getDetailInfo($object);
	}

    public function getRelated($id)
    {
        $object = Blog::find($id);
        if (!$object) {
            return $this->sendError(__('exception.not_found'));
        }

        $limitRelated = env('BLOG_MAX_RELATED', 3);
        $data = Blog::with('category:id,title_en,title_vi')
            ->where([
                'status' => Consts::STATUS_POSTED,
                'cat_id' => $object->cat_id
            ])
            ->where('id', '!=', $object->id)
            ->orderBy('updated_at', 'desc')
            ->limit($limitRelated)
            ->get();

        return $this->sendResponse($this->getDataList($data));
    }


	private function getDetailInfo($object)
	{
		return $this->sendResponse([
			'id' => $object->id,
			'static_url' => $object->static_url,
			'thumbnail' => $object->thumbnail_full_url,
			'title_en' => $object->title_en,
			'seo_title_en' => $object->seo_title_en,
			'meta_keywords_en' => $object->meta_keywords_en,
			'seo_description_en' => $object->seo_description_en,
			'content_en' => $object->content_en,
			'title_vi' => $object->title_vi,
			'seo_title_vi' => $object->seo_title_vi,
			'meta_keywords_vi' => $object->meta_keywords_vi,
			'seo_description_vi' => $object->seo_description_vi,
			'content_vi' => $object->content_vi,
			'is_pin' => $object->is_pin,
			'category' => $object->category,
			'created_at' => $object->created_at,
			'updated_at' => $object->updated_at,
		]);
	}
}
