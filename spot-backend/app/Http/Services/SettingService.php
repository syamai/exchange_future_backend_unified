<?php
namespace App\Http\Services;

use App\Models\Settings;
use App\Repositories\SettingReponsitory;
use Illuminate\Support\Arr;

class SettingService
{
    private SettingReponsitory $settingRepository;

    public function __construct()
    {
        $this->settingRepository = new SettingReponsitory();
    }

    public function getremainamlsetting()
    {
        return $this->settingRepository->getremainamlsetting();
    }
    public function saveremainamlsetting()
    {
        return $this->settingRepository->saveRemainAMLSetting();
    }

    public function getValueFromKey($key)
    {
        $settings = Settings::where('key', $key)->first();
        if (!$settings) {
            return false;
        }
        return $settings->value;
    }
    public function updateValueByKey($key, $value)
    {
        return Settings::where('key', $key)->update(['value' => $value]);
    }

    public function changeSetting($params)
    {
        $key = Arr::get($params, 'key', "");
        $value = Arr::get($params, 'value', false);
        Settings::updateOrCreate(
            ['key' => $key],
            ['key' => $key, 'value' => $value]
        );
    }
    public function getSettingSelfTrading()
    {
        $settings = Settings::all();
        if (!$settings) {
            return false;
        }
        return $settings;
    }
}
