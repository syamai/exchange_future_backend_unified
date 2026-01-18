<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\ForgotPasswordService;
use App\Http\Services\UserService;
use App\Rules\LimitResetPasswordRule;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use App\Utils;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends AppBaseController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords {
        resetPassword as protected AuthResetPassword;
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/login';
    private $userService;
    private $forgotPasswordService;

    /**
     * Reset the given user's password.
     * Overide Illuminate\Foundation\Auth\ResetsPasswords
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        $user->password = Hash::make($password);

        $user->setRememberToken(Str::random(60));

        $user->save();

        event(new PasswordReset($user));
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
        $this->userService = new UserService();
        $this->forgotPasswordService = new ForgotPasswordService();
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    protected function sendResetResponse($response)
    {
        session()->put('url.intended', $this->redirectPath());
        return redirect($this->redirectPath())
            ->with('status', trans($response));
    }

    public function resetViaApi(Request $request)
    {
        $request_data = request();
        $locale = $request_data->get('lang');
        $request->validate([
            'not_reset_2_times' => [new LimitResetPasswordRule()],
        ]);

        DB::beginTransaction();

        $token = $request->token;
        $locale = $request->lang;
        logger($locale);
        try {
            $this->validate($request, $this->rules(), $this->validationErrorMessages());
        } catch (Exception $ex) {
            $flattened = array_values(Arr::dot($ex->errors()));
            if (!empty($flattened)) {
                if ($flattened[0] == __('validation.custom.password.regex')) {
                    throw ValidationException::withMessages(['password' => ['validation.custom.password.regex']]);
                }
                if ($flattened[0] == __('validation.password_white_space')) {
                    throw ValidationException::withMessages(['password' => ['validation.password_white_space']]);
                }
            }
            throw $ex;
        }
        $password = $request->password;

        $email = $request->email;

        $currentTime = Carbon::now();

        $forgotPssword = $this->forgotPasswordService->getResetPasswordByTokenAndEmail($token, $email);

        if (!$forgotPssword) {
            throw ValidationException::withMessages(['error' => 'login.confirm_failed']);
        }

        $user = $this->userService->getUserByEmail($email);

        if (!$user) {
            throw ValidationException::withMessages(['error' => 'login.confirm_email_failed']);
        }

        try {
            $this->userService->updatePasswordByEmail($email, $currentTime, $password);
            $user->revokeAllToken(false);
            $this->forgotPasswordService->deleteByToken($token);

            DB::commit();
            return $this->sendResponse(['']);
        } catch (Exception $e) {
            if (!($e instanceof ValidationException)) {
                DB::rollBack();
                Log::error($e);
                return $this->sendError($e->getMessage());
            }
        }
    }

    protected function rules()
    {
        return [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|max:72|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/|confirmed|password_white_space',
        ];
    }
}
