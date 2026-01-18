<?php

namespace PassportHmac\Http\Controllers;

use App\Http\Controllers\AppBaseController;
use PassportHmac\Http\Requests\HmacTokenCreateRequest;
use PassportHmac\Http\Services\HmacTokenService;
use Illuminate\Support\Facades\Auth;
use PassportHmac\Define;

class HmacTokenController extends AppBaseController
{
    protected $hmacTokenService;

    public function __construct(HmacTokenService $hmacTokenService)
    {
        $this->hmacTokenService = $hmacTokenService;
    }

    public function index()
    {
        try {
            $userId = auth('api')->id();
            $tokens = $this->hmacTokenService->index($userId);

            return $this->sendResponse($tokens);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function create()
    {
        $user = Auth::user();
        $params = request()->except(['signature']);

        try {
            if ($user->verifyOtp($params['otp'])) {
                $res = $this->hmacTokenService->create($user, $params);
                return $this->sendResponse($res);
            } else {
                $this->sendError(["message" => "Not pass Google Authen"]);
            }
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function createTokenPnlChart()
    {
        $user = Auth::user();
        $params = request()->except(['signature']);

        try {
            $res = $this->hmacTokenService->createTokenPnlChart($user, $params);
            return $this->sendResponse($res);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function store(HmacTokenCreateRequest $request)
    {
        $scope = $request->scope;
        $user = auth('api')->user();
        $params = request()->except(['signature']);

        try {
            if ($user->verifyOtp($params['otp'])) {
                $token = $this->hmacTokenService->store($user, $scope);
                return $this->sendResponse($token);
            } else {
                $this->sendError(["message" => "Not pass Google Authen"]);
            }
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function update($id)
    {
        $userId = auth('api')->id();
        $params = request()->except(['signature']);
        $user = auth('api')->user();

        try {
            if ($user->verifyOtp($params['otp'])) {
                $res = $this->hmacTokenService->update($id, $userId, $params);
                return $this->sendResponse($res);
            } else {
                $this->sendError(["message" => "Not pass Google Authen"]);
            }
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function destroy($id)
    {
        $userId = auth('api')->id();
        $user = auth('api')->user();
        $params = request()->except(['signature']);

        try {
            if ($user->verifyOtp($params['otp'])) {
                $res = $this->hmacTokenService->destroy($id, $userId);
                return $this->sendResponse($res);
            } else {
                $this->sendError(["message" => "Not pass Google Authen"]);
            }
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function listScopes() {
        $scopes = Define::LIST_SCOPE;
        return $this->sendResponse($scopes);
    }
}
