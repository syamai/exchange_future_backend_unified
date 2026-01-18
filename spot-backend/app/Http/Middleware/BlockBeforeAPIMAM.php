<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use App\Consts;
use App\Models\MamSetting;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BlockBeforeAPIMAM
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $lockProcess = MamSetting::firstOrCreate(['key' => Consts::MAM_LOCK_PROCESS]);

        DB::transaction(function () use ($lockProcess) {
            $lockProcess = MamSetting::lockForUpdate()->find($lockProcess->id);
            if ($lockProcess->value) {
                throw new HttpException(422, __('exception.mam.block_api'));
            }
            $lockProcess->value = Consts::MAM_PROCESS_API;
            $lockProcess->save();
        });

        return $next($request);
    }
}
