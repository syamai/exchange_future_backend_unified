<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Validator;
use App\Utils\BearerToken;
use Illuminate\Support\Facades\Auth;

class AuthenticateMessage
{
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::check() && $request->bearerToken()) {
            $token = BearerToken::fromRequest();
            $this->verifySignature($token->secret);
        }
        return $next($request);
    }

    public function verifySignature($secret)
    {
        $validator = Validator::make(request()->all(), [
            'signature' => [
                'required',
                function ($attribute, $value, $fail) use ($secret) {
                    $path = request()->path();
                    $method = request()->method();
                    $params = request()->except([$attribute]);
                    $params = urldecode(http_build_query($params));
                    $payload = "$method {$path}?{$params}";

                    logger('verifySignature');
                    logger($payload);
                    logger(hash_hmac('sha256', $payload, $secret));
                    if ($value !== hash_hmac('sha256', $payload, $secret)) {
                        return $fail("{$attribute} is invalid");
                    }
                },
            ],
        ]);
        $validator->validate();
    }
}
