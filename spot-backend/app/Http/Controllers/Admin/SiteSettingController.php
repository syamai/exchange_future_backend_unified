<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\SocicalNetworkRequest;
use App\Models\SocicalNetwork;
use Illuminate\Http\Request;
use App\Models\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Services\MasterdataService;

class SiteSettingController extends AppBaseController
{
    public function getSettingSite(Request $request)
    {
        $result = [];
        $settings = DB::table('settings')->where('key', '!=', Consts::SETTING_MIN_BLOCKCHAIN_ADDRESS_COUNT)->get();
        foreach ($settings as $item) {
            $result[$item->key] = $item->value;
        }
        return $this->sendResponse($result);
    }

    public function updateSettingSite(Request $request)
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $keys = array_keys($input);

            $settings = DB::table('settings')->whereIn('key', $keys)->get();
            if (!$settings->count()) {
                return; // Expected all keys are existed.
            }
            foreach ($settings as $setting) {
                DB::table('settings')->where('key', $setting->key)
                    ->update([
                        'key'   => $setting->key,
                        'value' => $input[$setting->key]
                    ]);
            }
            DB::commit();
            MasterdataService::clearCacheOneTable('settings');
            return $this->sendResponse(Consts::TRUE);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getSocialNetworks()
    {
        $data = SocicalNetwork::all();
        return $this->sendResponse($data);
    }

    public function addSocialNetwork(SocicalNetworkRequest $request)
    {
        $res = SocicalNetwork::updateOrCreate($request->all());
        MasterdataService::clearCacheOneTable('social_networks');
        return $this->sendResponse($res);
    }

    public function updateSocialNetWork(SocicalNetworkRequest $request)
    {
        $socialNetworkId = $request->input('id');
        $params = $request->except('id');

        $socialNetwork = SocicalNetwork::find($socialNetworkId);
        $socialNetwork->update($params);
        MasterdataService::clearCacheOneTable('social_networks');
        return $socialNetwork;
    }

    public function removeSocialNetwork($id)
    {
        $socialNetwork = SocicalNetwork::where('id', $id);
        $socialNetwork->delete();
        MasterdataService::clearCacheOneTable('social_networks');
        return 'ok';
    }
}
