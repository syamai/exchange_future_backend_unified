<?php

namespace App\Http\Services;

use App\Models\EmailMarketing;
use App\Notifications\Marketing;
use App\Consts;
use Illuminate\Support\Arr;

class EmailMarketingService
{
    public function getList($params)
    {
        return EmailMarketing::when(!empty($params['search_key']), function ($query) use ($params) {
                                $query->where('title', 'like', '%' . $params['search_key'] . '%');
        })
                            -> when(
                                !empty($params['sort']) && !empty($params['sort_type']),
                                function ($query) use ($params) {
                                    $query->orderBy($params['sort'], $params['sort_type']);
                                },
                                function ($query) use ($params) {
                                    $query->orderBy('created_at', 'desc');
                                }
                            )
                            ->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
    }
    public function getOne($id)
    {
        return EmailMarketing::where('id', $id)->first();
    }

    public function sendMail($user, $templateEmailMaketing): void
    {
        $user->notify(new Marketing($templateEmailMaketing->content, $user->email, $templateEmailMaketing->title, $templateEmailMaketing->from_email));
    }
}
