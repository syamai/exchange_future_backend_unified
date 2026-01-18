<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserSettingAPIController extends AppBaseController
{
    public function getSymbolSettings(): JsonResponse
    {
        $userId = Auth::id();
        $sortSettings = UserSetting::where('user_id', $userId)
            ->whereIn('key', ['sort_column', 'sort_direction'])
            ->get();

        $result = [];
        foreach ($sortSettings as $setting) {
            $result[$setting->key] = $setting->value;
        }
        return $this->sendResponse($result);
    }

    public function updateSymbolSettings(Request $request): array
    {
        $params = $request->all();
        $userId = Auth::id();
        foreach ($params as $key => $value) {
            UserSetting::updateOrCreate(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $value]
            );
        }
        return $params;
    }

    public function useFakeName(): JsonResponse
    {
        \request()->validate(['use_fake_name' => ['required', Rule::in([0, 1])]]);

        try {
            $use_fake_name = \request('use_fake_name');
            $result = auth('api')->user()->securitySetting()->update(['use_fake_name' => $use_fake_name]);
            return $this->sendResponse($result);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }
}
