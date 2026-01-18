<?php

namespace App\Http\Middleware;

use App\Consts;
use App\Utils;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DecryptParamsMiddleware
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
        if ($request->header(Consts::API_KEY, null)) {
            $signature = $request->header(Consts::SIGNATURE_HEADER, null) ?? null;
            logger()->info('SIGNATURE======' . json_encode($signature));
            if (!$signature) {
                throw new \Exception('fail to resquest');
            }
            // Decrypt AES
//            $decryptData = Utils::decryptAES($signature);
            $now = Carbon::now()->timestamp;
            // $decryptData['time'] must be unix time second
            $timeEncrypt = (float)$request->header(Consts::TIMESTAMP_HEADER) ?? null;
            if (!$timeEncrypt) {
                throw new \Exception('Signature missing data');
            }
            if ($now - $timeEncrypt > Consts::EXPIRED_DECRYPT_DATA) {
                throw new \Exception("Data expire time");
            }
            // get params compare (params request compare with params in signature)
//            $params = $request->except('signature');
//            $paramsDecrypt = Arr::except($decryptData, ['time']);
            $params = $request->all();
            $params = array_merge($params, ['timestamp' => $timeEncrypt]);
            logger()->info("PARAMS=======" . json_encode($params));
            $hashParams = hash('sha256', json_encode($params));
            logger()->info("HASH PARAMS=======" . json_encode($hashParams));

            if (!$this->compareSHA256($signature, $hashParams)) {
                throw new \Exception('Invalid signature');
            }
        }

        return $next($request);
    }

    public function compareSHA256(string $signature, string $hashParams): bool
    {
        return $signature === $hashParams;
    }

    public function checkData(array $params, array $paramsDecrypt): bool
    {
        $base64Params = base64_encode(json_encode($params));
        $base64Decrypt = base64_encode(json_encode($paramsDecrypt));
        if ($base64Decrypt !== $base64Params) {
            return false;
        }

        return true;
    }
}
