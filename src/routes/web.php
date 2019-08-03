<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('captcha', 'Index\IndexController@captcha');//验证码
Route::any('login', 'Auth\LoginController@userLogin')->name('login');//登录
Route::post('reg', 'Index\IndexController@reg');//注册
Route::any('password', 'Index\IndexController@password');//找回密码
Route::any('logout', 'Auth\LoginController@logout');//退出
Route::any('admin/login', 'Auth\LoginController@adminLogin');//后台登录
Route::any('admin/logout', 'Auth\LoginController@adminLogout');//后台退出
Route::any('verify', 'Index\IndexController@verify');//邮件认证
Route::get('version', function () {
    return ['status' => 0, 'version' => config('version')];
});

Route::get('/', function () {
    return view('index');
});

Route::any('install', 'InstallController@install');

Route::get('cache', function () {
    opcache_reset();
    echo "Del cache success!";
});

Route::get('cron/check/{key}', 'Index\IndexController@autoCheck');

Route::post('check', 'Index\IndexController@check');

Route::prefix('home')->middleware(['auth', 'auth.session:web'])->namespace('Home')->group(function () {
    Route::get('/', function () {
        return view('home.index');
    });
    Route::get('point', function () {
        return view('home.point');
    });
    Route::get('profile', function () {
        return view('home.profile');
    });
    Route::post('/', 'HomeController@post');
});

Route::prefix('admin')->middleware('auth:admin', 'auth.session:admin')->namespace('Admin')->group(function () {
    Route::get('/', function () {
        return view('admin.index');
    });
    Route::get('profile', function () {
        return view('admin.profile');
    });
    Route::post('/', 'AdminController@post');

    Route::prefix('user')->group(function () {
        Route::post('/', 'UserController@post');
        Route::get('list', function () {
            return view('admin.user.list');
        });

        Route::post('group', 'UserGroupController@post');
        Route::get('group', function () {
            return view('admin.user.group');
        });

        Route::get('point', function () {
            return view('admin.user.point');
        });
    });

    Route::prefix('config')->group(function () {
        Route::post('dns', 'DnsConfigController@post');
        Route::get('dns', function () {
            return view('admin.config.dns');
        });
        Route::get('sys', function () {
            return view('admin.config.sys');
        });
        Route::get('check', function () {
            return view('admin.config.check');
        });
        Route::post('/', 'ConfigController@post');
    });

    Route::prefix('domain')->group(function () {
        Route::post('/', 'DomainController@post');
        Route::get('list', function () {
            return view('admin.domain.list');
        });

        Route::post('record', 'DomainRecordController@post');
        Route::get('record', function () {
            return view('admin.domain.record');
        });
    });
});
