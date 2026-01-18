<?php

namespace App\Http\Services;

use App\Repositories\NoticeRepositories;
use App\Consts;
use App\Models\Notice;
use Illuminate\Support\Arr;

class NoticeService
{
    private $repository;
    private $userService;

    /**
     * NoticeService constructor.
     */
    public function __construct()
    {
        $this->repository = new NoticeRepositories();
        $this->userService = new UserService();
    }

    public function getNotices($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return Notice::when(!empty($params['search_key']), function ($query) use ($params) {
                $query->where('title', 'like', '%' . $params['search_key'] . '%');
        })
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                $query->where('started_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                $query->where('ended_at', '<=', $params['end_date']);
            })
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) use ($params) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->paginate($limit);
    }

    public function getNotice($id)
    {
        return Notice::where('id', $id)
            ->first();
    }

    public function updateNotice($params)
    {
        return Notice::where('id', $params)
            ->first();
    }
}
