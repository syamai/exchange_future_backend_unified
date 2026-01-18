<?php

namespace App\Exceptions;

use Bugger\Bugger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use League\OAuth2\Server\Exception\OAuthServerException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
//        AuthenticationException::class,
//        AuthorizationException::class,
//        HttpException::class,
//        HttpResponseException::class,
//        ModelNotFoundException::class,
//        TokenMismatchException::class,
//        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $exception
     * @return Response|\Symfony\Component\HttpFoundation\Response
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TooManyRequestsException) {
            return $this->handleTooManyRequestsException($exception);
        }

        if ($exception instanceof UnregisteredSessionException) {
            return $this->unregisteredSessionHandler($request, $exception);
        }

        if ($exception instanceof TokenMismatchException) {
            if ($request->is('login') && $request->isMethod('post')) {
                return $this->handleLoginTokenMismatchException($exception);
            }
        }

        if ($exception instanceof OAuthServerException) {
            $res = [
                'success' => false,
                'message' => $exception->getMessage(),
                'error' => $exception->getErrorType(),
                'code' => $exception->getCode(),
            ];
            return response()->json($res, 400);
        }

        $render = parent::render($request, $exception);

        $this->notificationException($exception);

        return $render;
    }

    private function notificationException($exception)
    {
        $bugger = new Bugger();
        $bugger->send($exception);
    }

    protected function unregisteredSessionHandler($request, UnregisteredSessionException $exception): JsonResponse|RedirectResponse
    {
        Log::warning('EXCEPTION UnregisteredSessionException');

        if ($request->expectsJson()) {
            return response()->json(['error' => $exception->getMessage()], 401);
        }

        return redirect()->guest('login')->withErrors([
            'unregisterd_session' => $exception->getMessage()
        ]);
    }

    protected function handleLoginTokenMismatchException($exception):RedirectResponse
    {
        Log::warning('Login EXCEPTION TokenMismatchException');

        return redirect()->guest('login')->withErrors([
            'session_timeout' => $exception->getMessage()
        ]);
    }

    protected function handleTooManyRequestsException($exception): JsonResponse
    {
        Log::warning('Login EXCEPTION TooManyRequestsException');

        $res = [
            'success' => false,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'headers' => $exception->getHeaders(),
        ];
        return response()->json($res, $exception->getStatusCode());
    }
}
