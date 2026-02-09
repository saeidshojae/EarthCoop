<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
}
