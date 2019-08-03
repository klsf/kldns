<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
    $uri = null;
    if (!$app->runningInConsole()) {
        $uri = request()->getRequestUri();
    }
    if (trim($uri, '/') === 'install') {

    } else {
        if (!config('mysql') && !$app->runningInConsole()) {
            header("Location:/install");
            exit();
        }

        $mysql = config('database.connections.mysql');
        $mysql['host'] = config('mysql.host');
        $mysql['port'] = config('mysql.port');
        $mysql['database'] = config('mysql.database');
        $mysql['username'] = config('mysql.username');
        $mysql['password'] = config('mysql.password');
        $mysql['prefix'] = config('mysql.prefix');
        config(['database.connections.mysql' => $mysql]);
    }
});

return $app;
