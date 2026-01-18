<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\ActivityHistory;
use Carbon\Carbon;

class ActivityHistoryService
{
    public function create($data) {
        $data['detail'] = $this->getDetail($data['type'], $data['target_id'], 'en');
        ActivityHistory::create($data);
    }

    public function createAddPartnerActivity($actorId, $targetIds) {
        $data = [];
        $today = Carbon::now();
        foreach ($targetIds as $targetId) {
            $data[] = [
                'page' => Consts::ACTIVITY_HISTORY_PAGE_PARTNER_ADMIN,
                'type' => Consts::ACTIVITY_HISTORY_TYPE_ADD_PARTNER,
                'actor_id' => $actorId,
                'target_id' => $targetId,
                'detail' => $this->getDetail(Consts::ACTIVITY_HISTORY_TYPE_ADD_PARTNER, $targetId, 'en'),
                'created_at' => $today,
                'updated_at' => $today,
            ];
        }
        ActivityHistory::insert($data);
    }

    public function getDetail($type, $targetId, $local = null) {
        return __('partneradmin.activity.' . $type, ['id' => $targetId], $local);
    }
}   