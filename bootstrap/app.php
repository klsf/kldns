<?php

use App\Helper;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\LoadSysConfig;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            InvokeDeferredCallbacks::class,
            TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Http\Middleware\ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            LoadSysConfig::class,
        ]);

        $middleware->group('web', [
            EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->group('api', [
            'throttle:api',
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => AuthenticateSession::class,
            'bindings' => SubstituteBindings::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            Authenticate::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $response = static function (Request $request, int $status, ?string $message = null): Response {
            $parts = explode('@', (string) $message);
            $url = $parts[1] ?? null;
            $errorMessage = $parts[0] !== '' ? $parts[0] : '系统错误';

            if (!Helper::isPjax() && ($request->ajax() || str_starts_with($request->path(), 'api/'))) {
                return response(['status' => $status, 'message' => $errorMessage], 200);
            }

            return response()->view('error', [
                'status' => $status,
                'error' => $errorMessage,
                'url' => $url,
            ]);
        };

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($response) {
            return $response($request, 404, '页面不存在');
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($response) {
            $message = $exception->getStatusCode() === 405
                ? 'MethodNotAllowedHttpException'
                : $exception->getMessage();

            return $response($request, $exception->getStatusCode(), $message);
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($response) {
            return $response($request, 419, '页面已过期，请刷新后重试');
        });
    })
    ->create();

$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $installPath = storage_path('install');
    $mysqlConfigPath = $installPath.'/mysql.php';
    $versionConfigPath = $installPath.'/version.php';
    $mysql = null;

    if (is_file($mysqlConfigPath)) {
        $mysql = require $mysqlConfigPath;
        config(['mysql' => $mysql]);
    }

    if (is_file($versionConfigPath)) {
        config(['version' => require $versionConfigPath]);
    }

    $hasEnvDatabase = (bool) env('DB_DATABASE');

    if (is_array($mysql) && !empty($mysql['database'])) {
        $connection = config('database.connections.mysql');
        $connection['host'] = $mysql['host'];
        $connection['port'] = $mysql['port'];
        $connection['database'] = $mysql['database'];
        $connection['username'] = $mysql['username'];
        $connection['password'] = $mysql['password'];
        $connection['prefix'] = $mysql['prefix'];
        config(['database.connections.mysql' => $connection]);

        return;
    }

    if ($app->runningInConsole()) {
        return;
    }

    $path = trim(request()->path(), '/');
    if (in_array($path, ['install', 'up'], true)) {
        return;
    }

    if (!$hasEnvDatabase) {
        header('Location: /install');
        exit;
    }
});

return $app;
