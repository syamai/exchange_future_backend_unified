<?php

namespace App\Http\Controllers\API\Auth;

use App\Consts;
use App\Http\Services\Auth\DeviceService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Notifications;
use Illuminate\Support\Facades\App;
use App\Utils;
use App\Models\User;
use App\Mail\LoginNewIP;
use App\Notifications\LoginNewDevice;
use App\Models\UserConnectionHistory;
use Illuminate\Http\Request;
use App\Utils\BearerToken;
use App\Models\UserSecuritySetting;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\ResponseField;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Symfony\Component\HttpKernel\Exception\HttpException;

//use Zend\Diactoros\Response as Psr7Response;
use Nyholm\Psr7\Response as Psr7Response;
use App\Http\Services\UserService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use App\Http\Services\GeetestService;
use App\Rules\GoogleRecaptchaRule;
use Spatie\Crypto\Rsa\KeyPair;
use Spatie\Crypto\Rsa\PrivateKey;
use Spatie\Crypto\Rsa\PublicKey;

class LoginController extends AccessTokenController
{
    use HandlesOAuthErrors;

    /**
     * Login for user
     *
     * @group Account
     *
     * @response {
     * "token_type": "Bearer",
     * "expires_in": 1800,
     * "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiZTEyMmFkMGU0N2U0ZGY3NzkyY2E0MmU0YjRiNDU4ZmEyZTJlZWJhMWFjMWNhMzg5OWFmNzBjZjJmMzcwMzg3ODZkMDk1YTE1Mzk5YTFiOTkiLCJpYXQiOjE2ODA2NjYxMjIuNDgyODE5LCJuYmYiOjE2ODA2NjYxMjIuNDgyODIsImV4cCI6MTY4MDY2NzkyMi40NzE3MTcsInN1YiI6IjMxIiwic2NvcGVzIjpbIioiXX0.lc_SxRUuDVCQxN1nzFo205bZ1WdjpPaYPQrziB1e0UM57cCw6wdSeBqSMG3TV4TJAyMxOh-IczC6_q8LBabF9BM-1gkgdcAjP6-Oh9KviP5ruSCaD2gso3NB5qXaXo--enbrnzNZ08d_p7TVtLlpiLU01wSSztmcpbnqQcoMw4bVgxGk6xcOE1m1DQXu_zfZ0LXbpvxDUTQ4cyZ7BphIDt76EGpI2fXcARCCAs9S3t7m86i38T-sfH_WVl8PhUpxV2XBwIJcU7tImN1m0wq9zNbb6Zn42MviYbBx1zJpzhNTV390Li57rBdn1KSjgLgK2R-tDIPelHqe4IzKslfaVPsQuBKvJkx4_Vduf8mJE7Y7BVvu0l7qNKcMflP7N9t1OFiipsHeuM94CTadzD1yHrG3vexCvIsns-9cxqxdErt6D5mcJrzvf3W5QW_NHosslaW8xP6rwZHlcLWlCTLMLpofxP2DCKDxzT-FlpTBj7AnzFzeIYxL9wzhODuI5xcqJagKdzdGJL8PvMrz3CbWQQthKgB2mtBvChJrLFv2kllxK452FcNERciUQahAy_c0GUkNDp0w9_lj0LEr3bRg0Ehnuw66xtG7mx2gNEO3QJO6bVxjlcSYx9JUg8Gm99VNRNvGQQ3ebN_ux9bXfc37grN8FCVv5JZomP9HwcOez2A",
     * "refresh_token": "def50200afa5f1f362a96ea4df4d73e14261ebd93a889d5098d60ed786862b2752e45c103b33eeacbebfd46e2b6cc37a3f8a43d5eeaa7b82752980c962a72407d4f93ee67c7ab6b8b6e387b366843e092cf1278c56e4fb3bcee21c450a360a603250eb87e99ec8f48e4433807cf89915da6ee651ce691f2466f393e8c60ead697412565ae35936614032eebfa80dc7a7ebd5765d9bd6b0fda8a2ecd00757f2017f357e9636cccdc36186e5e422612afd39554826c95b3a285d444806600d3740b1a759c0d10e9d9a03be3ab1858c6e9473939975560ec87a9f170f38b41936060ef9f3c19af287dad2789359ba4216d9d5771581628664fb554beb96794848d697831f43b7c853d61ab84ec6cba69e8ac2cf88732fc410572e606231f8c8af56aa767c0cf51cca30405fab3d5efa8ab70fae5dbe964e92a5dd36060c3e41fc72829da5f1cf9ec4cb6f1adc01b055f4598da9b4ea9777d4b6b5616299bc4cb56c984b2d577b",
     * "secret": "E8uC7z8rmLUmIVbYZRzF7BpVIFPF9ngwAd16JlUA",
     * "locale": "en"
     * }
     *
     * @response 400 {
     * "error": "invalid_grant",
     * "error_description": "The user credentials were incorrect.",
     * "message": "The user credentials were incorrect.."
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated."
     * }
     */
    #[BodyParam("client_id", "int", "Client ID", required: true, example: 3)]
    #[BodyParam("client_secret", "string", "Client Secret", required: true, example: '78mquW3VmEEoRu84iDAVUzzyiOR3lIucAqkO68Mx')]
    #[BodyParam("grant_type", "string", "Grant Type", required: true, example: 'password')]
    #[BodyParam("scope", "string", "Scope", required: true, example: '*')]
    #[BodyParam("username", "string", "Email", required: true, example: 'user@monas.com')]
    #[BodyParam("password", "string", "Password", required: true, example: '14102001')]
    #[ResponseField("token_type", "The type of token")]
    #[ResponseField("expires_in", "Expired time of token")]
    #[ResponseField("access_token", "Token user")]
    #[ResponseField("refresh_token", "Refresh token user")]
    #[ResponseField("locale", "Language user chose")]
    #[ResponseField("secret", "Secret")]

    /**
     * Login for user
     *
     * @OA\Post(
     *     path="/api/v1/oauth/token",
     *     tags={"Authentication"},
     *     summary="Log in with API key (SIGNED)",
     *     description="Log in with API key (SIGNED)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"client_id","client_secret","grant_type","scope","username","password","otp"},
     *             @OA\Property(property="client_id", type="integer", format="int32", example="3"),
     *             @OA\Property(property="client_secret", type="string", example="78mquW3VmEEoRu84iDAVUzzyiOR3lIucAqkO68Mx"),
     *             @OA\Property(property="grant_type", type="string", example="password"),
     *             @OA\Property(property="scope", type="string", example="*"),
     *             @OA\Property(property="username", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="otp", type="string", example="123123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login",
     *         @OA\JsonContent(
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=1800),
     *             @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJh..."),
     *             @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJh..."),
     *             @OA\Property(property="locale", type="string", example="en"),
     *             @OA\Property(property="secret", type="string", example="E8uC7z8rmLUmIVbYZRzF7BpVIFPF9ngwAd16JlUA")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="invalid_grant"),
     *             @OA\Property(property="message", type="string", example="auth.failed"),
     *             @OA\Property(property="code", type="integer", example=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Server Error"),
     *             @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *             @OA\Property(property="data", type="string", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function issueToken(ServerRequestInterface $request)
    {
        return parent::issueToken($request);
    }

    public function loginBiometrics(ServerRequestInterface $request)
    {
        $request_data = request();
        $email = $request_data->get('message');
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw new OAuthServerException('validation.request', 6, 'invalid_request');
        }
        $signature = $request_data->get('signature');
        if (!($user->fingerprint ?? $user->faceID)) {
            throw new OAuthServerException('error.login', 6, 'error.login');
        }
        $key = $this->generatePublicKey($user->fingerprint ?? $user->faceID);
        $publicKey = PublicKey::fromString($key);
        if ($publicKey->verify($email, $signature)) {
            Auth::login($user);
            $tokenResult = $user->createToken('Token name');
            $accessToken = $tokenResult->accessToken;
            $secret = $this->createTokenSecret(['access_token' => $accessToken]);
            $locale = 'en';
            $tokenType = 'Bearer';
            $expiresIn = env('TOKEN_EXPIRE_TIME', 1440) * 60;
            $clineId = $tokenResult->token->client_id;

            // sync access token to future account when user login spot success
            Utils::syncAccessToken($accessToken);

            return response()->json([
                'access_token' => $accessToken,
                'secret' => $secret,
                'locale' => $locale,
                'token_type' => $tokenType,
                'expires_in' => $expiresIn
            ]);
        }

        throw new OAuthServerException('error.login', 6, 'error.login');
    }

    public function generatePublicKey($key)
    {
        return "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }

    public function extendTokenExpire($tokenResponse, $user)
    {
        if (@$user->type != 'bot') {
            return true;
        }

        $extendMonths = 12;
        $oauthId = @DB::table('oauth_access_tokens')->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first()->id;
        if ($oauthId) {
            $refreshTokenRecord = DB::table('oauth_refresh_tokens')->where('access_token_id', $oauthId)->first();
            if ($refreshTokenRecord) {
                $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $refreshTokenRecord->expires_at);
                $expiresAt->addMonths($extendMonths);

                DB::table('oauth_access_tokens')->where('id', $oauthId)->update([
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]);

                DB::table('oauth_refresh_tokens')->where('access_token_id', $oauthId)->update([
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]);
            }
        }
    }

    public function issueTokenViaApi(ServerRequestInterface $request)
    {
        $request_data = request();
        $user = DB::table('users')->where('email', $request_data->get('username'))->first();
        if ($user->id) {
            $user_security_settings = DB::table('user_security_settings')->where('id', $user->id)->first();
            if (!$user_security_settings && $user->created_at) {
                $create_at_db = Carbon::createFromFormat('Y-m-d H:i:s', $user->created_at);
                $create_at_rq = Carbon::now()->subHours(24);
                if ($create_at_db < $create_at_rq) {
                    $return_data = [
                        'error' => 'invalid_credentials',
                        'message' => 'The user credentials were incorrect.'
                    ];
                    return json_encode($return_data);
                }
            }
        }
        return $this->withErrorHandling(function () use ($request) {
            $response = $this->convertResponse(
                $this->server->respondToAccessTokenRequest($request, new Psr7Response)
            );

            $this->verifyAdditinalSettings($request);
            return $this->authenticated($response);
        });
    }

    protected function authenticated($response)
    {
        $request = request();
        $user = User::where('email', $request->get('username'))->first();

        $deviceService = new DeviceService();
        $deviceService->checkValid($user, $request->ip());

        return $this->modifyResponse($response);
    }


    protected function modifyResponse($response)
    {
        $content = $this->getResponseContent($response);
        $content['secret'] = $this->createTokenSecret($content);
        $content['locale'] = App::getLocale();
        $redirectUrl = $this->attemptSiteSupport();
        if ($redirectUrl) {
            $content['redirectUrl'] = $redirectUrl;
        }
        return $response->setContent($content);
    }

    protected function getResponseContent($response)
    {
        return collect(json_decode($response->content()));
    }

    protected function createTokenSecret($content)
    {
        $token = BearerToken::fromJWT($content['access_token']);
        $token->secret = Str::random(40);
        $token->save();
        return $token->secret;
    }

    protected function verifyAdditinalSettings($request)
    {
        $request_data = request();
        $locale = $request_data->get('lang');
        $params = $request->getParsedBody();
        $email = $params['username'];
        $user = User::where('email', $email)->first();
        $userSecuritySettings = UserSecuritySetting::find($user->id);

        if (!$userSecuritySettings->email_verified) {
            throw new OAuthServerException('validation.unverified_email', 6, 'email_unverified');
        }

        if ($userSecuritySettings->otp_verified) {
            $result = $this->verifyOtp($user, $params);
            if (!$result) {
                throw new OAuthServerException('validation.otp_incorrect', 6, 'invalid_otp');
            }
            if ($result === 409) {
                throw new OAuthServerException('validation.otp_not_used', 6, 'invalid_otp');
            }
        }
    }

    protected function verifyOtp($user, $params)
    {
        if (array_key_exists('otp', $params)) {
            $otp = $params['otp'];
            return $user->verifyOtp($otp);
        } else {
            return false;
        }
    }

    private function attemptSiteSupport()
    {
        $request = request();
        $key = config('app.zendesk_key');

        if (!$request->has('redirectUrl') || empty($key)) {
            return null;
        }
        $domain = \App\Consts::DOMAIN_SUPPORT;
        $redirectUrl = $request->get('redirectUrl');
        if (strpos($redirectUrl, $domain) === false) {
            return $redirectUrl;
        }
        $now = time();
        $token = array(
            'jti' => md5($now . rand()),
            'iat' => $now,
            'name' => $request->get('username'),
            'email' => $request->get('username')
        );
        $jwt = JWT::encode($token, $key, Consts::DEFAULT_JWT_ALGORITHM);
        $redirectUrl = "{$domain}/access/jwt?jwt={$jwt}&$redirectUrl";
        return $redirectUrl;
    }

    /**
     * @OA\Get(
     *     path="/check-api-key",
     *     summary="[Private] Query session status",
     *     description="Query session status",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *          name="APIKEY",
     *          in="header",
     *          description="",
     *          @OA\Schema(
     *              type="string",
     *              example="ed4......."
     *          )
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="Successful response",
     *          @OA\JsonContent(
     *              oneOf={
     *                  @OA\Schema(
     *                      description="Successful",
     *                      @OA\Property(property="token", type="string", example="1"),
     *                      @OA\Property(property="scopes", type="string", example="1"),
     *                      @OA\Property(property="name", type="string", example="Bot 1"),
     *                      @OA\Property(property="result", type="object",
     *                          @OA\Property(property="id", type="string", example="f709003..."),
     *                          @OA\Property(property="user_id", type="integer", example=1),
     *                          @OA\Property(property="client_id", type="integer", example=2),
     *                          @OA\Property(property="name", type="string", example="Bot1"),
     *                          @OA\Property(property="scopes", type="array", @OA\Items(type="string", example="1")),
     *                          @OA\Property(property="revoked", type="boolean", example=false),
     *                          @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-06 04:57:41"),
     *                          @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-06 04:57:41"),
     *                          @OA\Property(property="expires_at", type="string", format="date-time", example="2025-06-06T04:57:41.000000Z"),
     *                          @OA\Property(property="type", type="integer", example=1),
     *                          @OA\Property(property="ip_restricted", type="string", nullable=true, example=null),
     *                          @OA\Property(property="is_restrict", type="integer", example=0),
     *                          example={
     *                              "id": "f709003...",
     *                              "user_id": 1,
     *                              "client_id": 2,
     *                              "name": "Bot1",
     *                              "scopes": {"1"},
     *                              "revoked": false,
     *                              "created_at": "2024-06-06 04:57:41",
     *                              "updated_at": "2024-06-06 04:57:41",
     *                              "expires_at": "2025-06-06T04:57:41.000000Z",
     *                              "type": 1,
     *                              "ip_restricted": null,
     *                              "is_restrict": 0
     *                          }
     *                      )
     *                  ),
     *                  @OA\Schema(
     *                      description="APIKEY not working",
     *                      @OA\Property(property="token", type="string", example="Bearer No token found."),
     *                      @OA\Property(property="scopes", type="string", example="null"),
     *                      @OA\Property(property="result", type="string", example="null")
     *                  )
     *              }
     *          )
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="Server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      security={{ "apiAuth": {} }}
     * )
     */
    public function getAccessToken(Request $request)
    {
        return response()->json(
            [
                'token' => $request->header('Authorization'),
                'scopes' => $request->header('Scopes'),
                'result' => json_decode($request->header('data')),
            ]
        );
    }
}
