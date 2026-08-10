<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Throwable;

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
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        if ($this->isGroupChatJsonRequest($request)) {
            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpException => $e->getStatusCode(),
                default => 500,
            };
            $requestId = (string) ($request->attributes->get('group_chat_request_id') ?: Str::uuid());
            $details = $e instanceof ValidationException ? $e->errors() : null;
            $message = match ($status) {
                403 => 'You are not allowed to perform this operation.',
                404 => 'The requested resource was not found.',
                422 => 'The submitted data is invalid.',
                default => $status >= 500 ? 'An internal error occurred.' : ($e->getMessage() ?: 'The operation could not be completed.'),
            };

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'errors' => $details,
                'data' => null,
                'error' => [
                    'code' => match ($status) {
                        403 => 'forbidden', 404 => 'not_found', 409 => 'conflict',
                        422 => 'validation_failed', 429 => 'rate_limited', default => $status >= 500 ? 'server_error' : 'request_failed',
                    },
                    'message' => $message,
                    'details' => $details,
                    'retryable' => $status >= 500 || $status === 429,
                ],
                'meta' => ['api_version' => '2026-08-05', 'http_status' => $status],
                'request_id' => $requestId,
            ], $status)->header('X-Request-ID', $requestId);
        }

        // Handle timeout errors (Maximum execution time exceeded)
        $errorMessage = $e->getMessage();
        $isTimeoutError = false;
        
        // Check for various timeout error messages
        $timeoutPatterns = [
            'Maximum execution time',
            'execution time exceeded',
            'Maximum execution time of',
            'Fatal error: Maximum execution time',
        ];
        
        foreach ($timeoutPatterns as $pattern) {
            if (str_contains($errorMessage, $pattern)) {
                $isTimeoutError = true;
                break;
            }
        }
        
        if ($isTimeoutError) {
            // Log the error for debugging (only in development or if explicitly enabled)
            if (config('app.debug') || config('logging.log_timeout_errors', false)) {
                \Log::error('Timeout Error: ' . $errorMessage, [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => auth()->id(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
            
            // Return custom 503 error page
            return response()->view('errors.503', [], 503);
        }

        return parent::render($request, $e);
    }

    private function isGroupChatJsonRequest($request): bool
    {
        if (! ($request->expectsJson() || $request->wantsJson() || $request->ajax())) {
            return false;
        }

        return $request->is('messages/*', 'api/groups/*', 'blog/*', 'blogs/*', 'poll/*', 'polls/*', 'comment/*', 'comments/*', 'groups/*/search', 'groups/*/mention-users');
    }
}
