<?php


namespace App\Http\Services;


use App\Models\IpStackLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IpStackClientService
{

    private function getApiKey()
    {
        return env("IP_STACK_KEY", "");
    }

    public function getInfo($ip)
    {
        $ipStackLog = IpStackLog::where('ip', $ip)->first();
        if (!$ipStackLog) {
            $apiKey = $this->getApiKey();
            if (!$apiKey) {
                throw new HttpException(422, __('exception.ip_stack_not_config'));
            }
            $client = new Client();
            $url = "http://api.ipstack.com/{$ip}?access_key={$apiKey}";
            try {
                $response = $client->get($url, [
                    /*'headers' => [
                        "Accept" => "application/json"
                    ]*/
                ]);
                if ($response->getStatusCode() != 200) {
                    throw new HttpException(400, __('exception.ip_stack_not_work'));
                }

                $data = json_decode($response->getBody()->getContents());
                if(isset($data->error)) {
                    throw new HttpException(400, "{$data->error->type}: {$data->error->info}");
                }

                $data = [
                    'ip' => $data->ip,
                    'region_name' => $data->region_name,
                    'region_code' => $data->region_code,
                    'country_name' => $data->country_name,
                    'country_code' => $data->country_code,
                    'latitude' => $data->latitude,
                    'longitude' => $data->longitude,
                    //'location' => $data->location
                ];

                IpStackLog::create($data);

                return $data;

            } catch (GuzzleException $e) {
                throw new HttpException(400, __($e->getMessage()));
            } catch (\Exception $ex) {
                throw new HttpException(400, __($ex->getMessage()));
            }
        }

        return [
            'ip' => $ipStackLog->ip,
            'region_name' => $ipStackLog->region_name,
            'region_code' => $ipStackLog->region_code,
            'country_name' => $ipStackLog->country_name,
            'country_code' => $ipStackLog->country_code,
            'latitude' => $ipStackLog->latitude,
            'longitude' => $ipStackLog->longitude,
            //'location' => $ipStackLog->location
        ];

    }

}