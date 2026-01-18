<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Services\SingleSession\SingleSessionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPGangsta_GoogleAuthenticator;
use ErrorException;
use Exception;

class LoginController extends Controller
{
    private string $guard;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle a login request to the application.
     *
     * @param  Request  $request
     * @return RedirectResponse|Response|JsonResponse
     */
    public function login(Request $request): Response|JsonResponse|RedirectResponse
    {
        // $this->validateLogin($request);

        $customMessage = [
            // 'email.required' => "The email field is required.",
            // 'password.required' => "The password field is required."
            'email.required' => "email.required",
            'password.required' => "password.required"
        ];

        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
        ], $customMessage);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 422,
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only(['email', 'password']);
        $admin = Admin::query()->where('email', $credentials['email'])->first();
        if (!$admin || !Hash::check($credentials['password'], $admin->password)) {
            return response()->json([
                'status_code' => 422,
                'message' => 'auth.failed'
            ], 422);
        }

        //check gg auth
		$allowGGAuth = env('ENABLE_ADMIN_GG_AUTH', false);
		$otp = $request->otp ?? '';
		$ggCode = $admin->google_authentication;

		$googleAuthenticator = app(PHPGangsta_GoogleAuthenticator::class);

		if ($admin->otp_verified) {
			$result = $googleAuthenticator->verifyCode($ggCode, $otp, 0);
			if (!$result) {
				return response()->json([
					'status_code' => 400,
					'message' => 'validation.otp_incorrect',
				], 400);
			}
		} elseif ($otp && $ggCode) {
			if ($admin->otp_verified || $allowGGAuth) {
				$result = $googleAuthenticator->verifyCode($ggCode, $otp, 0);
				if (!$result) {
					return response()->json([
						'status_code' => 400,
						'message' => 'validation.otp_incorrect',
					], 400);
				}
				if (!$admin->otp_verified) {
					$admin->otp_verified = 1;
					$admin->save();
				}
			}
		} elseif ($allowGGAuth && !$admin->otp_verified) {
			if (!$ggCode) {

				$ggCode = $googleAuthenticator->createSecret();
				/*$admin->update([
					'google_authentication' => $ggCode
				]);*/
				$admin->google_authentication = $ggCode;
				$admin->save();
			}

			$qrCodeUrl = $googleAuthenticator->getQRCodeGoogleUrl($admin->email, $ggCode, 'Admin ' . env('APP_NAME'));
			return response()->json([
				'status_code' => 400,
				'message' => 'auth.enable_otp',
				'data' => [
					'key' => $ggCode,
					'url' => $qrCodeUrl
				]
			], 400);
		}


        $accessToken = $admin->createToken('authToken', ['*'], Carbon::now()->addDays(env('TOKEN_ADMIN_EXPIRE_TIME', 1)))->plainTextToken;
//		$tokenModel = $admin->tokens()->latest()->first(); // Lấy token vừa tạo
//		$tokenModel->expires_at = Carbon::now()->addDays(env('TOKEN_ADMIN_EXPIRE_TIME', 1));
//		$tokenModel->save();

        $this->syncAccessToken($accessToken);

        return response()->json([
            'status_code' => 200,
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
        ]);
    }

    protected function guard(): Guard|StatefulGuard
    {
        return Auth::guard($this->guard);
    }

    /**
     * @param Request $request
     * @param $user
     * @return RedirectResponse
     */
    protected function authenticated(Request $request, $user)
    {
        SingleSessionService::setOriginalSessionId();
        return redirect()->intended($this->redirectPath());
    }

    protected function syncAccessToken($accessToken)
    {
        $futureBaseUrl = env('FUTURE_API_URL');
        $futureAccessTokenUrl = $futureBaseUrl . '/api/v1/access-token';
        $client = new Client();

        try {

            $resAccess = $client->request('POST', $futureAccessTokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'json' => [
                    'token' => $accessToken
                ]
            ]);

            if ($resAccess->getStatusCode() >= 400) {
                throw new ErrorException(json_encode($resAccess->getBody()));
            }

        } catch (Exception $e) {
            Log::error("START SYNC ACCESS TOKEN TO FUTURE");
            Log::error($e);
            Log::error("END SYNC ACCESS TOKEN TO FUTURE");
        }
    }

    protected function authFuture()
    {
        return response()->json([
            'status_code' => 200,
            'auth' => 'success',
        ]);
    }
}
