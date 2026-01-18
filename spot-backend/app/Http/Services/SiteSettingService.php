<?php


namespace App\Http\Services;

use App\Models\SiteSetting;
use App\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class SiteSettingService
 * @package App\Http\Services
 */
class SiteSettingService
{
    /**
     * Get By Key
     * @param string $key
     * @return array
     */
    public function getByKey(string $key): array
    {
        $result = [];
        $settings = SiteSetting::query()->where('key', '!=', $key)->get();

        foreach ($settings as $item) {
            $result[$item->key] = $item->value;
        }

        return $result;
    }

    /**
     * Update
     * @param $input
     * @throws \Exception
     */
    public function update(array $input)
    {
        DB::beginTransaction();
        try {
            $keys = array_keys($input);

            $settings = DB::table('settings')->whereIn('key', $keys)->get();
            if (!$settings->count()) {
                return; // Expected all keys are existed.
            }
            foreach ($settings as $setting) {
                DB::table('settings')->where('key', $setting->key)
                    ->update([
                        'key' => $setting->key,
                        'value' => $input[$setting->key]
                    ]);
            }
            DB::commit();
            MasterdataService::clearCacheOneTable('settings');
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    public function getImageMail(): array
    {
        $header = SiteSetting::query()->where('banner', Utils::getBannerMailTemplate(Str::random('46')))->first()?->banner;
        $footer = SiteSetting::query()->where('footer', Utils::getFooterMailTemplate(Str::random('46')))->first()?->footer;

        return [
            'header' => $header,
            'footer' => $footer
        ];
    }
}
