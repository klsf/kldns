<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    protected string $guardName = 'web';

    public function userLogin(Request $request)
    {
        if ($request->method() === 'POST') {
            $this->guardName = 'web';

            return $this->attemptLogin($request);
        } else {
            return view('login');
        }
    }

    public function adminLogin(Request $request)
    {
        if ($request->method() === 'POST') {
            if (strtolower($request->post('code')) !== Session::get('captcha_code')) {
                return ['status' => -1, 'message' => '验证码不正确'];
            }
            $this->guardName = 'admin';

            return $this->attemptLogin($request);
        } else {
            return view('admin.login');
        }
    }

    public function adminLogout(Request $request)
    {
        if (auth('admin')->check()) {
            User::where('uid', auth('admin')->id())->update(['sid' => md5(Str::random())]);
        }
        auth('admin')->logout();
        return redirect('/admin/login');
    }

    public function logout(Request $request)
    {
        if (auth()->check()) {
            User::where('uid', auth()->id())->update(['sid' => md5(Str::random())]);
        }
        auth()->logout();
        return redirect('/login');
    }

    private function attemptLogin(Request $request): array
    {
        $credentials = [
            'username' => $request->string('username')->toString(),
            'password' => $request->string('password')->toString(),
        ];

        if ($this->guardName === 'admin') {
            $credentials['gid'] = 99;
        }

        $remember = $request->boolean('remember');
        $guard = Auth::guard($this->guardName);

        if (!$guard->attempt($credentials, $remember)) {
            return ['status' => -1, 'message' => '账号或者密码不正确'];
        }

        /** @var User $user */
        $user = $guard->user();

        if ((int) $user->status === 0) {
            $guard->logout();
            return ['status' => -1, 'message' => '对不起，账户已被禁用！'];
        }

        if ($this->guardName !== 'admin' && (int) $user->gid === 99) {
            $guard->logout();
            return ['status' => -1, 'message' => '账号或者密码不正确'];
        }

        $request->session()->regenerate();
        $request->session()->put('login_user_sid_' . $this->guardName, $user->sid);

        return [
            'status' => 0,
            'message' => '登录成功！',
            'go' => $this->guardName === 'admin' ? '/admin' : '/home',
        ];
    }
}
