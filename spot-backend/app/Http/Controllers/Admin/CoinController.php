<?php

namespace App\Http\Controllers\Admin;

use App\Models\WithdrawalLimit;
use App\Utils\BigNumber;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Models\Coin; 
use App\Models\Network; 
use App\Models\NetworkCoin; 
use Illuminate\Support\Facades\Validator;
use App\Http\Services\MasterdataService;
use Illuminate\Support\Arr;
use App\Consts;
use Illuminate\Support\Facades\Log;
use App\Jobs\RegisterTokenNetworkJob;
use App\Jobs\UpdateCoinConfirmationJob;
use SotaWallet\SotaWalletService;
use App\Models\CoinsConfirmation;
use SotaWallet\SotaWalletRequest;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class CoinController extends AppBaseController
{

    const MIGRATION_EXAMPLE_FILE = 'example/2019_03_19_212916_create_example_accounts_table.php';
    const ERC20_MIGRATE_PATH = 'migrations/erc20/';

    /**
     * Display the specified coin.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getCoinById($id)
    {
        $coin = Coin::select('id', 'name', 'coin', 'usd_price', 'decimal', 'is_fixed_price', 'status', 'icon_image') // Include 'id'
            ->with(['networkCoins' => function ($query) {
                $query->select(
                    'id',
                    'network_withdraw_enable', 
                    'network_deposit_enable', 
                    'network_enable', 
                    'contract_address', 
                    'network_id', 
                    'withdraw_fee', 
                    'min_deposit', 
                    'min_withdraw',
                    'decimal',
                    'token_explorer_url',
                    'coin_id' // Needed to link the network coins to the coin
                );
            }])
            ->find($id);


        if (!$coin) {
            return $this->sendError('Coin not found');
        }

		$coin = $this->getWithdrawLimits($coin);

        return $this->sendResponse($coin);
    }

    private function getWithdrawLimits($coin)
	{
		$coin->limit1 = 0;
		$coin->limit2 = 0;
		$coin->limit3 = 0;
		$coin->limit4 = 0;

		// get withdraw limits
		$withdrawLimits = WithdrawalLimit::where('currency', $coin->coin)->get();
		foreach ($withdrawLimits as $withdrawLimit) {
			$coin->{'limit' . $withdrawLimit->security_level} = BigNumber::new($withdrawLimit->limit)->toString();
		}
		return $coin;
	}

    public function getCoinsWithPagination(Request $request)
    {
        $coins = Coin::select('id', 'name', 'coin', 'usd_price', 'decimal', 'is_fixed_price',
        'updated_at', 'status')
            ->with(['networkCoins' => function ($query) {
                $query->select(
                    'network_withdraw_enable', 
                    'network_deposit_enable', 
                    'network_enable', 
                    'contract_address', 
                    'network_id', 
                    'withdraw_fee', 
                    'min_deposit', 
                    'min_withdraw', 
                    'coin_id',
                    'decimal',
                    'token_explorer_url'
                );
            }]) 
            ->withCount(['networkCoins as enable' => function ($query) {
                $query->where('network_enable', 1);
            }]);

       
        $input = $request->all();
        $limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
        $data = $coins->filter($input)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate($limit); 
        $data->transform(function ($coin) {
            $coin->enable = $coin->enable > 0 ? 1 : 0; 
            return $coin;
        });
        return $this->sendResponse($data);
    }

    public function dispatchRegisterTokenJob($networkCoinData)
    {
        $network = Network::find($networkCoinData['network_id']);
       
        if ($network) {
            $tokenParams = [
                'contract_address' => $networkCoinData['contract_address'],
                'symbol' => $network->symbol
            ];
            RegisterTokenNetworkJob::dispatch($tokenParams);
        }
    }

    public function dispatchUpdateCoinConfirmationJob($coin, $networkCoinData, $type)
    {
        $params = [
            'coin' => $coin,
            'network_coins' => $networkCoinData,
            'type' => $type
        ];
        UpdateCoinConfirmationJob::dispatch($params);
    }

    public function validateContractAddress(Request $request)
    {
        try {
            $contractAddress = $request->input('contract_address');
            $networkId = $request->input('network_id');
            $network = Network::find($networkId);
            $networkSymbol = $network->symbol;
            
            if ($contractAddress == $network->network_code) {
                $coinNetwork = [
                    "network"=> env('MIX_BLOCKCHAIN_NETWORK')
                ];
                return $this->sendResponse($coinNetwork);
            }
            $requestPath = "/api/currency_config/{$networkSymbol}.{$contractAddress}";
            $count = $this->countCurrency($networkSymbol);
            if ($count > 0) {
                $data['error'] = 'symbol.exist';
                return $this->sendError($data);
            }
            $res = SotaWalletRequest::sendRequest('GET', $requestPath);
            return $this->sendResponse(json_decode($res->getBody()->getContents()));
        } catch (\Exception $e) {
            
            return $this->sendError($e->getMessage());
        }
    }

    public function migrateWhenCreateToken($coinSymbol)
    {
        try {
            $migratePath = self::ERC20_MIGRATE_PATH;
            $tableName = Str::of((ucfirst($coinSymbol) . 'Accounts'))->snake();
          
            $fileName = Carbon::now()->format('Y_m_d_Hms') . "_create_{$coinSymbol}_accounts_table.php";
            $isRunMigrate = false;

            if (!$this->isExistedMigration("_create_{$coinSymbol}_accounts_table.php", $migratePath)) {
                $this->makeMigration($coinSymbol, $tableName, $fileName, $migratePath);
                $isRunMigrate = true;
            }
            
            if (!Schema::hasTable($tableName)) {
                Artisan::call('migrate', ['--path' => "/database/" . $migratePath, '--force' => true,]);
            } else {
                if ($isRunMigrate) {
                    $this->deleteMigration($fileName, $migratePath);
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
            $this->rollbackMigrate($coinSymbol, $tableName, $fileName, $migratePath);  
            return [
                'error' => null,
                'message' => $e->getMessage(),
                'status' => false
            ];
        }
    }

    private function updateWithdrawalLimits($coin, $request, $created = true)
	{
		$levels = 4;
		for ($i = 1; $i <= $levels; $i++) {
			$limit = $request->{'limit'. $i} ?? null;
			if (!is_null($limit) || $created) {
				if ($limit < 0) {
					$limit = 0;
				}
				$data = [

					'limit' => $limit,
					'daily_limit' => $limit,
					'fee' => '0.00001',
					'minium_withdrawal' => "0.001",
					'days' => 0,
				];

				WithdrawalLimit::updateOrCreate(
					[
						'security_level' => $i,
						'currency' => $coin,
					],
					$data
				);
			}
		}
	}

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'coin' => 'required|string|max:20|unique:coins,coin',
                'usd_price' => 'nullable|numeric|min:0',
                //'decimal' => 'required|integer|min:0',
                'is_fixed_price' => 'required|boolean',
                'icon_image' => 'nullable|string',
                'network_coins' => 'nullable|array', 
                'status' => 'required|integer',
                'limit1' => 'nullable|numeric|min:0',
				'limit2' => 'nullable|numeric|min:0',
				'limit3' => 'nullable|numeric|min:0',
				'limit4' => 'nullable|numeric|min:0',
                'network_coins.*.network_withdraw_enable' => 'nullable|integer',
                'network_coins.*.network_deposit_enable' => 'nullable|integer',
                'network_coins.*.network_enable' => 'nullable|integer',
                'network_coins.*.contract_address' => 'nullable|string|max:191',
                'network_coins.*.token_explorer_url' => 'nullable|string|max:191',
                'network_coins.*.network_id' => 'nullable|integer|exists:networks,id', 
                'network_coins.*.withdraw_fee' => 'nullable|numeric',
                //'network_coins.*.min_deposit' => 'nullable|numeric',
                'network_coins.*.min_withdraw' => 'nullable|numeric',
                'network_coins.*.decimal' => 'required|integer|min:0',
            ]);
    
            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $coinData = $request->only(['name', 'coin', 'usd_price', 'decimal', 'is_fixed_price', 'status', 'icon_image']);
            $coinSymbol = strtolower($coinData['coin']);
            $coinData['coin'] = $coinSymbol;
            $coinData['type'] = $coinSymbol;
            $coinData['trezor_coin_shortcut'] = $coinData['coin'];
            $coinData['env'] = env('MIX_BLOCKCHAIN_NETWORK');
            if ($request->has('icon_image')) {
                $coinData['icon_image'] = $this->formatBase64($request->input('icon_image'));
            }
            //migrate 
            $migrateResult = $this->migrateWhenCreateToken($coinData['coin']);
            if ($migrateResult['status'] == false) {
                return $this->sendError($migrateResult['message']);
            }
            $coin = Coin::create($coinData);
            if ($request->has('network_coins')) {
                $this->dispatchUpdateCoinConfirmationJob($coin->coin, $request->network_coins, 'create');
            }
            else {
                $this->dispatchUpdateCoinConfirmationJob($coin->coin, [], 'create');
            }
            if ($request->has('network_coins')) {
                foreach ($request->network_coins as $networkCoinData) {
                    $this-> dispatchRegisterTokenJob($networkCoinData);
                    $networkCoinData['coin_id'] = $coin->id;
                    $networkCoinData['min_deposit'] = 0;
                    $coin->networkCoins()->create($networkCoinData);
                }
            }

            // add withdrawal limits
			$this->updateWithdrawalLimits($coinSymbol, $request);
			$coin = $this->getWithdrawLimits($coin);
            return $this->sendResponse($coin->load('networkCoins'));
        
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }


    /**
     * Update the specified coin in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $coin = Coin::find($id);

            if (!$coin) {
                return $this->sendError('Coin not found');
            }

            // Validate coin data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:100',
                'coin' => 'sometimes|string|max:20',
                'usd_price' => 'nullable|numeric|min:0', 
                //'decimal' => 'sometimes|integer|min:0',
                'is_fixed_price' => 'sometimes|boolean',
                'icon_image' => 'nullable|string',
                'network_coins' => 'nullable|array',
                'status' => 'required|integer',
				'limit1' => 'nullable|numeric|min:0',
				'limit2' => 'nullable|numeric|min:0',
				'limit3' => 'nullable|numeric|min:0',
				'limit4' => 'nullable|numeric|min:0',
                'network_coins.*.id' => 'sometimes|integer',
                'network_coins.*.network_withdraw_enable' => 'sometimes|integer',
                'network_coins.*.network_deposit_enable' => 'sometimes|integer',
                'network_coins.*.network_enable' => 'sometimes|integer',
                'network_coins.*.contract_address' => 'nullable|string|max:191',
                'network_coins.*.token_explorer_url' => 'nullable|string|max:191',
                'network_coins.*.network_id' => 'required|integer|exists:networks,id',
                'network_coins.*.withdraw_fee' => 'nullable|numeric',
                //'network_coins.*.min_deposit' => 'nullable|numeric',
                'network_coins.*.min_withdraw' => 'nullable|numeric',
            ]);
        
            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $data = $request->all();

            if ($request->has('coin')) {
                $coinSymbol = strtolower($request->input('coin'));
                $data['coin'] = $coinSymbol;
                $data['type'] = $coinSymbol;
                $data['trezor_coin_shortcut'] = $coinSymbol;
            }
            $coinEnv = env('MIX_BLOCKCHAIN_NETWORK');
            $data['env'] = $coinEnv;
         
            if ($request->has('icon_image')) {
                $iconImage = $request->input('icon_image');
                if (isset($iconImage) && !empty($iconImage)) {
                    $data['icon_image'] = $this->formatBase64($iconImage);
                    $coin->update($data);
                }
                else {
                    return $this->sendError('Icon image is required');
                }
            }
            else {
                $coin->update($data);
            }
           
            if ($request->has('network_coins')) {
                $this->dispatchUpdateCoinConfirmationJob($coin->coin, $request->network_coins, 'update');
            }
            else {
                $this->dispatchUpdateCoinConfirmationJob($coin->coin, [], 'update');
            }
            if ($request->has('network_coins')) {
                foreach ($request->network_coins as $networkCoinData) {
                    // Check if the network coin data includes an ID for an existing record     
                    $this-> dispatchRegisterTokenJob($networkCoinData);
                    $networkCoinData['min_deposit'] = 0;
                    if (isset($networkCoinData['id'])) {
                        // Update the existing record based on the provided ID
                        $networkCoin = NetworkCoin::find($networkCoinData['id']);
                        if ($networkCoin) {
                            if (env('DISABLE_EDIT_DECIMAL_COIN', false)) {
                                if (isset($networkCoinData['decimal'])) {
                                    unset($networkCoinData['decimal']);
                                }
                            }

                            // check the duplicate record
                            $existingRecord = NetworkCoin::where('network_id', $networkCoinData['network_id'])
                                            ->where('coin_id', $coin->id) 
                                            ->where('id', '!=', $networkCoinData['id'])
                                            ->first();

                            // If no existing record found, proceed with update
                            if (!$existingRecord) {
                                $networkCoin->update($networkCoinData);
                            } else {
                                return $this->sendError('A record with this coin_id and network_id already exists.');                
                            }
                        } else {
                            // If not found, create a new record
                            NetworkCoin::create(array_merge($networkCoinData, ['coin_id' => $coin->id]));
                        }
                    } else {
                        // Create a new record if no ID is provided
                        
                        // check the duplicate record
                        $existingRecord = NetworkCoin::where('network_id', $networkCoinData['network_id'])
                            ->where('coin_id', $coin->id) 
                            ->first();

                        // If no existing record found, proceed with create
                        if (!$existingRecord) {
                            NetworkCoin::updateOrCreate(
                                [
                                    'network_id' => $networkCoinData['network_id'],
                                    'coin_id' => $coin->id 
                                ],
                                $networkCoinData
                            );
                        } else {
                            return $this->sendError('A record with this coin_id and network_id already exists.');
                        }
                    }
                }
            }

			// update withdrawal limits
			$this->updateWithdrawalLimits($coin->coin, $request, false);
			$coin = $this->getWithdrawLimits($coin);
            return $this->sendResponse($coin->load('networkCoins'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    
    }
    /**
     * Remove the specified coin from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $coin = Coin::find($id);

        if (!$coin) {
            return $this->sendError('Coin not found');
        }

        $coin->delete();

        return $this->sendResponse(null);
    }

    public function countCurrency($coin)
    {
        return CoinsConfirmation::where(compact('coin'))->count();
    }

    private function rollbackMigrate($coin, $tableName, $fileName, $migratePath): void
    {
        $tableNameSpot = "spot_{$coin}_accounts";
        $tbM = trim(rtrim($fileName, '.php'));

        File::delete(database_path($migratePath . $fileName));

        DB::table('migrations')->where('migration', $tbM)->delete();
        Schema::dropIfExists($tableName);
        Schema::dropIfExists($tableNameSpot);
    }

    private function isExistedMigration($name, $migratePath): bool
    {
        $fileNames = scandir(database_path($migratePath));

        return Str::contains(implode(" ", $fileNames), $name);
    }

    private function makeMigration($coin, $tableName, $fileName, $migratePath): void
    {
        if (!$this->isExistedMigration("_create_{$coin}_accounts_table.php", $migratePath)) {
            File::copy(database_path($migratePath . self::MIGRATION_EXAMPLE_FILE), database_path(self::ERC20_MIGRATE_PATH . $fileName));
            $fileContent = file_get_contents(database_path($migratePath . $fileName));
            $fileContent = str_replace('ExampleAccounts', ucfirst($coin) . 'Accounts', $fileContent);
            $fileContent = str_replace('example_accounts', $tableName, $fileContent);
            file_put_contents(database_path($migratePath . $fileName), $fileContent);
        }
    }

    private function deleteMigration($fileName, $migratePath): void {
        $tbM = trim(rtrim($fileName, '.php'));
        File::delete(database_path($migratePath . $fileName));
        DB::table('migrations')->where('migration', $tbM)->delete();
    }

    public function formatBase64($image, $width = 100, $height = 100, $tail = 'png'): string
    {
        $img = Image::make($image);
        $img->resize($width, $height);
        return "data:image/png;base64," . base64_encode($img->stream($tail));
    }
}
