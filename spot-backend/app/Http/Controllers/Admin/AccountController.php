<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\AccountService;
use App\Http\Services\Auth\ConfirmEmailService;
use App\Jobs\SendDataToServiceGame;
use App\Models\CoinSetting;
use App\Models\EmailFilter;
use App\Models\SumsubKYC;
use App\Models\User;
use App\Models\UserSecuritySetting;
use App\Notifications\RegisterDeniedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends AppBaseController
{
    private  $accountService;
    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function index(Request $request) {
        preg_match("/\/([^\/]+)\/([^\/]+)$/", parse_url($request->getRequestUri(), PHP_URL_PATH), $match);

        $match1 = $match[1];
//        dd($match1);
        switch ($match1) {
            case 'params':
                preg_match("/\/([^\/]+)\/([^\/]+)\/([^\/]+)$/", parse_url($request->getRequestUri(), PHP_URL_PATH), $matches);
                $case = $matches[1];
                $match2 = $match1;
                $key = $match[2];
                break;
            case 'orders':
            case 'tree':
            case 'setting':
                preg_match("/\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)$/", parse_url($request->getRequestUri(), PHP_URL_PATH), $matches);
                $match2 = $matches[2];
                $id = $matches[1];
                $case = $matches[4];
                $request['type'] = 'enable';
                $coin_enable = $this->coinPairTradeSpot($request);
                $request['coin_enable'] = $coin_enable->getData()->data ?? [];
                break;
            case 'trade':
                preg_match("/\/([^\/]+)\/([^\/]+)\/([^\/]+)\/([^\/]+)$/", parse_url($request->getRequestUri(), PHP_URL_PATH), $matches);
                $match2 = $matches[2];
                $id = $matches[1];
                $case = $matches[3];
                break;
            case 'history':
            case 'transactions':
            case 'logs':
            case 'balance':
                preg_match("/\/([^\/]+)\/([^\/]+)\/([^\/]+)$/", parse_url($request->getRequestUri(), PHP_URL_PATH), $matches);
                $match2 = $matches[2];
                $id = $matches[1];
                $case = $matches[3];
                break;
            default:
                $case = '';
                $match2 = $match[2];
                break;
        }
        $method = $request->method();
        $params = $request->only(['status']);
//        dd($case, $match2, $id);
        return match ($match2) {
            'params' => $this->sendResponse($this->accountService->params($case, $key)),
            'account' => $this->sendResponse($this->accountService->getListAccount($request)),
            'spot' => $this->sendResponse($this->accountService->spotCase($case, $id, $request, $method)),
            'history' => $this->sendResponse($this->accountService->getHistoryCase($case, $id, $request)),
            'transactions' => $this->sendResponse($this->accountService->getTransactionsCase($case, $id, $request)),
            'logs' => $this->sendResponse($this->accountService->getLogsCase($case, $id, $request)),
            'balance' => $this->sendResponse($this->accountService->getBalanceCase($case, $id, $request)),
            'affiliate' => $this->sendResponse($this->accountService->getAffiliateTree($case, $id, $request)),
            'kyc' => $this->sendResponse($this->accountService->getListAccountKYC($request)),
            'export' => $this->responseExport($this->accountService->export($request, $match1)),
            default => $this->sendResponse($this->getDetail($match1, $match2, $method, $params)),
        };
    }
    public function responseExport($export) {
        if($export) return $this->sendResponse($export);
        return $this->sendIsEmpty("export: IsEmpty");

    }
    public function getDetail($match1, $match2, $method, $params) {
        return match($match1) {
            'account' => $this->accountService->profile($match2, $method, $params),
            'kyc' => $this->accountService->profileKYC($match2, $method, $params),
            default => []
        };
    }

    public function coinPairTradeSpot(Request $request) {
        $coin_pair = collect();
        $coin_pair_trade_eneble = Consts::COIN_PAIR_ENABLE_TRADING;

        // Retrieve the coin settings that are enabled
        $coinSetting = CoinSetting::whereHas('coinConfirmation')
            ->where('is_enable', 1)
            ->get(['coin', 'currency']);

        // Transform the enabled trading pairs from the constant
        $coin_pair_const = collect($coin_pair_trade_eneble)->transform(function ($pair) {
            list($coin, $currency) = explode("/", $pair);
            return ['coin' => strtolower($coin), 'currency' => strtolower($currency)];
        });

        // Iterate through each pair in coin_pair_trade_enable
        foreach ($coin_pair_const as $pair) {
            $exists = $coinSetting->contains(function ($item) use ($pair) {
                return strtolower($item['coin']) === $pair['coin'] && strtolower($item['currency']) === $pair['currency'];
            });
            // Use `put()` to add key-value pairs to the collection
            $coin_pair->put("{$pair['coin']}/{$pair['currency']}", $exists ? 1 : 0);
        }

        // Convert the collection to JSON
        $type = $request['type'];
        return match ($type) {
            'enable' => $this->coinPairTradeSpotEnable($coin_pair),
            'disable' => $this->coinPairTradeSpotDisable($coin_pair),
        };
    }

    public function coinPairTradeSpotEnable($coin_pair) {
        return $this->sendResponse(collect($coin_pair)->filter(function ($item){ return $item == 1;})->keys());
    }
    public function coinPairTradeSpotDisable($coin_pair) {
        return $this->sendResponse(collect($coin_pair)->filter(function ($item){ return $item == 0;})->keys());
    }

    public function disableOtpAuthentication($id, Request $request)
    {
        $user = User::find($id);
        $admin = $request->user();

        if (!$user) {
            return $this->sendError(__('exception.not_found'));
        }

        $securitySetting = UserSecuritySetting::where('id', $user->id)->first();

        if (!$securitySetting) {
            return $this->sendError(__('exception.not_found'));
        }

        if (!$securitySetting->otp_verified) {
            return $this->sendError(__('exception.otp_authentication_disable'));
        }
        $securityLevel = $user->security_level > Consts::SECURITY_LEVEL_OTP ? $user->security_level : (!$securitySetting->identity_verified ? Consts::SECURITY_LEVEL_EMAIL : Consts::SECURITY_LEVEL_IDENTITY);

        DB::beginTransaction();
        try {
            User::where('id', $user->id)
                ->update([
                    'google_authentication' => null,
                    'security_level' => $securityLevel
                ]);

            UserSecuritySetting::where('id', $user->id)
                ->update(['otp_verified' => 0]);

            //log activity
            $user->activityLogs()->create([
                'admin_id' => $admin->id,
                'feature' => Consts::FEATURE_ACOUNT,
                'action' => 'Stop OTP Authentication'
            ]);

			SendDataToServiceGame::dispatch('kyc', $user->id);

            DB::commit();
            return $this->sendResponse('','Stop using OTP success!');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError($e);
        }
    }

	public function adminApproved($id, Request $request)
	{
		$user = User::find($id);
		$admin = $request->user();

		if (!$user) {
			return $this->sendError(__('exception.not_found'));
		}

		if ($user->status != Consts::USER_WARNING) {
			return $this->sendError('exception.status_not_warning');
		}

		DB::beginTransaction();
		try {
			// change status
			$data = ['status' => Consts::USER_ACTIVE, 'registered_at' => Carbon::now()];
			$user->update($data);

			//log activity
			$user->activityLogs()->create([
				'admin_id' => $admin->id,
				'feature' => Consts::FEATURE_ACOUNT,
				'action' => 'Approve account'
			]);

			$confirmService = new ConfirmEmailService();
			$confirmService->queueAndNotifyConfirm($user, $user->id);

			DB::commit();
			return $this->sendResponse('','success');
		} catch (\Exception $e) {
			DB::rollBack();
			return $this->sendError($e);
		}
	}

	public function adminDenied($id, Request $request)
	{
		$user = User::find($id);
		$admin = $request->user();

		if (!$user) {
			return $this->sendError(__('exception.not_found'));
		}

		if ($user->status != Consts::USER_WARNING) {
			return $this->sendError('exception.status_not_warning');
		}

		DB::beginTransaction();
		try {
			// change status
			$data = ['status' => Consts::USER_INACTIVE];
			$user->update($data);

			//add domain email to blacklist
			$domain = trim(strtolower(explode('@', $user->email)[1] ?? ''));
			EmailFilter::updateOrCreate(
				['domain' => $domain],
				[
					'type' => Consts::TYPE_BLACKLIST,
					'admin_id' => $admin->id
				]
			);

			//log activity
			$user->activityLogs()->create([
				'admin_id' => $admin->id,
				'feature' => Consts::FEATURE_ACOUNT,
				'action' => 'Denied account'
			]);

			$user->notify(new RegisterDeniedNotification($user));

			DB::commit();
			return $this->sendResponse('','success');
		} catch (\Exception $e) {
			DB::rollBack();
			return $this->sendError($e);
		}
	}

	public function statisticsKYC()
	{
		try {
			$result = SumsubKYC::selectRaw("count(*) total,
					count(if (status = '".Consts::KYC_STATUS_VERIFIED."', id, NULL)) kyc_verified,
					count(if (status = '".Consts::KYC_STATUS_REJECTED."', id, NULL)) kyc_rejected,
					count(if (status = '".Consts::KYC_STATUS_PENDING."' and bank_status != 'init', id, NULL)) kyc_review,
					count(if (review_result is not null and review_result != '', id, NULL)) kyc_flagged
				")
				->first();
			return $this->sendResponse($result,'success');
		} catch (\Exception $e) {
			DB::rollBack();
			return $this->sendError($e);
		}
	}
}
