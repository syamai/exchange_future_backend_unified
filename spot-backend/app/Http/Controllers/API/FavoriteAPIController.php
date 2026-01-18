<?php

namespace App\Http\Controllers\API;

use App\Consts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Models\UserFavorite;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class FavoriteAPIController extends AppBaseController
{
    public function getList(): JsonResponse
    {
        $userId = Auth::id();
        return $this->sendResponse(UserFavorite::where('user_id', $userId)->get());
    }

    public function getListFavorite(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $userFavorite = UserFavorite::where('user_id', $userId);
        if ($request->keyword) {
            $userFavorite = $userFavorite->whereRaw('(lower(coin_pair) LIKE ? OR lower(coin_pair) LIKE ?)', ['%/%'. strtolower($request->keyword).'%', '%'.strtolower($request->keyword).'%/%']);
        }
//        $limit = Arr::get($request, 'limit', Consts::DEFAULT_PER_PAGE);
        return $this->sendResponse($userFavorite->get());
    }

    public function create(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $coinPair = $request->coin_pair;

        $favorite = UserFavorite::create([
            'user_id' => $userId,
            'coin_pair' => $coinPair
        ]);

        return $this->sendResponse($favorite);
    }

    public function addAll(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $favorites = json_decode($request->data);

        UserFavorite::where('user_id', $userId)->delete();
        foreach ($favorites as $favorite) {
            UserFavorite::create([
                'user_id' => $userId,
                'coin_pair' => $favorite->coin_pair
            ]);
        }
        return $this->sendResponse([]);
    }

    public function delete(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $coinPair = $request->coin_pair;

        UserFavorite::where('user_id', $userId)
            ->where('coin_pair', $coinPair)
            ->delete();

        return $this->sendResponse([]);
    }
}
