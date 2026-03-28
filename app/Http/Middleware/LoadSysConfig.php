<?php

namespace App\Http\Middleware;

use App\Http\Controllers\InstallController;
use App\Models\Config;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class LoadSysConfig
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array(trim($request->path(), '/'), ['install', 'up'], true)) {
            $mysql = config('mysql');
            $hasMysqlConfig = is_array($mysql) && !empty($mysql['database']);
            $hasEnvDatabase = (bool) env('DB_DATABASE');

            if (!$hasMysqlConfig && !$hasEnvDatabase) {
                return $next($request);
            }

            try {
                $c = new InstallController();
                $c->update();
                $this->loadSysConfig($request);
            } catch (Throwable $exception) {
                config(['sys' => []]);
            }
        }

        return $next($request);
    }

    /**
     * 加载系统配置
     * @param $request
     * @return mixed
     */
    private function loadSysConfig(Request $request): void
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
        config(['sys' => $_configs]);
    }
}
