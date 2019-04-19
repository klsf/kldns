<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    private $url;

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request $request
     * @return string
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            return $this->url;
        }
    }

    public function handle($request, Closure $next, ...$guards)
    {
        if (isset($guards[0])) {
            $this->url = $guards[0] === 'admin' ? '/admin/login' : '/login';
        }
        return parent::handle($request, $next, ...$guards);
    }

}
