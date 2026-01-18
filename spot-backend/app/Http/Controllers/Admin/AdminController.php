<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Exports\UsdTransactionExport;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\EmailMarketingRequest;
use App\Http\Requests\NoticeRequest;
use App\Http\Requests\CreateNewAdministrator;
use App\Http\Requests\BankSettingUpdateRequest;
use App\Http\Services\BalanceService;
use App\Http\Services\EmailMarketingService;
use App\Http\Services\FirebaseNotificationService;
use App\Http\Services\MasterdataService;
use App\Http\Services\NoticeService;
use App\Http\Services\OrderService;
use App\Http\Services\TransactionService;
use App\Http\Services\UserService;
use App\Http\Services\AmlTransactionService;
use App\Jobs\UpdateUserTransaction;
use App\Models\AdminDeposits;
use App\Models\SpotCommands;
use App\Models\SumsubKYC;
use App\Notifications\AdminKyc;
use App\Notifications\TransactionStatusChanged;
use App\Http\Services\PriceGroupService;
use App\Http\Services\AdminService;
use App\Http\Services\AmalNetService;
use App\Http\Services\AutoDividendService;
use App\Http\Services\ProfitService;
use App\Http\Services\SettingService;
use App\Models\AdminBankAccount;
use App\Models\EmailMarketing;
use App\Models\KYC;
use App\Models\Notice;
use App\Models\UserMarketingHistory;
use App\Models\UserSecuritySetting;
use App\Models\WithdrawalLimit;
use App\Models\Admin;
use App\Models\AdminPermission;
use App\Models\Coin;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

class AdminController extends AppBaseController
{
    private TransactionService $transactionService;
    private UserService $userService;
    private NoticeService $noticeService;
    private EmailMarketingService $emailMarketingService;
    private AmlTransactionService $amlTransactionService;
    private PriceGroupService $priceGroupService;
    private AdminService $adminService;
    private OrderService $orderService;

    public function __construct(
        TransactionService $transactionService,
        UserService $userService,
        PriceGroupService $priceGroupService,
        OrderService $orderService,
        NoticeService $noticeService,
        EmailMarketingService $emailMarketingService,
        AmlTransactionService $amlTransactionService,
        AdminService $adminService
    ) {
        $this->transactionService = $transactionService;
        $this->userService = $userService;
        $this->priceGroupService = $priceGroupService;
        $this->orderService = $orderService;
        $this->noticeService = $noticeService;
        $this->emailMarketingService = $emailMarketingService;
        $this->amlTransactionService = $amlTransactionService;
        $this->adminService = $adminService;
    }

    public function index(
    ): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $dataVersion = MasterdataService::getDataVersion();
        return view('admin.app')->with('dataVersion', $dataVersion);
    }

    public function getUsers(Request $request): JsonResponse
    {
        $data = $this->userService->getUsers($request->all());
        return $this->sendResponse($data);
    }

    public function getCurrentAdmin(): JsonResponse
    {
        $admin = Auth::guard('admin')->user()->load('permissions');
        return $this->sendResponse($admin);
    }


    public function getUsdTransactions(Request $request): JsonResponse
    {
        $data = $this->transactionService->getUsdTransactions($request->all());
        return $this->sendResponse($data);
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $data = $this->transactionService->getTransactions($request->all());
        return $this->sendResponse($data);
    }

    public function confirmUsdTransaction(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->confirmUsdTransaction($request->all());
            DB::commit();
            $this->notifyTransactionStatus($transaction);
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    private function notifyTransactionStatus($transaction)
    {
        $user = User::find($transaction->user_id);
        $locale = $user->getLocale();
        $amount = new Utils\BigNumber($transaction->amount);
        if ($amount->comp(0) != -1) {
            if ($transaction->status === Consts::TRANSACTION_STATUS_REJECTED) {
                $title = __('title.notification.deposit_usd_fail', [], $locale);
                $body = __('body.notification.deposit_usd_fail', ['time' => Carbon::now()], $locale);
            } else {
                $title = __('title.notification.deposit_usd_success', [], $locale);
                $body = __('body.notification.deposit_usd_success', ['time' => Carbon::now()], $locale);
            }
        } else {
            if ($transaction->status === Consts::TRANSACTION_STATUS_REJECTED) {
                $title = __('title.notification.withdraw_usd_fail', [], $locale);
                $body = __('body.notification.withdraw_usd_fail', ['time' => Carbon::now()], $locale);
            } else {
                $title = __('title.notification.withdraw_usd_success', [], $locale);
                $body = __('body.notification.withdraw_usd_success', ['time' => Carbon::now()], $locale);
            }
        }

        FirebaseNotificationService::send($user->id, $title, $body);
        $user->notify(new TransactionStatusChanged($user->email, $transaction));
    }

    public function rejectUsdTransaction(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->rejectUsdTransaction($request->all());
            DB::commit();
            $this->notifyTransactionStatus($transaction);
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function sendTransaction(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->sendTransaction($request->all());
            DB::commit();
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function cancelTransaction(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->cancelTransaction($request->all());
            DB::commit();
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getUserBalances(Request $request): JsonResponse
    {
        $userId = $request->input('user_id', null);
        $data = app(BalanceService::class)->getUserAccountsV2($userId);

        return $this->sendResponse($data);
    }

    public function getAMALNet(Request $request): JsonResponse
    {
        $data = app(AmalNetService::class)->getAMALNet($request->all());

        return $this->sendResponse($data);
    }

    public function getProfit(Request $request): JsonResponse
    {
        $data = app(ProfitService::class)->getProfit($request->all());

        return $this->sendResponse($data);
    }

    public function getUserAccessHistories(Request $request): JsonResponse
    {
        $userId = $request->input('user_id', null);
        $data = $this->userService->getUserAccessHistories($userId, $request->all());

        return $this->sendResponse($data);
    }

    public function exportUsdTransactionsToExcel(Request $request)
    {
        $timezoneOffset = $request->input('timezone_offset', Carbon::now()->offset);
        $params = $request->all();
        $transactions = $this->transactionService->exportUsdTransactions($params);
        $rows = [];
        $rows[] = [
            __('Request Time'),
            __('Name'),
            __('ID'),
            __('Registered Bank'),
            __('Account Number'),
            __('Withdraw Amount'),
            __('Status')
        ];
        foreach ($transactions as $transaction) {
            $status = $transaction->status == 'pending' ? __('admin.withdraw_status_pending') : __('admin.withdraw_status_success');

            // format date
            $time = Utils::millisecondsToDateTime($transaction->created_at, $timezoneOffset, 'm.d H:i:s');

            $rows[] = [
                $time,
                $transaction->foreign_bank_account_holder,
                $transaction->email,
                $transaction->bank_name,
                $transaction->foreign_bank_account,
                Utils::formatUsdAmount($transaction->amount),
                $status
            ];
        }

        ExcelFacade::download(new UsdTransactionExport($rows), 'USD Withdrawals', Excel::XLSX);
    }

    public function getBankAccounts(): JsonResponse
    {
        $data = AdminBankAccount::all()->first();
        if (!$data) {
            $a = new Request();
            return $this->createBankAccount($a);
        };
        return $this->sendResponse($data);
    }

    public function createBankAccount(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $bankAccount = AdminBankAccount::create([
                'type' => $request->input('type', ''),
                'bank_name' => $request->input('bank_name', ''),
                'account_no' => $request->input('account_no', ''),
                'account_name' => $request->input('account_name', ''),
                'note' => $request->input('note', ''),
                'balance' => $request->input('balance', '0'),
            ]);
            DB::commit();
            return $this->sendResponse($bankAccount);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function updateBankAccount(BankSettingUpdateRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $bankAccount = AdminBankAccount::where('id', $request->input('id'))
                ->update([
                    'bank_name' => $request->input('bank_name', ''),
                    'account_no' => $request->input('account_no', ''),
                    'bank_branch' => $request->input('bank_branch', ''),
                    'account_name' => $request->input('account_name', ''),
                ]);
            DB::commit();
            return $this->sendResponse($bankAccount);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function deleteBankAccount(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data = AdminBankAccount::where('id', $request->input('id'))->delete();
            DB::commit();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getCustomerUsdTotal(): JsonResponse
    {
        $data = DB::table('usd_accounts')->where('balance', '>', 0)->sum('balance');
        return $this->sendResponse($data);
    }

    public function getWithdrawalLimits(): JsonResponse
    {
        $results = MasterdataService::getOneTable('withdrawal_limits')
            ->groupBy('currency')
            ->map(function ($currencyWithdrawLimit) {
                return $currencyWithdrawLimit->mapWithKeys(function ($item) {
                    return ["level_$item->security_level" => $item];
                });
            });
        return $this->sendResponse($results);
    }

    public function updateWithdrawalLimit(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            foreach ($params as $param) {
                WithdrawalLimit::where('id', Arr::get($param, 'id'))
                    ->where('security_level', Arr::get($param, 'security_level'))
                    ->where('currency', Arr::get($param, 'currency'))
                    ->update([
                        'limit' => Arr::get($param, 'limit', 0),
                        'daily_limit' => Arr::get($param, 'daily_limit', 0),
                    ]);
            }
            DB::commit();
            MasterdataService::clearCacheOneTable('withdrawal_limits');
            return $this->getWithdrawalLimits();
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getCoinStepUnitByUsd(Request $request): JsonResponse
    {
        $searchKey = $request->input('search_key');
        $results = MasterdataService::getOneTable('price_groups')
            ->where('currency', 'usd')
            ->when($searchKey, function ($rows) use ($searchKey) {
                return $rows->filter(function ($row, $key) use ($searchKey) {
                    return Str::contains(strtolower($row->coin), strtolower($searchKey));
                });
            })
            ->groupBy('coin')
            ->map(function ($coinStepUnitByUsd) {
                return $coinStepUnitByUsd->mapWithKeys(function ($item) {
                    if (intval($item->group) === 0) {
                        return ["min" => $item];
                    } else {
                        return ["max" => $item];
                    }
                });
            });

        return $this->sendResponse($results);
    }

    public function updateCoinStepUnitByUsd(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            foreach ($params as $param) {
                DB::table('price_groups')->where('id', Arr::get($param, 'id'))
                    ->where('currency', 'usd')
                    ->where('coin', Arr::get($param, 'coin'))
                    ->where('group', Arr::get($param, 'group'))
                    ->update([
                        'value' => Arr::get($param, 'value', 0),
                    ]);
            }
            DB::commit();
            MasterdataService::clearCacheOneTable('price_groups');
            return $this->getCoinStepUnitByUsd($request);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getNotifications(): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        return $this->sendResponse($admin->notifications()->paginate(Consts::DEFAULT_PER_PAGE));
    }

    public function markAsRead($notificationId): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $notification = $admin->unreadNotifications()->where('id', $notificationId)->first();
        if ($notification) {
            $notification->update(['read_at' => Carbon::now()]);
        }
        return $this->sendResponse($notification);
    }

    public function getDepositPageBadge(): JsonResponse
    {
        $params = [
            'type' => Consts::TRANSACTION_TYPE_DEPOSIT,
            'status' => Consts::TRANSACTION_STATUS_PENDING,
            'currency' => Consts::CURRENCY_USD
        ];
        $data = $this->transactionService->getUsdTransactionCount($params);
        return $this->sendResponse($data);
    }

    public function getWithdrawPageBadge(): JsonResponse
    {
        $params = [
            'type' => Consts::TRANSACTION_TYPE_WITHDRAW,
            'status' => Consts::TRANSACTION_STATUS_PENDING,
            'currency' => Consts::CURRENCY_USD
        ];
        $data = $this->transactionService->getUsdTransactionCount($params);
        return $this->sendResponse($data);
    }

    public function getUserKycs(Request $request): JsonResponse
    {
        $data = $this->userService->getUserKycs($request->all());
        return $this->sendResponse($data);
    }

    public function getDetailUserKyc(Request $request): JsonResponse
    {
        $data = $this->userService->getDetailUserKyc($request->all());
        return $this->sendResponse($data);
    }

    public function verifyUserKyc(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $userKyc = KYC::find($request->kyc_id);
            $userKyc->status = Consts::KYC_STATUS_VERIFIED;
            $userKyc->save();

            UserSecuritySetting::where('id', $userKyc->user_id)->update(['identity_verified' => 1]);
            $this->userService->updateUserSecurityLevel($userKyc->user_id);

            DB::commit();

            $user = User::find($userKyc->user_id);
            $user->notify(new AdminKyc($userKyc));

            return $this->sendResponse($userKyc);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function rejectUserKyc(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $userKyc = KYC::find($request->kyc_id);
            $userKyc->status = Consts::KYC_STATUS_REJECTED;
            $userKyc->save();
            DB::commit();

            $user = User::find($userKyc->user_id);
            $user->notify(new AdminKyc($userKyc));

            return $this->sendResponse($userKyc);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getTransactionHistory(Request $request): JsonResponse
    {
        $transaction = $this->orderService->getTransactionsWithPagination($request->all());

        return $this->sendResponse($transaction);
    }

    public function getTradingsByOrder(Request $request, $orderId): JsonResponse
    {
        $transaction = $this->orderService->getTradingsHistoryOrder($request->all(), $orderId);
        return $this->sendResponse($transaction);
    }

    public function getNotices(Request $request): JsonResponse
    {
        $data = $this->noticeService->getNotices($request->all());
        return $this->sendResponse($data);
    }

    public function getEditNotice(Request $request): JsonResponse
    {
        $data = $this->noticeService->getNotice($request->all());
        return $this->sendResponse($data);
    }

    public function updateNotice(NoticeRequest $request): JsonResponse
    {
        $params = [
            'title' => $request->title,
            'support_url' => $request->support_url,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at
        ];
        $img_banner = $request->banner_url;
        if (is_file($img_banner)) {
            $banner_url = Utils::saveFileToStorage($img_banner, 'notice', null, 'public');
            $params['banner_url'] = $banner_url;
        }
        $img_banner_mobile = $request->banner_mobile_url;
        if (is_file($img_banner_mobile)) {
            $banner_mobile_url = Utils::saveFileToStorage($img_banner_mobile, 'notice', null, 'public');
            $params['banner_mobile_url'] = $banner_mobile_url;
        }
        try {
            Notice::where('id', $request->input('id'))
                ->update($params);
            return $this->sendResponse('', 'Update success!');
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function createNotice(NoticeRequest $request): JsonResponse
    {
        $title = $request->title;
        $support_url = $request->support_url;
        $started_at = $request->started_at;
        $ended_at = $request->ended_at;
        $banner_url = Utils::saveFileToStorage($request->banner_url, 'notice', null, 'public');
        $banner_mobile_url = Utils::saveFileToStorage($request->banner_mobile_url, 'notice', null, 'public');
        try {
            Notice::create([
                'title' => $title,
                'support_url' => $support_url,
                'started_at' => $started_at,
                'ended_at' => $ended_at,
                'banner_url' => $banner_url,
                'banner_mobile_url' => $banner_mobile_url
            ]);

            return $this->sendResponse('', 'Create success!');
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function deleteNotice(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            Notice::where('id', $request[0])->delete();
            DB::commit();
            return $this->sendResponse('', 'Delete success!');
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getTradingHistories(Request $request): JsonResponse
    {
        $data = $this->orderService->getTradingHistoriesForAdmin($request->all());
        return $this->sendResponse($data);
    }

    public function getOrderPending(Request $request)
    {
        $orders = $this->orderService->getOrderPending($request);
        return $this->sendResponse($orders);
    }

    public function getUserLoginHistory(Request $request)
    {
        $history = $this->userService->getUserLoginHistory($request->all());

        return $this->sendResponse($history);
    }

    public function getEmailMarketing(Request $request): JsonResponse
    {
        $emails = $this->emailMarketingService->getList($request->all());

        return $this->sendResponse($emails);
    }

    public function editEmailMarketing(Request $request): JsonResponse
    {
        $email = $this->emailMarketingService->getOne($request->all());

        return $this->sendResponse($email);
    }

    public function updateEmailMarketing(EmailMarketingRequest $request): JsonResponse
    {
        $params = [
            'title' => $request->get('title'),
            'content' => $request->get('content'),
        ];
        try {
            EmailMarketing::where('id', $request->input('id'))
                ->update($params);
            return $this->sendResponse('', __('email.update_success'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function createEmailMarketing(EmailMarketingRequest $request): JsonResponse
    {
        $title = $request->get('title');
        $content = $request->get('content');
        try {
            EmailMarketing::create([
                'title' => $title,
                'content' => $content,
            ]);
            return $this->sendResponse('', __('email.create_success'));
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    public function deleteEmailMarketing(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            EmailMarketing::where('id', $request[0])->delete();
            DB::commit();
            return $this->sendResponse('', __('email.delete_success'));
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function sendEmailsMarketing(Request $request): JsonResponse
    {
        $templateEmailId = $request->id;
        $sendAllUsers = $request->all_users;
        $emails = $request->emails;
        try {
            $templateEmailMarketing = EmailMarketing::findOrFail($templateEmailId);
            $users = User::where('status', Consts::USER_ACTIVE)
                ->where('type', '!=', Consts::USER_TYPE_BOT)
                ->when(!$sendAllUsers, function ($q) use ($emails) {
                    return $q->whereIn('email', $emails);
                })
                ->get();
            $result = $users->map(function ($user) use ($sendAllUsers, $emails, $templateEmailMarketing) {
                $data = [
                    'email' => $user->email,
                    'sended_id' => sha1(Carbon::now()),
                    'email_marketing_id' => $templateEmailMarketing->id
                ];
                try {
                    $this->emailMarketingService->sendMail($user, $templateEmailMarketing);
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
            if (!$sendAllUsers) {
                // Email isn't exist.
                $existEmails = $users->map(function ($user) {
                    return $user->email;
                });
                foreach ($emails as $email) {
                    if ($existEmails->contains(strtolower($email))) {
                        continue;
                    }
                    $result->push([
                        'email' => $email,
                        'sended_id' => sha1(Carbon::now()),
                        'email_marketing_id' => $templateEmailMarketing->id,
                        'status' => Consts::SENDED_EMAIL_MARKETING_STATUS_FAILED,
                        'message' => 'The email isn\'t exist'
                    ]);
                }
            }
            $userMarketingHistories = new UserMarketingHistory();
            foreach ($result as $item) {
                $userMarketingHistories->create([
                    'email' => $item['email'],
                    'email_marketing_id' => $item['email_marketing_id'],
                    'sended_id' => $item['sended_id'],
                    'status' => $item['status'],
                    'message' => $item['message']
                ]);
            }
            return $this->sendResponse('ok');
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getEmailMarketingSendedHistories($id, Request $request): JsonResponse
    {
        $params = $request->all();
        $userMarketingHistories = UserMarketingHistory::where('email_marketing_id', $id)
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    return $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) {
                    return $query->orderBy('created_at', 'desc');
                }
            )
            ->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
        return $this->sendResponse($userMarketingHistories);
    }

    public function getCountries(): JsonResponse
    {
        $data = MasterdataService::getOneTable('countries');
        return $this->sendResponse($data);
    }

    public function getAdmins(Request $request): JsonResponse
    {
        $params = $request->all();
        $searchKey = $request->input('search_key');
        $data = Admin::when(!empty($searchKey), function ($query) use ($searchKey) {
            return $query->where(function ($q) use ($searchKey) {
                $q->where('email', 'like', '%' . $searchKey . '%')
                    ->orWhere('name', 'like', '%' . $searchKey . '%');
            });
        })
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    return $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) {
                    return $query->orderBy('created_at', 'asc');
                }
            )
            ->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
        return $this->sendResponse($data);
    }

    public function createNewOrUpdateAdministrator(CreateNewAdministrator $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $admin = Admin::where('email', $request->email)->first();
            if ($admin) {
                $admin->name = $request->name;
                $admin->save();
            } else {
                $admin = Admin::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password)
                ]);
            }

            $permissionModels = [];
            foreach ($request->permissions as $permission) {
                $permissionModels[] = new AdminPermission([
                    'admin_id' => $admin->id,
                    'name' => $permission
                ]);
            }
            AdminPermission::where('admin_id', $admin->id)->delete();
            $admin->permissions()->saveMany($permissionModels);

            DB::commit();
            return $this->sendResponse($admin);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getAdministratorById(Request $request): JsonResponse
    {
        $data = Admin::select('id', 'name', 'email')->findOrFail($request->id)->load('permissions');
        return $this->sendResponse($data);
    }

    public function deleteAdministrator(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            if ($request->id === Consts::SUPER_ADMIN_ID) {
                throw new HttpException(422, __('exception.cannot_remove_super_admin'));
            }
            AdminPermission::where('admin_id', $request->id)->delete();
            $result = Admin::destroy($request->id);
            DB::commit();
            return $this->sendResponse($result);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getBuyHistory(Request $request): JsonResponse
    {
        $history = $this->amlTransactionService->getBuyHistory($request->all());

        return $this->sendResponse($history);
    }

    public function getCashBackHistory(Request $request): JsonResponse
    {
        $history = $this->amlTransactionService->getCashBackHistory($request->all());

        return $this->sendResponse($history);
    }

    public function getPriceGroupCurrency(): JsonResponse
    {
        $data = $this->priceGroupService->getListCurrency();
        return $this->sendResponse($data);
    }

    public function getAllCoin(): JsonResponse
    {
        $data = app(AutoDividendService::class)->getAllCoin();
        return $this->sendResponse($data);
    }

    public function checkOldPassword(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $checkPassword = Hash::check($request->input("password"), $admin->password);
        return $this->sendResponse($checkPassword);
    }

    public function changeAdminPassword(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $checkPassword = Hash::check($request->input("password"), $admin->password);
        try {
            if ($checkPassword) {
                $this->adminService->changeAdminPassword($admin->id, $request->newPassword);
                $data = true;
                if ($data) {
                    $response = [
                        'status' => true,
                        'message' => __('admin.change_password_admin_success')
                    ];

                    //Auth::guard('admin')->logoutOtherDevices();
                    $admin->tokens()
                        ->where('id', '!=', $admin->currentAccessToken()->id)
                        ->delete();
                    return $this->sendResponse(($response));
                }
            } else {
                $response = [
                    'status' => true,
                    'message' => __('exception.cannot_change_admin_password')
                ];
                return $this->sendResponse(($response));
            }
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getInfomationCoins(): JsonResponse
    {
        return $this->sendResponse(Coin::all());
    }

    public function changeSetting(Request $request): JsonResponse
    {
        try {
            $data = app(SettingService::class)->changeSetting($request->all());
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function getSettingSelfTrading(): JsonResponse
    {
        try {
            $data = app(SettingService::class)->getSettingSelfTrading();
            return $this->sendResponse($data);
        } catch (\Exception $ex) {
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }


    public function getSumsubUserKycs(Request $request): JsonResponse
    {
        $data = $this->userService->getSumsubUserKycs($request->all());
        return $this->sendResponse($data);
    }

    public function getSumsubDetailUserKyc(Request $request): JsonResponse
    {
        $data = $this->userService->getSumsubDetailUserKyc($request->all());
        return $this->sendResponse($data);
    }

    public function verifySumsubUserKyc(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $userKyc = SumsubKYC::find($request->kyc_id);
            $userKyc->status = Consts::KYC_STATUS_VERIFIED;
            $userKyc->save();

            UserSecuritySetting::where('id', $userKyc->user_id)->update(['identity_verified' => 1]);
            $this->userService->updateUserSecurityLevel($userKyc->user_id);

            DB::commit();

            $user = User::find($userKyc->user_id);
            $user->notify(new AdminKyc($userKyc));

            return $this->sendResponse($userKyc);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function rejectSumsubUserKyc(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $userKyc = SumsubKYC::find($request->kyc_id);
            $userKyc->status = Consts::KYC_STATUS_REJECTED;
            $userKyc->save();
            DB::commit();

            $user = User::find($userKyc->user_id);
            $user->notify(new AdminKyc($userKyc));

            return $this->sendResponse($userKyc);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function adminDeposit(Request $request) {
        $userId = $request->user_id ?? 0;
        $asset = $request->asset ?? '';
        $amount = $request->amount ?? 0;
        $note = $request->note ?? null;

        try {
            $tableBalance = 'spot_' . $asset . '_accounts';
            $amount = BigNumber::new($amount)->toString();
            if ($amount <= 0) {
                return response()->json([
                    'status' => false,
                    'msg' => "Amount <= 0 ({$amount})"
                ]);
            }
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'msg' => "user_not_exist"
                ]);
            }

            $balance = DB::table($tableBalance)->where(['id' => $userId])->first();
            $availableBalance = $balance->available_balance;

            DB::transaction(function () use ($tableBalance, $userId, $asset, $amount, $availableBalance, $note, $user) {
                DB::table($tableBalance)
                    ->lockForUpdate()
                    ->where([
                        'id' => $userId
                    ])->update([
                        'balance' => DB::raw('balance + ' . $amount),
                        'available_balance' => DB::raw('available_balance + ' . $amount),
                    ]);
                $adminDeposit = AdminDeposits::create([
                    'user_id' => $userId,
                    'currency' => $asset,
                    'amount' => $amount,
                    'before_balance' => $availableBalance,
                    'after_balance' => BigNumber::new($availableBalance)->add($amount)->toString(),
                    'note' => $note
                ]);

                if ($user->type != Consts::USER_TYPE_BOT) {
                    UpdateUserTransaction::dispatchIfNeed(
                        Consts::USER_TRANSACTION_TYPE_ADMIN_DEPOSIT,
                        $adminDeposit->id,
                        $userId,
                        $asset,
                        $amount
                    );
                }


                try {
                    $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                    if ($matchingJavaAllow) {
                        //send kafka ME Deposit
                        $typeName = 'deposit';
                        if ($amount < 0) {
                            $amount = BigNumber::new($amount)->mul(-1)->toString();
                        }

                        $payload = [
                            'type' => $typeName,
                            'data' => [
                                'userId' => $userId,
                                'coin' => $asset,
                                'amount' => $amount,
                                'adminDepositId' => $adminDeposit->id
                            ]
                        ];

                        $command = SpotCommands::create([
                            'command_key' => md5(json_encode($payload)),
                            'type_name' => $typeName,
                            'user_id' => $userId,
                            'obj_id' => $adminDeposit->id,
                            'payload' => json_encode($payload),
                        ]);
                        if (!$command) {
                            throw new HttpException(422, 'can not create command');
                        }

                        $payload['data']['commandId'] = $command->id;
                        Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $payload);

                    }
                } catch (\Exception $ex) {
                    Log::error($ex);
                    Log::error("++++++++++++++++++++ Admin Deposit: $userId, coin: $asset, amount: $amount");
                }
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => true,
            'msg' => 'Success'
        ]);
    }
}
