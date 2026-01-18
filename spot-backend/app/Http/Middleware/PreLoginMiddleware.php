<?php

namespace App\Http\Middleware;

use App\Http\Services\GeetestService;
use App\Models\UserSecuritySetting;
use App\Rules\GoogleRecaptchaRule;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PreLoginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $isBot = false;
        $this->validateCaptcha($request, $isBot);

        if (!$isBot) {
            $this->verifyAdditionalSettings($request);
        }
        return $next($request);
    }

    use HandlesOAuthErrors;
    protected function validateCaptcha($request, &$isBot)
    {
        $user = User::query()
            ->where('email', $request->get('username'))
            ->first();

        $request->merge([
            'userInformation' => $user
        ]);

        if (!$user) {
            throw new UnprocessableEntityHttpException('exception.user_not_found');
        }
        if (!$this->checkPassword($request->password, $user->password)) {
            throw new OAuthServerException('auth.failed', 400, 'invalid_grant');
        }

        if ($user->type == 'bot') {
            $isBot = true;
            return;
        }
        // $this->validateGoogleCaptcha($user);
    }

    private function validateGoogleCaptcha($user)
    {
        if (request()->get('is_ggcaptcha', null)) {
            $recaptchaKey = request()->get('geetestData');
            $recaptchaKey = "recaptcha_{$user->id}_{$recaptchaKey}";
            if (!cache($recaptchaKey, false)) {
                $validator = Validator::make(['ggToken' => request()->get('geetestData')], [
                    'ggToken' => [new GoogleRecaptchaRule()]
                ]);

                $result = !$validator->fails();

                cache([$recaptchaKey => $result], now()->addSeconds(30));

                if (!$result) {
                    throw new OAuthServerException('google.recaptcha.errors', 6, 'recaptcha_failed');
                }
            }
        } else {
            $geetestData = request()->get('geetestData', []);
            $geetestKey = implode($geetestData);
            $geetestKey = "geetest_{$user->id}_{$geetestKey}";

            if (!cache($geetestKey, false)) {
                $result = GeetestService::secondaryVerify($geetestData);
                cache([$geetestKey => !!$result], now()->addSeconds(30));
                if (!$result) {
                    throw new OAuthServerException(__('exception.geetest.invalid'), 6, 'geetest_failed');
                }
            }
        }
    }

    private function checkPassword($password, $hashedPassword): bool
    {
        return Hash::check($password, $hashedPassword);
    }

    protected function verifyAdditionalSettings(Request $request)
    {
        $user = $request->userInformation;
        $userSecuritySettings = UserSecuritySetting::query()->find($user->id);
        $request->merge([
            'userSecuritySettings' => $userSecuritySettings,
        ]);

        if (!$userSecuritySettings->email_verified) {
            throw new OAuthServerException('validation.unverified_email', 6, 'email_unverified');
        }
    }
}
