<?php

namespace App\Http\Middleware;

use App\Http\Services\Auth\DeviceService;
use App\Consts;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;

class ManageDevice
{
    public function handle($request, Closure $next, $guard = null)
    {
        $disableVerifyDevice = env('DISABLE_VERIFY_DEVICE', false);
        if (!$disableVerifyDevice && !$request->header(Consts::API_KEY, null) && Auth::check() && Auth::user()->isValid() && $this->isPassport()) {
            $deviceService = new DeviceService();
            $device = $deviceService->getCurrentDevice('');
            if ($device->isNewDevice) {
                throw new AuthenticationException;
            }
        }

        return $next($request);
    }

    public function isPassport()
    {
        return $this->getAuthDriver() === 'passport';
    }

    public function getAuthDriver()
    {
        $currentGuard = Auth::getDefaultDriver();
        $config = config('auth.guards');
        return $config[$currentGuard]['driver'];
    }
}
