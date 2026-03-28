<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/15
 * Time: 17:38
 */

namespace App\Http\Middleware;


use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthenticateSession
{
    private $auth;
    private $key;

    public function handle(Request $request, Closure $next, $guard = null)
    {
        if (!$request->user() || !$request->session()) {
            return $next($request);
        }
        $this->auth = Auth::guard($guard);

        if (!$this->auth->check()) {
            $this->logout($request);
        }
        $this->key = 'login_user_sid_' . $guard;
        if (!$request->session()->has($this->key)) {
            $this->storeUserSidInSession($request);
        }

        if ($request->session()->get($this->key) !== $this->auth->user()->sid) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request) {
            $this->storeUserSidInSession($request);
        });
    }

    protected function storeUserSidInSession($request)
    {
        if (!$this->auth->check()) {
            $this->logout($request);
        }
        if (!$sid = $this->auth->user()->sid) {
            $sid = md5(uniqid() . Str::random());
            User::where('uid', $this->auth->id())->update(['sid' => $sid]);
            $this->auth->user()->sid = $sid;
        }
        $request->session()->put([
            $this->key => $sid,
        ]);
    }

    protected function logout($request)
    {
        $this->auth->logout();

        $request->session()->flush();

        throw new AuthenticationException;
    }
}