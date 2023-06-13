<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Models\Error;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Exceptions\CustomException;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        // ModelNotFoundException::class,
    ];


    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $exception) {
            // only create entries if app environment is not local
            //if (!app()->environment('local')) {
            $user_id = 0;


            if (Auth::user()) {
                $user_id = Auth::user()->id;
            }

            $data = array(
                'user_id'   => $user_id,
                'code'      => $exception->getCode(),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'message'   => $exception->getMessage(),
                'trace'     => $exception->getTraceAsString(),
            );

            Error::create($data);
            //}
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            if ($exception instanceof ModelNotFoundException) {
                $a = explode('\\', $exception->getModel());
                $model = end($a);
                $ids = implode(', ', $exception->getIds());

                if ($exception->getIds()) {
                    $message = __(":model with id `{$ids}` not found.", ['model' => $model]);
                }
                return error($message);
            } elseif ($exception instanceof RequestException) {
                return error($exception->response->json()['message']);
            } elseif ($exception instanceof CustomException) {
                return error($exception->getMessage());
            } elseif ($exception instanceof ConnectionException) {
                return error($exception->getMessage());
            } elseif ($exception instanceof NotFoundHttpException) {
                return error(__("Route not found."));
            } elseif ($exception instanceof ValidationException) {
                return error($exception->validator->errors()->first(), $exception->errors(), 'validation');
            } elseif ($exception instanceof MethodNotAllowedHttpException) {
                return error($exception->getMessage());
            } elseif ($exception instanceof AuthenticationException) {
                return error($exception->getMessage(), [], 'unauthenticated');
            }
        }

        return parent::render($request, $exception);
    }
}
