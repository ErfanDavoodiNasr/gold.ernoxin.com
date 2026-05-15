<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];
    protected $dontFlash = ['password', 'password_confirmation'];

    public function register()
    {
        $this->reportable(function (Throwable $e) {
        });
    }

    public function render($request, Throwable $e)
    {
        $this->logServerError($request, $e);

        if ($this->wantsSanitizedJson($request)) {
            return $this->jsonErrorResponse($e);
        }

        if ($this->statusCode($e) >= 500) {
            return response()->view('errors.500', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return parent::render($request, $e);
    }

    private function wantsSanitizedJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private function jsonErrorResponse(Throwable $e): JsonResponse
    {
        $status = $this->statusCode($e);
        $message = $status >= 500
            ? 'خطای داخلی سرور رخ داد. لطفاً کمی بعد دوباره تلاش کنید.'
            : Response::$statusTexts[$status] ?? 'درخواست قابل پردازش نیست.';

        return response()->json([
            'message' => $message,
        ], $status);
    }

    private function logServerError(Request $request, Throwable $e): void
    {
        $status = $this->statusCode($e);

        if ($status < 500) {
            return;
        }

        Log::error('Unhandled application error', [
            'path' => $request->path(),
            'method' => $request->method(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    private function statusCode(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        if ($e instanceof ModelNotFoundException) {
            return Response::HTTP_NOT_FOUND;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
