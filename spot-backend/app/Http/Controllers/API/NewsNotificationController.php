<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;

use App\Models\NewsNotification;
use App\Models\NewsNotificationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class NewsNotificationController extends AppBaseController
{
    public function getCategories(Request $request)
    {
        $input = $request->all();
        $data = NewsNotificationCategory::filter($input)
            ->where('status', 'enable')
            ->orderBy('created_at', 'desc')
            ->get();


        return $this->sendResponse($data);
    }

    public function getNewsNotifications(Request $request)
    {

        $user = auth('api')->user() ?? null;
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $hideRead = $request->hide_read ?? false;
        $notifications = NewsNotification::with([
            'category',
            'readers' => function ($query) use ($user) {
                if ($user) {
                    $query->where('user_id', $user->id);
                }
            }])
            ->filter($input)
            ->when($hideRead && $user, function ($query) use ($user) {
                $query->whereNotIn('id', function ($builder) use ($user) {
                    $builder->select('news_notification_id')
                        ->from('news_notification_users')
                        ->where('user_id', $user->id);
                });
            })
            ->where(['status' => Consts::NEWS_NOTIFICATION_STATUS_POSTED])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        $data = $notifications->map(function ($item) use ($user) {
            return [
                'id' => $item->id,
                'title' => $item->title_en,
                'content' => $item->content_en,
                'title_vi' => $item->title_vi,
                'content_vi' => $item->content_vi,
                'created_at' => $item->created_at,
                'link_event' => $item->link_event,
                'read' => $user ? $item->readers->isNotEmpty() : false,
                'category' => $item->category
            ];
        });

        return $this->sendResponse($data);
    }

    public function markAsRead($id, Request $request)
    {
        $user = $request->user();

        $notification = NewsNotification::findOrFail($id);

        $user->readNewsNotifications()->syncWithoutDetaching([
            $notification->id => ['read_at' => now()],
        ]);

        return $this->sendResponse([
            'notification_id' => $notification->id,
        ], 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        /*$allIds = NewsNotification::pluck('id')->toArray();
        $alreadyReadIds = $user->readNewsNotifications()->pluck('news_notification_id')->toArray();

        $unreadIds = array_diff($allIds, $alreadyReadIds);*/

        $unreadIds = NewsNotification::where('status', 'posted')
            ->whereNotIn('id', function ($query) use ($user){
                $query->select('news_notification_id')
                    ->from('news_notification_users')
                    ->where('user_id', $user->id);
            })->pluck('id')->toArray();

        if (empty($unreadIds)) {
            return $this->sendResponse('All notifications already read.');
        }
        $now = Carbon::now();

        $pivotData = [];
        foreach ($unreadIds as $id) {
            $pivotData[$id] = ['read_at' => $now];
        }

        $user->readNewsNotifications()->syncWithoutDetaching($pivotData);

        return $this->sendResponse([
            'marked_count' => count($pivotData),
        ], "All notifications marked as read.");
    }

    public function getUnreadCount(Request $request)
    {
        $user = auth('api')->user() ?? null;
        $unreadCount = 0;
        if ($user) {
            $unreadCount = NewsNotification::where('status', 'posted')
                ->whereNotIn('id', function ($query) use ($user){
                    $query->select('news_notification_id')
                        ->from('news_notification_users')
                        ->where('user_id', $user->id);
                })->count();
        }


        return $this->sendResponse(['unread_count' => $unreadCount]);
    }
}
