<?php

namespace Tests\Feature;

use App\Consts;
use App\Models\User;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WithdrawTest extends TestCase
{
    public $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImYyODE4ZWEyMmYzNzdiNDczODk1NThmNjliZGFjZTczNTNhNzdmZTFkMmQxMmYzNDdjZjkxMzIwMmNlYmU1N2I0NTZlNzIzMTkxZDE1YTU5In0.eyJhdWQiOiIxIiwianRpIjoiZjI4MThlYTIyZjM3N2I0NzM4OTU1OGY2OWJkYWNlNzM1M2E3N2ZlMWQyZDEyZjM0N2NmOTEzMjAyY2ViZTU3YjQ1NmU3MjMxOTFkMTVhNTkiLCJpYXQiOjE1NjQ2MzkzMjMsIm5iZiI6MTU2NDYzOTMyMywiZXhwIjoxNTk2MjYxNzIzLCJzdWIiOiIxIiwic2NvcGVzIjpbIioiXX0.xiCno703_blfi6Y1fp7ODvc8-9cQGowIhJPcq1vinPZqsR8IVA1svbabSh_njKFGJNtj0AjSZYNuWfSrroEDRciqCuC5KxbLht9-w8-FMWaDhbJo9ntzqp-829BCQCB4au4d1Yulzr4BIeNgN9bwhiE_4VMo7lpq4WJnlRdh4PJtIEqOmkT_yrhR9FHbx5t_JkGvr9oEq_YmJmT3Mi2oLJw1XNdIRKbO1oMtw-2ntmTHOJ1Y9fHzoCo5O3D_QbQXs5ly3oXbuvBfFWlBfQvOgkg9Qvvg4Qq0gh-GZPLsJVjBF2rNadHJFtkGHnp7WjeNqXHBhfgXEZYrN3yd_kV7reCWomWTMMV8XNnyZU7x7tLYiotGDBUu1IcjKSNNHTqGMZnkgSFwEUjgbXsnmBvjRXI7kTRdJ8d_VNwRTZxukQVs3hmxDK7__fqNVvEtiDUvkUfu7NdNNQvDEdDQMb0ALW8sMsppYeEUei9zFEJcVNhf1eYkbCkLY-UuRZfzskLFgypGKBMERbGa8DftVTspnef5JeJ_N0iH7uX_72hZhE2TA78EosKFQ5H7cHVbTqPvf7zQuImA2a8W9BCfdCXs19kdWueqSCfbPvKrMpBnMBlPxh_XJSJ7oYy8omCYwFWJiESzWsgieVGUELM5vUT_Lo_HX4frbgu_BsECRFeWl3o';
    public $otp = '687346';

    public function getHeader()
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}"
        ];
    }

    public function getOtp()
    {
        return $this->otp;
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBTC()
    {
        $header = $this->getHeader();
        $otp = $this->getOtp();
        $amount = -0.1;
        $currency = 'btc';
        $address = 'n4VQ5YdHf7hLQ2gWQYYrcxoE5B7nWuDFNF';

        $params = [
            'blockchain_address' => $address,
            'currency' => $currency,
            'amount' => $amount,
            'otp' => $otp
        ];

        $accountBefore = DB::table('btc_accounts')->find(1);

        $response = $this
            ->post('api/v1/withdraw', $params, $header);

        $json = ['data' => [
            'to_address' => $address,
            'currency' => $currency,
            'amount' => $amount,
            'status' => Consts::ORDER_STATUS_PENDING
        ]];

        $this->testBalance($accountBefore, $response);

        $response->assertJson($json);
    }

    public function testBalance($accountBefore, $response)
    {
        $result = json_decode($response->getContent(), true)['data'];

        $fee = $result['fee'];
        $payment = BigNumber::new($result['amount'])->sub($fee)->toString();

        $accountAfter = DB::table('btc_accounts')->find(1);

        if ($accountBefore->balance === $accountAfter->balance) {
            echo "Balance pass \n";
        } else {
            echo "Balance fail \n";
        }

        $availableBalance = BigNumber::new($accountBefore->available_balance)->add($payment)->toString();

        if ($accountAfter->available_balance == $availableBalance) {
            echo "Available balance pass \n";
        } else {
            echo "Available balance fail: {$availableBalance} \n";
        }
    }

    public function testEth()
    {
        $header = $this->getHeader();
        $otp = $this->getOtp();
        $amount = -0.1;
        $currency = 'eth';
        $address = '0x0282b0620Cc8e994b7B8647a09D11756a27a0977';

        $params = [
            'blockchain_address' => $address,
            'currency' => $currency,
            'amount' => $amount,
            'otp' => $otp
        ];

        $accountBefore = DB::table('btc_accounts')->find(1);

        $response = $this
            ->post('api/v1/withdraw', $params, $header);

        $response->assertJson([
            'to_address' => $address,
            'currency' => $currency,
            'amount' => $amount,
        ]);

        $this->testBalance($accountBefore, $response);
    }
}
