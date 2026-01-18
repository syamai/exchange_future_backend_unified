<?php

namespace App\Repositories;

use App\Consts;
use App\Models\Settings;
use App\Events\SettingUpdated;

class SettingReponsitory extends BaseRepository
{
    private $setting;

    public function __construct()
    {
        // Settings $setting
        $this->setting = app(Settings::class);
    }

    public function model()
    {
        return Settings::class;
    }

    public function create($input)
    {
        return Settings::create($input);
    }

    public function getRemainAMLSetting()
    {
        $res = Settings::where('key', 'show_remaining_aml')->first();
        if (empty($res)) {
            $record = [
                'key' => 'show_remaining_aml',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now()
            ];


            $res = $this->setting->create($record);
        }
        event(new SettingUpdated($res));
        return $res;
    }
    public function saveRemainAMLSetting()
    {
        $res = Settings::where('key', 'show_remaining_aml')->first();
        $res->value = $res->value == 1 ? 0 : 1 ;
        $res->save();
        event(new SettingUpdated($res));
        return $res;
    }
}
