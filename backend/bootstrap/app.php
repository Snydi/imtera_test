<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());

        $exceptions->respond(function (Response $response): Response {
            if (! request()->is('api/*') || ! $response instanceof JsonResponse) {
                return $response;
            }

            $message = match ($response->getStatusCode()) {
                401 => 'Для работы с организациями необходимо войти в аккаунт.',
                403 => 'У вас нет доступа к этому действию.',
                404 => 'Запрашиваемый ресурс не найден.',
                419 => 'Сессия истекла. Обновите страницу и повторите попытку.',
                429 => 'Слишком много запросов. Попробуйте через минуту.',
                default => $response->getStatusCode() >= 500
                    ? 'Не удалось выполнить запрос. Попробуйте ещё раз.'
                    : null,
            };

            if ($message !== null) {
                $response->setData(['message' => $message]);
            }

            return $response;
        });
    })->create();
