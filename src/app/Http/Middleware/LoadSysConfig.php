<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/15
 * Time: 13:22
 */

namespace App\Http\Middleware;


use App\Http\Controllers\InstallController;
use App\Models\Config;
use Closure;
use Illuminate\Http\Request;

class LoadSysConfig
{

    public function handle(Request $request, Closure $next)
    {
        //设置信任代理IP来源
        Request::setTrustedProxies(['100.0.0.0/8'], Request::HEADER_X_FORWARDED_FOR);
        $uri = $request->getRequestUri();
        if ($uri === '/install' || $uri === '/install/') {

        } else {
            $c = new InstallController();
            $c->update();//更新数据库

            $this->loadSysConfig($request);
        }
        return $next($request);
    }

    /**
     * 加载系统配置
     * @param $request
     * @return mixed
     */
    private function loadSysConfig($request)
    {
        $configs = Config::all();
        $_configs = [];
        foreach ($configs as $config) {
            if (substr($config->k, 0, 6) === 'array_') {
                $_configs[substr($config->k, 6)] = json_decode($config->v, true);
            } else {
                $_configs[$config->k] = $config->v;
            }
        }
        \config(['sys' => $_configs]);
    }
}