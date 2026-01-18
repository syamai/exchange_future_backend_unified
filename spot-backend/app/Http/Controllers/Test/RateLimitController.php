<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RateLimitController extends Controller
{
    public function form()
    {
        return view('test.form');
    }

    public function runRateLimit(Request $request)
    {
        $params = $request->all();
        $url = $request->url;
        $rate = $request->rate;
        $time = $request->time;
        $APIKEY = $request->APIKEY;
        $signature = $request->signature;
        $method = $request->method;
        $data = $request->params ?? '';

        dump('REQUEST-With:');
        dump($params);

        $this->callCurl($url, $method, $rate, $time, $APIKEY, $signature, $data);
    }

    public function callCurl($url, $method, $rate, $time, $APIKEY, $signature, $data)
    {
        dump("===========================================================================================>");

        for ($i = 0; $i < $rate / 1; $i++) {
            $this->callSection($url, $method, 1, $time, $APIKEY, $signature, $data);
        }

        dump("===========================================================================================>");
        dump("END ===========================================================================================>");
    }

    public function callSection($url, $method, $rate, $time, $APIKEY, $signature, $data)
    {
        $url = $url.'&signature='.$signature;
        $method = strtoupper($method);
        $curlObjects = [];
        for ($i = 0; $i < $rate; $i++) {
            // create both cURL resources
            $ch1 = curl_init();

            // set URL and other appropriate options
            curl_setopt($ch1, CURLOPT_URL, $url);
            curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json',
                'APIKEY:'.$APIKEY,
                'signature:'.$signature,
                'Connection: Keep-Alive'
            ));
            curl_setopt($ch1, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch1, CURLOPT_TIMEOUT, 10000);
            curl_setopt($ch1, CURLOPT_HEADER, 0);

            $data = $data.'&signature='.$signature;
            curl_setopt($ch1, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch1, CURLOPT_CUSTOMREQUEST, $method);

            if ($method == 'POST') {
                curl_setopt($ch1, CURLOPT_POST, 1);
            }

            $curlObjects[] = $ch1;
        }

        //create the multiple cURL handle
        $mh = curl_multi_init();

        for ($i = 0; $i < $rate; $i++) {
            curl_multi_add_handle($mh, $curlObjects[$i]);
        }

        //execute the multi handle
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status == CURLM_OK);

        for ($i = 0; $i < $rate; $i++) {
            curl_multi_remove_handle($mh, $curlObjects[$i]);
        }
        curl_multi_close($mh);
        usleep(200);

        // all of our requests are done, we can now access the results
        for ($i = 0; $i < $rate; $i++) {
            dump("REQUEST - {$i}=====================================================================================>");
            dump($curlObjects[$i]);
            $response_1 = curl_multi_getcontent($curlObjects[$i]);
            dump($response_1);
        }
    }

    public function callAPI($url, $method, $headers, $data = null)
    {
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ];

        switch ($method) {
            case "POST":
                $options[CURLOPT_POST] = 1;
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case "GET":
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case "PUT":
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case "DELETE":
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }
        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $output = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($output);

        return $response;
    }


    private function sampleCallCurl()
    {
        // create both cURL resources
        $ch1 = curl_init();
        $ch2 = curl_init();

        // set URL and other appropriate options
        curl_setopt($ch1, CURLOPT_URL, "http://lxr.php.net/");
        curl_setopt($ch1, CURLOPT_HEADER, 0);
        curl_setopt($ch2, CURLOPT_URL, "http://www.php.net/");
        curl_setopt($ch2, CURLOPT_HEADER, 0);



        //create the multiple cURL handle
        $mh = curl_multi_init();

        //add the two handles
        curl_multi_add_handle($mh, $ch1);
        curl_multi_add_handle($mh, $ch2);

        //execute the multi handle
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status == CURLM_OK);

        //close the handles
        curl_multi_remove_handle($mh, $ch1);
        curl_multi_remove_handle($mh, $ch2);
        curl_multi_close($mh);
    }
}
