<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Consts;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('admins')->truncate();
        DB::table('admin_permissions')->truncate();
        $admin = [
            'id'            => 1,
            'name'          => 'DR Admin',
            'email'         => 'admin@monas.exchange',
            'password'      => bcrypt('123123'),
            'role'          => Consts::ROLE_SUPER_ADMIN,
            'created_at'    => Carbon::now()
        ];

        DB::table('admins')->insert($admin);
        $this->createPermissions();
        $this->syncAdminToFuture($admin);
    }

    private function createPermissions()
    {
        $permissions = [
            'SiteSetting', 'Users', 'Permission', 'Exchange', 'Orders', 'Marketing', 'Transaction History'];
        foreach ($permissions as $index => $value) {
            DB::table('admin_permissions')->insert([
            [
                'id'        => $index + 1,
                'admin_id'  => 1,
                'name'      => $value
            ]
            ]);
        }
    }

    private function syncAdminToFuture($user)
    {
        try {
            $url = env('FUTURE_API_URL', 'http://localhost:3000') . env('FUTURE_USER_SYNC', '');
            $data = [
                'id' => $user['id'],
                'email' => $user['email'],
                'position' => 'admin',
                'role' => 'ADMIN',
                'isLocked' => 'UNLOCKED',
                'status' => 'ACTIVE'
            ];
            Http::withHeaders([
                'Authorization' => 'Bearer ' . env('FUTURE_SECRET_KEY', '')
            ])->post($url, $data);
            Log::info("Sync admin to future success");
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
