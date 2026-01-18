<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Facades\FormatFa;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\EmailMarketingService;
use App\Models\CoinsConfirmation;
use App\Models\EmailMarketing;
use App\Models\User;
use App\Models\UserDeviceRegister;
use App\Models\UserMarketingHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WebhookController extends AppBaseController
{
    public function onReceiveTransaction(Request $request)
    {
        $params = $request->all();
        logger(__FUNCTION__, $params);

        if (isset($params['data']['note']) && $params['data']['note'] === 'cold_wallet') {
            return;
        }


        $networkCoin = FormatFa::formatCoinWebHook($request->data['currency']);
        $blockchainService = new SotatekBlockchainService($networkCoin);

        if (!$blockchainService->isSupportedCoin()) {
            logger()->info("Not support {$networkCoin->coin}");
            return;
        }

        try {
            DB::beginTransaction();

            $params['data']['currency'] = $networkCoin->coin;
            $params['data']['network_id'] = $networkCoin->network_id;
            $data = $blockchainService->onReceiveTransaction($params);

            DB::commit();

            return $data;
        } catch (\Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    public function getTransaction(Request $request)
    {
        try {
            $coin = FormatFa::formatCoin($request->currency);
            $blockchainService = new SotatekBlockchainService($coin);
            $result = $blockchainService->getTransaction($request->transaction_id);

            return $this->sendResponse($result);
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function getAccountBalances(Request $request) {
        //get list account
        $limit = $request->limit ?? 0;
        if ($limit <= 0) {
            $limit = 100;
        } elseif ($limit > 1000) {
            $limit = 1000;
        }

        $users = User::orderBy('id', 'asc')
            ->selectRaw('id as userId, uid as accountId')
            ->paginate($limit);
        $userIds = $users->pluck("userId");
        if ($userIds) {
            $accounts = [];
            $currencies = CoinsConfirmation::query()->select('coin')->pluck('coin');

            foreach ($currencies as $currency) {
                $coin = strtolower($currency);
                $currencyTable = 'spot_' . $coin . '_accounts';

                $balances = DB::connection('master')
                    ->table($currencyTable)
                    ->select(['id', 'balance', 'available_balance'])
                    ->whereIn('id', $userIds)
                    ->where('balance', '>', 0)
                    ->get();
                foreach($balances as $balance) {
                    if (!isset($accounts[$balance->id])) {
                        $accounts[$balance->id] = [
                            'userId' => $balance->id,
                            'assets' => []
                        ];
                    }
                    if ($balance->available_balance > 0) {
                        $accounts[$balance->id]['assets'][$coin] = $balance->balance;
                    }
                }
            }

            $users->SetCollection($users->map(function($item) use ($accounts) {
                $item->assets = [];
                if (isset($accounts[$item->userId])) {
                    $item->assets = $accounts[$item->userId]['assets'];
                }
                return $item;
            }));
        }


        return $users;
    }

	public function sendEmailMarketing(Request $request)
	{
		$inputs = $request->only(['title', 'content', 'from']);
		$validator = Validator::make($inputs, [
			'title' => 'required',
			'content' => 'required',
			'from' =>  'nullable|email:rfc,dns'
		]);

		if ($validator->fails()) {
			return $this->sendError(['errors' => $validator->errors()]);
		}

		$title = $request->get('title');
		$content = $request->get('content');
		$from = $request->from ?? null;
		$email = $request->email ?? [];
		if (!is_array($email)) {
		    $email = [$email];
        }

		try {
			//send email
			$users = User::where('status', Consts::USER_ACTIVE)
				->where('type', '!=', Consts::USER_TYPE_BOT)
				->when($email, function ($q) use ($email) {
					return $q->whereIn('email', $email);
				})
				->get();
			if ($users->isEmpty()) {
				return $this->sendError('not exists '.$email);
			}

			$emailMarketing = EmailMarketing::create([
				'title' => $title,
				'content' => $content,
				'from_email' => $from
			]);

			$emailMarketingService = app(EmailMarketingService::class);


			$result = $users->map(function ($user) use ($emailMarketingService, $emailMarketing) {
				$data = [
					'email' => $user->email,
					'sended_id' => sha1(Carbon::now()),
					'email_marketing_id' => $emailMarketing->id
				];
				try {
					$emailMarketingService->sendMail($user, $emailMarketing);
					$data = array_merge($data, [
						'status' => Consts::SENDED_EMAIL_MARKETING_STATUS_SUCCESS,
						'message' => ''
					]);
				} catch (\Exception $ex) {
					Log::error($ex);
					$data = array_merge($data, [
						'status' => Consts::SENDED_EMAIL_MARKETING_STATUS_FAILED,
						'message' => $ex->getMessage()
					]);
				}
				return $data;
			});

			foreach ($result as $item) {
				UserMarketingHistory::create([
					'email' => $item['email'],
					'email_marketing_id' => $item['email_marketing_id'],
					'sended_id' => $item['sended_id'],
					'status' => $item['status'],
					'message' => $item['message']
				]);
			}

			return $this->sendResponse('', 'success');
		} catch (\Exception $e) {
			Log::error($e);
			return $this->sendError($e);
		}
	}

	public function getInfoAccounts(Request $request) {
		//get list account
		$limit = $request->limit ?? 0;
		if ($limit <= 0) {
			$limit = 100;
		} elseif ($limit > 1000) {
			$limit = 1000;
		}

		$users = User::with(['userSamsubKYC', 'referrerUser'])
			->where('type', Consts::USER_TYPE_NORMAL)
			->where('status', Consts::USER_ACTIVE)
			->selectRaw('id, uid, created_at, email, referrer_code, referrer_id')
			->orderBy('id')
			->paginate($limit);
		$users->setCollection($users->map(function($item) {
			$signUpIp = '';
			$deviceName = '';
			$kyc = $item->userSamsubKYC && $item->userSamsubKYC->status == Consts::KYC_STATUS_VERIFIED;
			$referrerId = $item->referrerUser ? $item->referrerUser->uid : '';
			$totalRefKyc = $item->referrered_users()
				->join('user_samsub_kyc as kyc', 'users.id', 'kyc.user_id')
				->where('users.status', Consts::USER_ACTIVE)
				->where('kyc.status', Consts::KYC_STATUS_VERIFIED)
				->count();
			$totalRef = $item->referrered_users()
				->where('status', Consts::USER_ACTIVE)
				->count();

			$device = UserDeviceRegister::where(['user_id' => $item->id, 'state' => 'connectable'])
				->first();
			if ($device) {
				$signUpIp = $device->latest_ip_address;
				$deviceName = "{$device->platform} ({$device->operating_system})";
			}

			return [
				'uid' => $item->uid,
				'created_at' => $item->created_at,
				'email' => $item->email,
				'sign_up_ip' => $signUpIp,
				'device_name' => $deviceName,
				'kyc' => $kyc,
				'referrer_id' => $referrerId,
				'referrer_code' => $item->referrer_code,
				'total_ref' => $totalRef,
				'total_ref_kyc' => $totalRefKyc

			];
		}));


		return $users;
	}
}
