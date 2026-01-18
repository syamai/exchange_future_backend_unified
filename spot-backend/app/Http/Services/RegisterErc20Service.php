<?php
/**
 * Created by PhpStorm.
 * Date: 6/28/19
 * Time: 1:01 PM
 */

namespace App\Http\Services;

use App\Events\CreateAccountErc20;
use App\Events\FinishedRegisterErc20;
use App\Models\Coin;
use App\Models\CoinsConfirmation;
use App\Models\CoinSetting;
use App\Models\MarketFeeSetting;
use App\Models\PriceGroup;
use App\Models\WithdrawalLimit;
use App\Utils;
use AWS\CRT\Log;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use SotaWallet\Logger;
use SotaWallet\SotaWalletService;

class RegisterErc20Service
{
    const MIGRATION_EXAMPLE_FILE = 'example/2019_03_19_212916_create_example_accounts_table.php';
    const ERC20_MIGRATE_PATH = 'migrations/erc20/';
    const STORAGE_SERVICE = 's3';

    public function register($params)
    {
        $coin = strtolower(Str::camel(data_get($params, 'coin_setting.symbol')));
        $tableName = Str::of((ucfirst($coin) . 'Accounts'))->snake();
        $fileName = Carbon::now()->format('Y_m_d_Hms') . "_create_{$coin}_accounts_table.php";
        $isRunMigrate = false;
        try {
            if (!$this->isExistedMigration("_create_{$coin}_accounts_table.php")) {
                $this->makeMigration($coin, $tableName, $fileName);
                $isRunMigrate = true;
            }

            if (!Schema::hasTable($tableName)) {
                Artisan::call('migrate', ['--path' => "/database/" . self::ERC20_MIGRATE_PATH, '--force' => true,]);
                $this->registerWallet($params);
            } else {
                if ($isRunMigrate) {
                    $this->deleteMigration($fileName);
                }
                return [
                    'error' => 'symbol.exist',
                    'message' => 'Symbol already exists',
                    'status' => false
                ];
            }

            return [
                'error' => null,
                'message' => 'Register successfully',
                'status' => true
            ];

        } catch (\Exception $e) {
            $this->rollbackMigrate($coin, $tableName, $fileName);
            logger($e);
            throw $e;
        }
    }

    public function registerWallet($params): void
    {
        logger('registerWallet');
        $contractAddress = data_get($params, 'coin_setting.contract_address');

        $walletParams = [
            "custom_symbol" => data_get($params, 'coin_setting.symbol'),
            "contract_address" => $contractAddress
        ];

        $network = data_get($params, 'coin_setting.network');
        SotaWalletService::registerErc20($walletParams, $network);
        $this->updateMasterData($params, $network);
    }

    private function isExistedMigration($name): bool
    {
        $path = self::ERC20_MIGRATE_PATH;
        $fileNames = scandir(database_path($path));

        return Str::contains(implode(" ", $fileNames), $name);
    }

    private function makeMigration($coin, $tableName, $fileName): void
    {
        if (!$this->isExistedMigration("_create_{$coin}_accounts_table.php")) {
            File::copy(database_path(self::ERC20_MIGRATE_PATH . self::MIGRATION_EXAMPLE_FILE), database_path(self::ERC20_MIGRATE_PATH . $fileName));
            $fileContent = file_get_contents(database_path(self::ERC20_MIGRATE_PATH . $fileName));
            $fileContent = str_replace('ExampleAccounts', ucfirst($coin) . 'Accounts', $fileContent);
            $fileContent = str_replace('example_accounts', $tableName, $fileContent);
            file_put_contents(database_path(self::ERC20_MIGRATE_PATH . $fileName), $fileContent);
        }
    }

    private function rollbackMigrate($coin, $tableName, $fileName): void
    {
        $tableNameSpot = "spot_{$coin}_accounts";
        $tbM = trim(rtrim($fileName, '.php'));

        File::delete(database_path(self::ERC20_MIGRATE_PATH . $fileName));

        DB::table('migrations')->where('migration', $tbM)->delete();
        Schema::dropIfExists($tableName);
        Schema::dropIfExists($tableNameSpot);
    }

    private function deleteMigration($fileName): void {
        $tbM = trim(rtrim($fileName, '.php'));
        File::delete(database_path(self::ERC20_MIGRATE_PATH . $fileName));
        DB::table('migrations')->where('migration', $tbM)->delete();
    }

    private function updateMasterData($params, $network = null): void
    {
        logger('updateMasterData');

        try {
            DB::beginTransaction();

            $coinSetting = Arr::get($params, 'coin_setting', []);
            $coin = strtolower(Arr::get($coinSetting, 'symbol'));
            $tradingPairs = Arr::get($params, 'trading_setting', []);
            $withdrawalSetting = Arr::get($params, 'withdrawal_setting', []);
            $currency = Arr::get($withdrawalSetting, 'currency');

            $this->updateCoinSetting($coinSetting, $coin, $network);
            $this->updateTradingPairSetting($tradingPairs);
            $this->updateWithdrawalSetting($withdrawalSetting, $currency);
            $this->updateCoinConfirmation($coin);

            $this->setMarket($tradingPairs);
            $this->createAccountErc20($coin, $network);
            $this->finish($tradingPairs);

            DB::commit();

            $this->cacheClear();
        } catch (\Exception $e) {
            DB::rollBack();
            Logger::error('updateMasterData:', [$e->getMessage()]);
            throw $e;
        }
    }

    public function cacheClear(): void
    {
        MasterdataService::clearCacheOneTable('coins');
        MasterdataService::clearCacheOneTable('coins_confirmation');
        MasterdataService::clearCacheOneTable('coin_settings');
        MasterdataService::clearCacheOneTable('market_fee_setting');
        MasterdataService::clearCacheOneTable('price_groups');
        MasterdataService::clearCacheOneTable('withdrawal_limits');

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
    }

    private function setMarket($tradingPairs): void
    {
        event(new \App\Events\SetMarketPriceErc20());

        foreach ($tradingPairs as $tradingPair) {
            DB::table("prices")->insert([
                'currency' => strtolower(Arr::get($tradingPair, 'currency')),
                'coin' => strtolower(Arr::get($tradingPair, 'coin')),
                'price' => Arr::get($tradingPair, 'market_price'),
                'quantity' => '0',
                'amount' => '0',
                'is_market' => 1,
                'created_at' => Utils::currentMilliseconds()
            ]);
        }
    }

    private function createAccountErc20($coin, $network = null): void
    {
        event(new CreateAccountErc20());

        $table = 'eth_accounts';
        if ($network) {
            $table = strtolower($network).'_accounts';
        }

        DB::insert("insert into {$coin}_accounts(id, blockchain_address) select id, blockchain_address from {$table}");
        DB::insert("insert into spot_{$coin}_accounts(id, blockchain_address) select id, blockchain_address from {$table}");
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

    private function updateWithdrawalSetting($withdrawalSetting, $currency): void
    {
        logger('updateWithdrawalSetting');

        if (DB::table('withdrawal_limits')->where('currency', $currency)->count() === 0) {
            $levels = 4;
            $masterData = $this->checkMasterFileExist();

            for ($i = 1; $i <= $levels; $i++) {
                $data = [
                    'security_level' => $i,
                    'currency' => strtolower($currency),
                    'limit' => Arr::get($withdrawalSetting, 'limit' . $i),
                    'daily_limit' => Arr::get($withdrawalSetting, 'daily_limit' . $i),
                    'fee' => Arr::get($withdrawalSetting, 'fee'),
                    'minium_withdrawal' => Arr::get($withdrawalSetting, 'minium_withdrawal' . $i),
                    'days' => 0,
                ];
                $result = WithdrawalLimit::create($data);
                $masterData->withdrawal_limits[] = $result;
            }
            $this->putFileContent($masterData);
        }
    }

    private function updateTradingPairSetting($tradingPairs): void
    {
        logger('updateTradingPairSetting');

        foreach ($tradingPairs as $tradingPair) {
            $coin = strtolower(Arr::get($tradingPair, 'coin'));
            $currency = strtolower(Arr::get($tradingPair, 'currency'));

            logger($tradingPair);
            $this->marketFeeSetting($coin, $currency, $tradingPair);

            $this->insertCoinSettings($coin, $currency, $tradingPair);
            logger('insertCoinSettings');

            $this->insertPriceGroups($coin, $currency, $tradingPair);
            logger('insertPriceGroups');
        }
    }

    private function marketFeeSetting($coin, $currency, $tradingPair): void
    {
        logger('marketFeeSetting', compact('coin', 'currency', 'tradingPair'));

        if (DB::table('market_fee_setting')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }

        $data = [
            'currency' => $currency,
            'coin' => $coin,
            'fee_taker' => Arr::get($tradingPair, 'taker_fee'),
            'fee_maker' => Arr::get($tradingPair, 'maker_fee')
        ];

        MarketFeeSetting::create($data);
    }

    private function insertCoinSettings($coin, $currency, $tradingPair): void
    {
        logger('insertCoinSettings', compact('coin', 'currency', 'tradingPair'));

        if (DB::table('coin_settings')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }

        $data = [
            'coin' => $coin,
            'currency' => $currency,
            'minimum_quantity' => Arr::get($tradingPair, 'minimum_quantity'),
            'quantity_precision' => Arr::get($tradingPair, 'quantity_precision'),
            'price_precision' => Arr::get($tradingPair, 'price_precision'),
            'minimum_amount' => Arr::get($tradingPair, 'minimum_amount'),
            'is_enable' => 0,
            'zone' => Arr::get($tradingPair, 'zone')
        ];
        $result = CoinSetting::create($data);
        $masterData = $this->checkMasterFileExist();
        $masterData->coin_settings[] = $result;
        $this->putFileContent($masterData);
    }

    private function insertPriceGroups($coin, $currency, $tradingPair): void
    {
        logger('insertCoinSettings', compact('coin', 'currency', 'tradingPair'));

        if (DB::table('price_groups')->where('coin', $coin)->where('currency', $currency)->count() > 0) {
            return;
        }

        $precision = @$tradingPair['precision'] ?? '0.0001';

        $data = [
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 3,
                'value' => ($precision *= 10)
            ],
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 2,
                'value' => ($precision *= 10)
            ],
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 1,
                'value' => ($precision *= 10)
            ],
            [
                'coin' => $coin,
                'currency' => $currency,
                'group' => 0,
                'value' => ($precision *= 10)
            ],
        ];
        $masterData = $this->checkMasterFileExist();
        foreach ($data as $datum) {
            $result = PriceGroup::create($datum);
            $masterData->price_groups[] = $result;
        }
        $this->putFileContent($masterData);
    }

    private function updateCoinSetting($coinSetting, $coin, $network = null): void
    {
        $data = [
            'coin' => $coin,
            'icon_image' => $this->formatBase64(Arr::get($coinSetting, 'image_base64')),
            'name' => Arr::get($coinSetting, 'name'),
            'confirmation' => Arr::get($coinSetting, 'required_confirmations', 12),
            'contract_address' => Arr::get($coinSetting, 'contract_address'),
            'type' => $network ? strtolower($network) . '_token' : 'eth_token',
            'trezor_coin_shortcut' => 'eth',
            'trezor_address_path' => 'm/44\'/60\'/{$account}\'/0/{$i}',
            'env' => config('blockchain.network'),
            'transaction_tx_path' => Arr::get($coinSetting, 'explorer', 12) . '/tx/{$transaction_id}',
            'transaction_explorer' => Arr::get($coinSetting, 'explorer', 12),
            'decimal' => Arr::get($coinSetting, 'decimals', 8)
        ];
        $result = Coin::insert($data);
        $masterData = $this->checkMasterFileExist();
        logger('=============== Master data =================', [$masterData]);
        $masterData->coins[] = $result;
        $this->putFileContent($masterData);
    }

    public function formatBase64($image, $width = 100, $height = 100, $tail = 'png'): string
    {
        $img = Image::make($image);
        $img->resize($width, $height);
        return "data:image/png;base64," . base64_encode($img->stream($tail));
    }

    private function updateCoinConfirmation($coin): void
    {
        logger('updateCoinConfirmation');

        $data = [
            'coin' => $coin,
            'confirmation' => 6,
            'is_withdraw' => 1,
            'is_deposit' => 1
        ];

        CoinsConfirmation::create($data);
    }

    private function finish($tradingPairs)
    {
        foreach ($tradingPairs as $index => $tradingPair) {
            $coin = strtolower(Arr::get($tradingPair, 'coin'));
            $currency = strtolower(Arr::get($tradingPair, 'currency'));

            logger('Redis::publish StartNewOrderProcessor', [
                'coin' => $coin,
                'currency' => $currency
            ]);

            $data = collect([
                'coin' => $coin,
                'currency' => $currency
            ])->toJson();

            Redis::connection('order_processor')->publish('StartNewOrderProcessor', $data);
        }

        event(new FinishedRegisterErc20());
    }
}
