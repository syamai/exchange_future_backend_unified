<?php

namespace PassportHmac\Http\Services;

use App\Utils\BearerToken;

class HmacService
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function checking($request)
    {
        $signature = request('signature');
        $token = BearerToken::fromRequest();
        $tokenScopes = $token->scopes;

        if ($tokenScopes == null && count($tokenScopes) === 0) {
            return true;
        }

        if ($this->permissionService->checkIsPermissionScope($tokenScopes[0])) {
            // $this->compare($token->secret, $signature);
        }

        if (!!$token->is_restrict && $this->checkIp($token, $request)) {
            abort(403, 'Access denied');
        }

        return true;
    }

    private function checkIp($token, $request)
    {
        $requestIp = $request->ip();

        if (isset($token) && isset($token->ip_restricted)) {
            $array = explode(',', $token->ip_restricted);
            if (!in_array($requestIp, $array)) {
                return true;
            }
        }

        return false;
    }

    public function compare($secret, $signature)
    {
        if ($signature == null) {
            abort(422, 'signature required');
        }

        $params = $this->getParams();
        $hparams = hash_hmac('sha256', $params, $secret);

        if ($hparams !== $signature) {
            abort(422, 'signature invalid');
        }
    }

    public function getParams()
    {
        $path = request()->path();
        $method = request()->method();
        $params = request()->except(['signature']);
        $params = urldecode(http_build_query($params));
        $response = "$method {$path}?{$params}";
        logger($response);

        return "$method {$path}?{$params}";
    }
}
