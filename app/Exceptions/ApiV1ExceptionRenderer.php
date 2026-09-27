<?php

namespace App\Exceptions;

use App\Http\Support\Api\V1\ApiRequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiV1ExceptionRenderer
{
    public function render(Request $request, Throwable $exception)
    {
        $status = match (true) {
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof ValidationException => 422,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        $code = match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'validation_failed',
            429 => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };

        $retryable = $status === 429 || $status >= 500;
        $details = $exception instanceof ValidationException ? $exception->errors() : null;
        [$requestId, $locale] = $this->context($request);
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $response = response()->json([
            'status' => 'error',
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $this->message($code, $locale),
                'details' => $details,
                'retryable' => $retryable,
            ],
            'meta' => [
                'api_version' => 'v1',
                'http_status' => $status,
            ],
            'request_id' => $requestId,
        ], $status, $headers);

        return $response
            ->header('X-Request-ID', $requestId)
            ->header('Content-Language', $locale);
    }

    private function context(Request $request): array
    {
        $context = $request->attributes->get('api_v1_context');
        if ($context instanceof ApiRequestContext) {
            return [$context->requestId(), $context->locale()];
        }

        $candidate = trim((string) $request->header('X-Request-ID'));
        $requestId = $candidate !== '' && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();

        foreach (explode(',', strtolower((string) $request->header('Accept-Language'))) as $part) {
            $tag = trim(explode(';', $part, 2)[0]);
            $base = explode('-', $tag, 2)[0];
            if (in_array($base, ['fa', 'en', 'ar'], true)) {
                return [$requestId, $base];
            }
        }

        return [$requestId, 'fa'];
    }

    private function message(string $code, string $locale): string
    {
        $messages = [
            'fa' => [
                'bad_request' => 'درخواست نامعتبر است.',
                'unauthenticated' => 'برای انجام این درخواست باید وارد حساب شوید.',
                'forbidden' => 'اجازه انجام این عملیات را ندارید.',
                'not_found' => 'منبع درخواستی پیدا نشد.',
                'conflict' => 'درخواست با وضعیت فعلی منبع سازگار نیست.',
                'validation_failed' => 'داده‌های ارسال‌شده معتبر نیستند.',
                'rate_limited' => 'تعداد درخواست‌ها بیش از حد مجاز است.',
                'server_error' => 'خطای داخلی رخ داد.',
                'request_failed' => 'انجام درخواست ممکن نشد.',
            ],
            'en' => [
                'bad_request' => 'The request is malformed.',
                'unauthenticated' => 'Authentication is required.',
                'forbidden' => 'You are not allowed to perform this operation.',
                'not_found' => 'The requested resource was not found.',
                'conflict' => 'The request conflicts with the current resource state.',
                'validation_failed' => 'The submitted data is invalid.',
                'rate_limited' => 'Too many requests.',
                'server_error' => 'An internal error occurred.',
                'request_failed' => 'The request could not be completed.',
            ],
            'ar' => [
                'bad_request' => 'الطلب غير صالح.',
                'unauthenticated' => 'يلزم تسجيل الدخول.',
                'forbidden' => 'غير مسموح لك بتنفيذ هذه العملية.',
                'not_found' => 'لم يتم العثور على المورد المطلوب.',
                'conflict' => 'يتعارض الطلب مع الحالة الحالية للمورد.',
                'validation_failed' => 'البيانات المرسلة غير صالحة.',
                'rate_limited' => 'تم تجاوز حد الطلبات.',
                'server_error' => 'حدث خطأ داخلي.',
                'request_failed' => 'تعذر إكمال الطلب.',
            ],
        ];

        return $messages[$locale][$code] ?? $messages['en']['request_failed'];
    }
}
