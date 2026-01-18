<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\UserGroupSetting;
use App\Models\UserGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserGroupSettingService
{
    private $model;

    public function __construct(UserGroupSetting $model)
    {
        $this->model = $model;
    }

    public function getList($params)
    {
        $params = escapse_string_params($params);
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return UserGroupSetting::when(!empty($params['search_key']), function ($query) use ($params) {
            $query->where('name', 'like', '%' . $params['search_key'] . '%');
        })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            $query->where(DB::raw('DATE(created_at)'), '>=', $params['start_date']);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            $query->where(DB::raw('DATE(created_at)'), '<=', $params['end_date']);
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

    public function addNew($params)
    {
        $groupExist = UserGroupSetting::where('name', $params['name'])->first();
        if ($groupExist != null) {
            throw new HttpException(422, __('group_setting.error.exist'));
        }

        return UserGroupSetting::create($params);
    }

    public function update($id, $params)
    {
        $groupExist = UserGroupSetting::where('name', $params['name'])->where('id', '!=', $id)->first();
        if ($groupExist != null) {
            throw new HttpException(422, __('group_setting.error.exist'));
        }

        return UserGroupSetting::where('id', $id)->update($params);
    }

    public function remove($id)
    {
        DB::beginTransaction();
        try {
            UserGroupSetting::where('id', $id)->delete();
            UserGroup::where('group_id', $id)->delete();

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }
}
