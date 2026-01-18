<?php


namespace PassportHmac\Http\Services;

use Illuminate\Support\Facades\Route;
use PassportHmac\Define;

class PermissionService
{
    public function checkIsPermissionScope($tokeScope)
    {
        $scopes = Define::SCOPES;

        // MY SCOPES NOT IN PERMISSION TOKEN SCOPE
        if (!$this->isNormalScope($tokeScope, $scopes)) {
            return false;
        }

        // Access to all
        if (!$this->isPermissionScope($tokeScope)) {
            return true;
        }

        $routeScope = $this->getRouteScope($scopes);

        if ($this->isPermissionScope($routeScope)) {
            if (!$routeScope) {
                abort(403, 'Access denied');
            }
            $acceptScopeList = Define::SCOPE_GROUP[$routeScope];

            if (in_array($tokeScope, $acceptScopeList)) {
                return true;
            }

            abort(403, 'Access denied');
        }

        return false;
    }

    public function isNormalScope($tokeScope, $scopes)
    {
        return in_array($tokeScope, $scopes);
    }

    public function isPermissionScope($routeScope)
    {
        return $routeScope !== '*';
    }

    public function getRouteScope($scopes)
    {
        //TODO: this method gonna lead to some errors if the scope is more one permission. Double check this.
        $name = Route::currentRouteName();

        if ($this->withdraw($name)) {
            return $scopes[2];
        }

        if ($this->trade($name)) {
            return $scopes[1];
        }

        if ($this->read()) {
            return $scopes[0];
        }

        return '';
    }

    public function read()
    {
        return true;
//        $method = request()->method();
//        return $method === 'GET';
    }

    public function withdraw($name)
    {
        $routeNames = Define::ROUTE_GROUP[Define::WITHDRAW];
        return in_array($name, $routeNames);
    }

    public function trade($name)
    {
        $routeNames = Define::ROUTE_GROUP[Define::TRADE];

        return in_array($name, $routeNames);
    }
}
