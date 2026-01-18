<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AppBaseController;
use Carbon\Carbon;

/**
 * Class NotificationAPIController
 * @package App\Http\Controllers\API
 */

class NotificationAPIController extends AppBaseController
{
    /**
     * Get the unread notifications of the current logged in user
     *
     * @return JSON
     */
    public function getUnreadNotifications(Request $request)
    {
        return $this->sendResponse($request->user()->unreadNotifications);
    }

    /**
     * Mark all the unread notifications of the current logged in user as read
     *
     * @return JSON
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => Carbon::now()]);
        return $this->sendResponse(0);
    }
}
