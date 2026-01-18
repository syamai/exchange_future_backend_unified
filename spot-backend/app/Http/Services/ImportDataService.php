<?php

namespace App\Http\Services;

use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Models\CoinsConfirmation;
use App\Models\User;
use App\Consts;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Support\Facades\DB;
use App\Jobs\CreateBlockchainAddress;
use Illuminate\Support\Str;

class ImportDataService
{
    protected $email;
    protected $password;
    public function __construct()
    {
        $this->email = 'importdb-balance@gmail.com';
        $this->password = 'sWcWlhhDV4paoT1L2RBv';
    }

    public function getImportUser()
    {
        return User::where('email', $this->email)->first();
    }

    public function createImportUser($balance): void
    {
        $email = $this->email;
        $seeder = new UsersTableSeeder();
        $user = User::create([
            'name' => "Import DB Bot",
            'email' => $email,
            'password' => bcrypt($this->password),
            'remember_token' => Str::random(10),
            'type' => 'bot',
            'status' => 'active',
            'security_level' => 3,
        ]);

        $seeder->createUserData($user->id, $email);

        $this->updateBalanceRecords($user, $balance);

        // Create deposit address for import user
        if (!$this->checkExistDepositAddress($user->id)) {
            $res = $this->createDepositAddress(Consts::CURRENCY_AMAL, $user->id);
            if ($res) {
                $this->loggerImport('create deposit success: '.$res['blockchain_address'], 'userId: '.$user->id);
            }
        }
    }

    public function updateBalanceRecords($user, $balance): void
    {
        $coins = CoinsConfirmation::get()->pluck('coin', 'id');
        foreach ($coins as $coin) {
            DB::table("{$coin}_accounts")->where('id', $user->id)
                ->update([
                    'balance' => 0,
                    'usd_amount' => 0,
                    'available_balance' => 0,
                ]);
            DB::table("spot_{$coin}_accounts")->where('id', $user->id)
                ->update([
                    'balance' => 0,
                    'usd_amount' => 0,
                    'available_balance' => 0,
                ]);
        }

        DB::table("margin_accounts")->where('owner_id', $user->id)
            ->update([
                'balance' => 0,
                'available_balance' => 0,
            ]);

        // Update balance for import user
        DB::table('amal_accounts')->where('id', $user->id)
            ->update([
                'balance' => $balance,
                'usd_amount' => 0,
                'available_balance' => $balance,
            ]);
    }


    public function getAmalBalance(): \Illuminate\Support\Collection
    {
        return DB::table('t_balance')
            ->where('user_id', '!=', null)
            ->where('m_currency_id', 7)
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->get();
    }


    public function createDepositAddressUsers($userInCurrentSys): void
    {
        $this->createBlockchainAddress('eth');

        // Create deposit Address
        $count = 0;
        foreach ($userInCurrentSys as $userId => $user) {
            $userId = $user->id;
            if (!$this->checkExistDepositAddress($userId)) {
                $res = $this->createDepositAddress(Consts::CURRENCY_AMAL, $userId);
                if ($res) {
                    $this->loggerImport('create deposit success: '.$res['blockchain_address'], 'userId: '.$userId);
                    $count++;
                }
            }
        }

        $this->loggerImport('Number deposit address created: '.$count);
    }

    public function createBlockchainAddress($currency, $minCount = 500): void
    {
        $blockchainService = new SotatekBlockchainService($currency);
        if (!$blockchainService->isSupportedCoin()) {
            logger()->error("Create Address Command: The coin '{$currency}' is unsported");
            return;
        }

        if ($blockchainService->isEthErc20Token()) {
            return;
        }

        $count = DB::table('blockchain_addresses')
            ->where('currency', $currency)
            ->count();

        // No need to dispatch a job
        $createBlockchainAddress = new CreateBlockchainAddress($currency, $minCount - $count + 1);
        $createBlockchainAddress->handle();
    }


    public function checkExistDepositAddress($userId, $onMaster = null)
    {
        $currency = Consts::CURRENCY_AMAL;
        $table = $onMaster ? DB::connection('master')->table($currency . '_accounts') : DB::table($currency . '_accounts');
        $address = $table->where('id', $userId)->first();

        return @$address->blockchain_address;
    }

    public function createDepositAddress($currency, $userId)
    {
        return app(DepositService::class)->createByUserId($currency, $userId);
    }


    public function getUserIdOldSysFromBalance($balances)
    {
        $userId = $balances->pluck('user_id')->toArray();
        $userId = array_unique($userId);

        return $userId;
    }

    public function getUserIdNewSys($userIds): array
    {
        // Get user in old system
        $oldUsers = DB::table('t_user')
            ->whereIn('id', $userIds)
            ->get();

        $oldMails = $oldUsers->pluck('mail', 'id');
        $mappingMailArray = array_flip($oldMails->toArray());

        $users = User::whereIn('email', $oldMails)->get();
        $mappingUsers = [];
        foreach ($users as $user) {
            $oldUserId = $mappingMailArray[$user->email];
            $mappingUsers[$oldUserId] = $user;
        }

        return $mappingUsers;
    }

    private function loggerImport(...$data): void
    {
        logger('IMPORT DATA -------->');
        foreach ($data as $item) {
            logger($item);
            dump($item);
        }
    }
}
