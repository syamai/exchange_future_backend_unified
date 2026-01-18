<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;

use App\Consts;

class SupportController extends Controller
{
    public function getSupport(Request $request): string|\Illuminate\Http\RedirectResponse
    {
        $key = config('app.zendesk_key');

        if (empty($key)) {
            return $this->redirectWithoutLogin($request);
        }
        $now        = time();
        $token      = array(
            'jti'   => md5($now . rand()),
            'iat'   => $now,
            'name'  => Auth::user()->name,
            'email' => Auth::user()->email
        );
        $domain = Consts::DOMAIN_SUPPORT;
        $jwt = JWT::encode($token, $key, Consts::DEFAULT_JWT_ALGORITHM);
        $location = "{$domain}/access/jwt?jwt={$jwt}";
        if ($request->has('return_to')) {
            $location = "{$location}&return_to={urlencode($request->return_to)}";
        }
        return $location;
    }

    public function supportAndLoginIfPossible(Request $request): string|\Illuminate\Http\RedirectResponse
    {
        if (Auth::check()) {
            return $this->getSupport($request);
        }
        return $this->redirectWithoutLogin($request);
    }

    private function redirectWithoutLogin(Request $request): \Illuminate\Http\RedirectResponse
    {
        $location = $request->has('return_to') ? $request->return_to : Consts::DOMAIN_SUPPORT;
        return redirect()->to($location);
    }

    public function getSupportLogin(Request $request): string|\Illuminate\Http\RedirectResponse
    {
        $key = config('app.zendesk_key');
        if (empty($key)) {
            return $this->redirectWithoutLogin($request);
        }
        $now        = time();
        $token      = array(
            'jti'   => md5($now . rand()),
            'iat'   => $now,
            'name'  => $request->get('email'),
            'email' => $request->get('email'),
        );
        $domain = Consts::DOMAIN_SUPPORT;
        $jwt = JWT::encode($token, $key, Consts::DEFAULT_JWT_ALGORITHM);
	    return "{$domain}/access/jwt?jwt={$jwt}";
    }
}
