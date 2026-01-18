<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\UserGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserGroupService
{
    private UserGroup $model;

    public function __construct(UserGroup $model)
    {
        $this->model = $model;
    }

    public function getList($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return UserGroup::leftJoin('users', function ($join) {
            $join->on('user_group.user_id', '=', 'users.id');
        })
        ->leftJoin('user_group_setting', function ($join) {
            $join->on('user_group.group_id', '=', 'user_group_setting.id');
        })
        ->when(!empty($params['group']), function ($query) use ($params) {
            $query->where('user_group.group_id', $params['group']);
        })
        ->when(!empty($params['user']), function ($query) use ($params) {
            $query->where('user_group.user_id', $params['user']);
        })
        ->select('user_group.group_id', 'user_group.user_id', 'users.name as user_name', 'users.email as user_email', 'user_group_setting.name as group_name')
        ->paginate($limit);
    }

    public function update($lstUser, $lstGroup)
    {
        DB::beginTransaction();
        try {
            /*
            foreach ($lstUser as $itemUser=>$user) {
                UserGroup::where('user_id', $user)->delete();
            }
            */
            foreach ($lstGroup as $itemGroup => $group) {
                foreach ($lstUser as $itemUser => $user) {
                    UserGroup::updateOrCreate([
                        'group_id' => $group,
                        'user_id' => $user
                    ], [
                        'group_id' => $group,
                        'user_id' => $user
                    ]);
                }
            }

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }

    public function delete($lstRemove)
    {
        DB::beginTransaction();
        try {
            foreach ($lstRemove as $item => $value) {
                UserGroup::where('group_id', $value['group_id'])
                ->where('user_id', $value['user_id'])
                ->delete();
            }

            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }
}
