<?php

/**
 * Created by PhpStorm.
 * Date: 7/23/19
 * Time: 10:32 AM
 */

namespace App\Http\Services\Auth;

use App\Consts;
use App\Http\Services\FirebaseNotificationService;
use App\Models\UserConnectionHistory;
use App\Models\UserDeviceRegister;
use App\Notifications\LoginNewDevice;
use App\Utils;
use Carbon\Carbon;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use const _PHPStan_532094bc1\__;

class DeviceService
{
    public function checkLastIp($settingId, $ip)
    {
        $device = $this->getCurrentUserDevice($settingId);
//        $device->latest_ip_address = $ip;
//        $device->save();

        if (!$device) {
            return;
        }

        $authorizeDeviceCode = $this->getAuthorizeDeviceCode($device);
        $this->authorizeDevice($authorizeDeviceCode);
    }

    public function checkValid($user, $ip, $saveConnectionHistory = true)
    {
        $device = $this->checkCurrent($user, $ip);
        if ($saveConnectionHistory) {
            $this->storeConnection($device);
        }
    }

    private function checkCurrent($user, $ip)
    {
        $device = $this->getCurrentDevice('', $user->id);
        $device->touch();
        $disableVerifyDevice = env('DISABLE_VERIFY_DEVICE', false);

        if (!$disableVerifyDevice && $user->isValid() && ($device->isNewDevice)) {
            $device->latest_ip_address = request()->ip();
            $device->save();
//            FirebaseNotificationService::send($user->id, __('title.notification.login_new_device'),
//                __('body.notification.login_new_device', ['time' => Carbon::now()], $user->getLocale()));
            $user->notify(new LoginNewDevice($device, $this->getAuthorizeDeviceCode($device)));
            throw new OAuthServerException('account.invalid_device', 6, 'invalid_device');
//            $this->notifyLoginInNewIp($device);
        }
        $device->latest_ip_address = request()->ip();
        $device->save();

        return $device;
    }

    private function storeConnection($device)
    {
        $connectionHistory = new UserConnectionHistory();
        $connectionHistory->user_id = $device->user->id;
        $connectionHistory->device_id = $device->id;
        $connectionHistory->ip_address = $device->latest_ip_address;
        $connectionHistory->created_at = Utils::currentMilliseconds();
        $connectionHistory->updated_at = Utils::currentMilliseconds();
        $connectionHistory->setConnection('master');
        $connectionHistory->save();
    }

    private function notifyLoginInNewIp($device)
    {
        // Uncomment to enable Login New IP Mail
        // Mail::queue(new LoginNewIP($device));
    }


    public function getCurrentDevice($name, $userId = null)
    {
        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_NONE);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceDetector = new DeviceDetector($userAgent);
        $deviceDetector->parse();

        $device = new UserDeviceRegister();
        $device->setConnection('master');
        $device->user_id = $userId ?? Auth::id();
        $device->kind = $deviceDetector->getDeviceName();
        $device->name = $name;
        $device->platform = @$deviceDetector->getClient()['name'] . " " . @$deviceDetector->getClient()['version'];
        $device->operating_system = @$deviceDetector->getOs()['name'] . " " . @$deviceDetector->getOs()['version'];
        $device->state = Consts::DEVICE_STATUS_BLOCKED;
//        $payload = [$device->user_id, $device->kind, request()->ip(), $device->operating_system];
        $payload = [$device->user_id, $device->kind, $device->platform, $device->operating_system];
        $device->user_device_identify = base64url_encode(implode('_', $payload));

        $existedDevice = UserDeviceRegister::on('master')
            ->where('user_device_identify', $device->user_device_identify)
            ->first();

        if ($existedDevice) {
            return $existedDevice;
        }

        $device->latest_ip_address = request()->ip();
        $device->save();

        return $device;
    }

    public function getCurrentUserDevice($userId)
    {
        return UserDeviceRegister::on('master')
            ->where('user_id', $userId)
            ->first();
    }

    public function authorizeDevice($code)
    {
        $payload = explode('_', base64url_decode($code) . '_');

        $deviceIdentify = $payload[0];
        $expriedAt = $payload[1];
        $codeUniqid = $payload[3];

        $device = UserDeviceRegister::on('master')
            ->where('user_device_identify', $deviceIdentify)
            ->where('code', $codeUniqid)
            ->first();

        $invalid = !$device || $expriedAt < Utils::currentMilliseconds() || !$device->updated_at || ($device->state == Consts::DEVICE_STATUS_CONNECTABLE);

        if ($invalid) {
            throw new HttpException(422, 'exception.device_invalid');
        }

        if ($device->state != Consts::DEVICE_STATUS_CONNECTABLE) {
            $device->state = Consts::DEVICE_STATUS_CONNECTABLE;
            $device->save();
        }

        return $device;
    }

    public function getAuthorizeDeviceCode($device)
    {
        $code = uniqid(Str::random(60), true);
        $device->code = $code;
        $device->setConnection('master');
        $device->save();

        $payload = [
            $device->user_device_identify,
            Utils::currentMilliseconds() + (1000 * 60 * 30),
            now()->timestamp,
            $device->code
        ];

        return base64url_encode(implode('_', $payload));
    }
}
