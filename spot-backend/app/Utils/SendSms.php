<?php
namespace App\Utils;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\ScTran;
use Carbon\Carbon;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendSms
{

    public static function getAdminPhoneNumber()
    {
        $phoneConfig = MasterdataService::getOneTable('settings')->where('key', Consts::SETTING_ADMIN_PHONE_NO)->first();
        if (!$phoneConfig) {
            throw new HttpException(422, __('exception.not_setting_phone_admin'));
        }
        return $phoneConfig->value;
    }

    private static function shortenString($str, $maxLength)
    {
        if ($str && mb_strlen($str) > $maxLength) {
            return mb_substr($str, 0, $maxLength) . "**";
        }
        return $str;
    }

    private static function trailString($str, $maxLength)
    {
        if ($str && mb_strlen($str) > $maxLength) {
            return "***" . mb_substr($str, -$maxLength, $maxLength);
        }
        return $str;
    }
}
