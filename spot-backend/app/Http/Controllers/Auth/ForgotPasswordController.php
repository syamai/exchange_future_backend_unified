<?php

namespace App\Http\Controllers\Auth;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\ForgotPasswordService;
use App\Http\Services\UserService;
use App\Mail\ForgotPassword;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use App\Utils;
use App\Models\User;
use App\Http\Services\MasterdataService;
use Jenssegers\Agent\Facades\Agent;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ForgotPasswordController extends AppBaseController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    private $userService;
    private $forgotPasswordService;

    public function __construct()
    {
        $this->middleware('guest');
        $this->userService = new UserService();
        $this->forgotPasswordService = new ForgotPasswordService();
    }

    public function showLinkRequestForm(Request $request)
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmailViaApi(Request $request)
    {
        DB::beginTransaction();

        $this->validateEmail($request);

        $email = $request->email;

        $user = $this->userService->getUserByEmail($email);

        if (!$user) {
            throw ValidationException::withMessages(['error' => ['login.confirm_email_failed']]);
        }
        if ($user->status == Consts::USER_INACTIVE) {
            throw ValidationException::withMessages(['error' => ['reset.user_inactive']]);
        }

        try {
            $this->forgotPasswordService->deleteByEmail($email);

            $this->forgotPasswordService->saveAndSendLinkToEmail($email, $user);
            DB::commit();
        } catch (Exception $e) {
            if (!($e instanceof ValidationException)) {
                DB::rollBack();
                Log::error($e);
                return $this->sendError($e->getMessage(), 501);
            }
        }
    }

    protected function validateEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email|verified_email']);
    }

    public function checkExpiredResetPassword(Request $request)
    {
        $token = $request->only('token');

        $forgotPssword = $this->forgotPasswordService->getResetPasswordByToken($token);

        if (!$forgotPssword) {
            throw ValidationException::withMessages(['error' => ['login.confirm_link_not_found']]);
        }
        $timeCreated = $forgotPssword->created_at;

        $isExpired = $this->forgotPasswordService->validateExpiredLink($timeCreated);

        if ($isExpired) {
            throw ValidationException::withMessages(['error' => ['login.confirm_failed']]);
        }

        $email = $forgotPssword->email;

        // Expired link after first click
        $this->forgotPasswordService->expiredToken($token);

        return $this->sendResponse($email);
    }
}
