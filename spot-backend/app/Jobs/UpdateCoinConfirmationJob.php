<?php

namespace App\Jobs;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use SotaWallet\SotaWalletRequest;
use Illuminate\Support\Facades\Log;
use App\Events\CreateAccountErc20;
use App\Models\Network; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\CoinsConfirmation;
use App\Http\Services\MasterdataService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Models\WithdrawalLimit;

class UpdateCoinConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    const STORAGE_SERVICE = 's3';
    protected $params;

    /**
     * Create a new job instance.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     * @throws Exception
     */
    public function handle()
    {
        try {
            $coin = data_get($this->params, 'coin');
            $networkData = data_get($this->params, 'network_coins');
            $type = data_get($this->params, 'type');
            $this->updateMasterData($coin, $networkData, $type);

        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    private function updateMasterData($coin, $networkData, $type): void
    {
        try {
            DB::beginTransaction();
          
            $this->updateCoinConfirmation($coin, $networkData);
            if ($type == 'create') {
                $this->createAccountCoin($coin);
            }
            //$this->updateWithdrawalSetting($coin);

            DB::commit();

            $this->cacheClear();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function updateCoinConfirmation($coin, $networkData): void
    {
        $isWithdraw = 0;
        $isDeposit  = 0;
        $depositConfirm = 0;
        foreach ($networkData as $networkDataElement) {
            if ($networkDataElement['network_deposit_enable'] == 1) {
                $isDeposit = 1;
                $network = Network::find($networkDataElement['network_id']);
                $depositConfirm = $network->deposit_confirmation;
            }
            if ($networkDataElement['network_withdraw_enable'] == 1) {
                $isWithdraw = 1;
            }
            if ($networkDataElement['network_deposit_enable'] == 1 && 
            $networkDataElement['network_withdraw_enable'] == 1) {
                $isDeposit = 1;
                $isWithdraw = 1;
                break;
            }
        }
      
        $coinExists = CoinsConfirmation::where('coin', $coin)->exists();
       
        if (!$coinExists) {
            $data = [
                'coin' => $coin,
                'confirmation' => $depositConfirm,
                'is_withdraw' => $isWithdraw,
                'is_deposit' => $isDeposit
            ];
            
            CoinsConfirmation::create($data);

        } else {
            CoinsConfirmation::where('coin', $coin)->update([
                'confirmation' => $depositConfirm,
                'is_withdraw' => $isWithdraw,
                'is_deposit' => $isDeposit
            ]);
        }
    }


    private function createAccountCoin($coin): void
    {
        $coin = strtolower($coin);
        $table = $coin . '_accounts';
        $spotTable = 'spot_' . $coin . '_accounts';  
        if (Schema::hasTable($table)) {    
            DB::insert("insert into {$coin}_accounts(id) select id from usdt_accounts");  
        } 
           
        if (Schema::hasTable($spotTable)) {     
            DB::insert("INSERT INTO {$spotTable} (id) SELECT id FROM {$table}");
        } else {
            Log::warning("Table '{$spotTable}' does not exist");
        }
    }


    public function cacheClear(): void
    {
        MasterdataService::clearCacheOneTable('coins');
        MasterdataService::clearCacheOneTable('coins_confirmation');

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
    }

    private function updateWithdrawalSetting($currency): void
    {
        $withdrawalUsdt = WithdrawalLimit::where('currency', 'usdt')->get();
        if (DB::table('withdrawal_limits')->where('currency', $currency)->count() === 0
            && $withdrawalUsdt->count() === 4) {
            $levels = 4;
            $masterData = $this->checkMasterFileExist();

            for ($i = 0; $i < $levels; $i++) {
                $data = [
                    'security_level' => $i + 1,
                    'currency' => strtolower($currency),
                    'limit' => $withdrawalUsdt[$i]['limit'],
                    'daily_limit' => $withdrawalUsdt[$i]['daily_limit'],
                    'fee' => $withdrawalUsdt[$i]['fee'],
                    'minium_withdrawal' => $withdrawalUsdt[$i]['minium_withdrawal'],
                    'days' => 0,
                ];
                $result = WithdrawalLimit::create($data);
                $masterData->withdrawal_limits[] = $result;
            }
            $this->putFileContent($masterData);
        }
    }

    private function checkMasterFileExist() {
        $file = Storage::disk(self::STORAGE_SERVICE)->exists('masterdata/latest.json');
        if (!$file) {
            $fileLocal = Storage::disk('local')->exists('masterdata/latest.json');
            if ($fileLocal) {
                $fileLocalContent = Storage::disk('local')->get('masterdata/latest.json');
                Storage::disk(self::STORAGE_SERVICE)->put('masterdata/latest.json', $fileLocalContent);
            } else {
                throw new \Exception('masterdata/latest.json not found');
            }
        }
        $file = Storage::disk(self::STORAGE_SERVICE)->get('masterdata/latest.json');
        return json_decode($file);
    }

    private function putFileContent($masterData) {
        return Storage::disk(self::STORAGE_SERVICE)->put('masterdata/latest.json', json_encode($masterData, JSON_PRETTY_PRINT));
    }
}
