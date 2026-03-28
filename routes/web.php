<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\DnsConfigController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\DomainRecordController;
use App\Http\Controllers\Admin\DomainReviewController;
use App\Http\Controllers\Admin\OperationLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserGroupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Index\IndexController;
use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::get('captcha', [IndexController::class, 'captcha']);
Route::match(['get', 'post'], 'login', [LoginController::class, 'userLogin'])->name('login');
Route::post('reg', [IndexController::class, 'reg']);
Route::match(['get', 'post'], 'password', [IndexController::class, 'password']);
Route::match(['get', 'post'], 'logout', [LoginController::class, 'logout']);
Route::match(['get', 'post'], 'admin/login', [LoginController::class, 'adminLogin']);
Route::match(['get', 'post'], 'admin/logout', [LoginController::class, 'adminLogout']);
Route::match(['get', 'post'], 'verify', [IndexController::class, 'verify']);

Route::get('version', function () {
    return ['status' => 0, 'version' => config('version')];
});

Route::get('/', fn () => view('index'));

Route::match(['get', 'post'], 'install', [InstallController::class, 'install'])->name('install');

Route::get('cache', function () {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    return 'Del cache success!';
});

Route::get('cron/check/{key}', [IndexController::class, 'autoCheck']);
Route::post('check', [IndexController::class, 'check']);

Route::prefix('home')->middleware(['auth', 'auth.session:web'])->group(function () {
    Route::get('/', fn () => view('home.index'));
    Route::get('point', fn () => view('home.point'));
    Route::get('review', fn () => view('home.review'));
    Route::get('api', fn () => view('home.api'));
    Route::get('profile', fn () => view('home.profile'));
    Route::post('/', [HomeController::class, 'post']);
});

Route::prefix('admin')->middleware(['auth:admin', 'auth.session:admin'])->group(function () {
    Route::get('/', fn () => view('admin.index'));
    Route::get('profile', fn () => view('admin.profile'));
    Route::post('/', [AdminController::class, 'post']);

    Route::prefix('user')->group(function () {
        Route::post('/', [UserController::class, 'post']);
        Route::get('list', fn () => view('admin.user.list'));
        Route::post('group', [UserGroupController::class, 'post']);
        Route::get('group', fn () => view('admin.user.group'));
        Route::get('point', fn () => view('admin.user.point'));
    });

    Route::prefix('config')->group(function () {
        Route::post('dns', [DnsConfigController::class, 'post']);
        Route::get('dns', fn () => view('admin.config.dns'));
        Route::get('sys', fn () => view('admin.config.sys'));
        Route::get('check', fn () => view('admin.config.check'));
        Route::get('logs', fn () => view('admin.config.logs'));
        Route::post('logs', [OperationLogController::class, 'post']);
        Route::post('/', [ConfigController::class, 'post']);
    });

    Route::prefix('domain')->group(function () {
        Route::post('/', [DomainController::class, 'post']);
        Route::get('list', fn () => view('admin.domain.list'));
        Route::post('record', [DomainRecordController::class, 'post']);
        Route::get('record', fn () => view('admin.domain.record'));
        Route::post('review', [DomainReviewController::class, 'post']);
        Route::get('review', fn () => view('admin.domain.review'));
    });
});
