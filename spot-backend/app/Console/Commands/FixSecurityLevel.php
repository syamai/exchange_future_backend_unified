<?php

namespace App\Console\Commands;

use App\Consts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSecurityLevel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix_security_level';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $users = DB::select('SELECT users.id, users.email, security_level, max_security_level, otp_verified, identity_verified
            FROM users join user_security_settings on users.id = user_security_settings.id
            WHERE (max_security_level > 3 and identity_verified = 1 and otp_verified = 1 and security_level < 4)
                OR (identity_verified = 1 and otp_verified = 1 and security_level < 3)
                OR (identity_verified = 1 and security_level < 2) 
        ');
        if (count($users) == 0) {
            echo "All users have correct security level.\n";
            return;
        }

        echo "Id\tEmail\t\tOTP\tMax Level\tLevel\tCorrect level\n";
        foreach ($users as $user) {
            $securityLevel = $this->getCorrectSecurityLevel($user);
            echo "{$user->id}\t{$user->email}\t{$user->otp_verified}\t\t{$user->max_security_level}\t{$user->security_level}\t{$securityLevel}\n";
        }

        if ($this->confirm("Do you want to fix security level for these users?")) {
            $this->fixSecurityLevel($users);
        }
    }

    private function fixSecurityLevel($users)
    {
        foreach ($users as $user) {
            $oldSecurityLevel = $user->security_level;
            $securityLevel = $this->getCorrectSecurityLevel($user);
            DB::table('users')
                ->where('id', $user->id)
                ->update(['security_level' => $securityLevel]);
            echo "Updated: user({$user->id}, {$user->email}), security_level: {$oldSecurityLevel} => $securityLevel\n";
        }
    }

    private function getCorrectSecurityLevel($user)
    {
        $securityLevel = Consts::SECURITY_LEVEL_OTP;

        if ($user->max_security_level > Consts::SECURITY_LEVEL_OTP) {
            $securityLevel = $user->max_security_level;
        }

        if (!$user->otp_verified) {
            $securityLevel = Consts::SECURITY_LEVEL_IDENTITY;
        }

		if (!$user->identity_verified) {
			$securityLevel = Consts::SECURITY_LEVEL_EMAIL;
		}

        return $securityLevel;
    }
}
