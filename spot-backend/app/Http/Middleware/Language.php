<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\App;
use App\Utils;
use Closure;
use Illuminate\Support\Facades\View;
use App\Http\Services\MasterdataService;

class Language
{
    public function handle($request, Closure $next)
    {
        Utils::setLocale($request);
        return $next($request);
    }
}
