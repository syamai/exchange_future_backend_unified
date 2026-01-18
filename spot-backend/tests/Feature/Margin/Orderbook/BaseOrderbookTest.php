<?php

namespace Tests\Feature\Margin\Orderbook;

use Tests\Feature\Margin\BaseMarginTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use ExecutionService;
use OrderService;
use MatchingEngine;

class BaseOrderbookTest extends BaseMarginTest
{
    protected $symbol = 'BTCUSD';
    protected $userId = 1;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpAccount([
            'id' => $this->userId,
            'balance' => 1000000,
            'order_margin' => 100000,
            'available_balance' => 1000000,
        ]);
        $this->setUpInsuranceAccount();
        $this->setUpInstruments();
    }

    protected function clearData()
    {
        DB::table('margin_accounts')->truncate();
        DB::table('margin_orders')->truncate();
        DB::table('margin_trades')->truncate();
        DB::table('positions')->truncate();
        DB::table('margin_accounts')->truncate();
        DB::table('instruments')->truncate();
        DB::table('instrument_extra_informations')->truncate();
    }

    protected function getOrderData($data)
    {
        $data = parent::getOrderData($data);
        $data['account_id'] = $this->userId;
        return $data;
    }

    /**
     * Test order matching
     *
     * @return void
     */
    protected function doTest($inputs, $outputs)
    {
        foreach ($inputs as $input) {
            $order = OrderService::create($this->getOrderData($input));
            ExecutionService::activeOrder($order);
        }
        Artisan::call('margin:process_order', ['symbol' => $this->symbol]);
        $this->checkOrderbook($outputs);
    }

    protected function checkOrderbook($outputs)
    {
        $orderbook = OrderService::getOrderbook($this->symbol);
        $this->checkRows($orderbook['buy'], $outputs['buy']);
        $this->checkRows($orderbook['sell'], $outputs['sell']);
    }

    protected function checkRows($rows1, $rows2)
    {
        $this->assertSame(count($rows1), count($rows2));

        $count = count($rows1);
        for ($i = 0; $i < $count; $i++) {
            $this->checkRow($rows1[$i], $rows2[$i]);
        }
    }

    protected function checkRow($row1, $row2)
    {
        $this->assertSame(BigNumber::new($row1['price'])->toString(), BigNumber::new($row2['price'])->toString());
        $this->assertSame(BigNumber::new($row1['quantity'])->toString(), BigNumber::new($row2['quantity'])->toString());
        $this->assertSame(BigNumber::new($row1['count'])->toString(), BigNumber::new($row2['count'])->toString());
    }
}
