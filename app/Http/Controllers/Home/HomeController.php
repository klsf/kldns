<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:41
 */

namespace App\Http\Controllers\Home;


use App\Helper;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainRecord;
use App\Models\DomainRecordReview;
use App\Models\ApiToken;
use App\Models\OperationLog;
use App\Models\User;
use App\Models\UserPointRecord;
use App\Services\DomainRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'verify':
                return $this->verify($request);
            case 'profile':
                return $this->profile($request);
            case 'recordList':
                return $this->recordList($request);
            case 'domainList':
                return $this->domainList($request);
            case 'pointRecord':
                return $this->pointRecord($request);
            case 'reviewList':
                return $this->reviewList($request);
            case 'tokenList':
                return $this->tokenList($request);
        }
        if (Auth::user()->status != 2) {
            return ['status' => -1, 'message' => "对不起，请先完成认证！<a href='/home/profile'>点击认证</a>"];
        }

        switch ($action) {
            case 'recordStore':
                return $this->recordStore($request);
            case 'recordDelete':
                return $this->recordDelete($request);
            case 'tokenCreate':
                return $this->tokenCreate($request);
            case 'tokenDelete':
                return $this->tokenDelete($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function verify(Request $request)
    {
        $result = ['status' => -1];
        $user = Auth::user();
        if ($user->status != 1) {
            $result['message'] = '当前状态不需要认证';
        } else {
            list($ret, $error) = Helper::sendVerifyEmail($user);
            if ($ret) {
                $result = ['status' => 0, 'message' => "已将认证邮件发送到{$user->email}，请注意查收！"];
            } else {
                $result['message'] = "发送邮件失败：" . $error;
            }
        }
        return $result;
    }

    private function profile(Request $request)
    {
        $result = ['status' => -1];
        $old_password = $request->post('old_password');
        $new_password = $request->post('new_password');
        if (strlen($old_password) < 5) {
            $result['message'] = '旧密码验证失败';
        } elseif (!Hash::check($old_password, Auth::user()->password)) {
            $result['message'] = '旧密码验证失败';
        } elseif (strlen($new_password) < 5) {
            $result['message'] = '新密码太简单';
        } else {
            if (User::where('uid', Auth::id())->update([
                'password' => Hash::make($new_password),
                'sid' => md5(uniqid() . Str::random())
            ])) {
                $result = ['status' => 0, 'message' => '修改成功'];
            } else {
                $result['message'] = '修改失败，请稍后再试！';
            }
        }
        return $result;
    }

    private function pointRecord(Request $request)
    {
        $data = UserPointRecord::search()->where('uid', Auth::id())->orderBy('id', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function recordStore(Request $request)
    {
        list($ok, $message) = app(DomainRecordService::class)->submit(Auth::user(), $request->post(), ['source' => 'web']);

        return ['status' => $ok ? 0 : -1, 'message' => $message];
    }

    private function recordList(Request $request)
    {
        $data = DomainRecord::search()
            ->where('uid', auth()->id())
            ->orderBy('id', 'desc')
            ->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function domainList(Request $request)
    {
        $data = Domain::with('dnsConfig')->available()->get();
        $list = [];
        foreach ($data as $domain) {
            if ($dns = $domain->dnsConfig) {
                if ($_dns = \App\Klsf\Dns\Helper::getModel($dns->dns)) {
                    $_dns->config($dns->config);
                    $list[] = [
                        'did' => $domain->did,
                        'domain' => $domain->domain,
                        'point' => $domain->point,
                        'desc' => $domain->desc,
                        'record_types' => $domain->record_type_list,
                        'record_type_text' => $domain->record_type_text,
                        'review_mode' => intval($domain->review_mode),
                        'review_mode_text' => $domain->review_mode_text,
                        'beian' => intval($domain->beian),
                        'beian_text' => $domain->beian_text,
                        'line' => $_dns->getRecordLine()
                    ];
                }
            }
        }
        return ['status' => 0, 'message' => '', 'data' => $list];
    }

    private function recordDelete(Request $request)
    {
        list($ok, $message) = app(DomainRecordService::class)->delete(Auth::user(), intval($request->post('id')), ['source' => 'web']);

        return ['status' => $ok ? 0 : -1, 'message' => $message];
    }

    private function reviewList(Request $request)
    {
        $data = DomainRecordReview::search()->orderBy('id', 'desc')->pageSelect();

        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function tokenList(Request $request)
    {
        $data = ApiToken::search()->orderBy('id', 'desc')->pageSelect();

        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function tokenCreate(Request $request)
    {
        $name = trim((string)$request->post('name'));
        $days = intval($request->post('days'));
        if ($name === '') {
            return ['status' => -1, 'message' => '请输入令牌名称'];
        }

        $plainToken = 'kldns_' . Str::random(48);
        $expiresAt = $days > 0 ? time() + ($days * 86400) : 0;

        $token = ApiToken::create([
            'uid' => Auth::id(),
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'token_hint' => substr($plainToken, 0, 10) . '...' . substr($plainToken, -6),
            'expires_at' => $expiresAt,
        ]);

        OperationLog::write('api.token.create', "创建 API 令牌 [{$name}]", [
            'uid' => Auth::id(),
            'source' => 'web',
            'target_type' => 'api_token',
            'target_id' => $token->id,
        ]);

        return ['status' => 0, 'message' => '创建成功，请立即保存令牌明文，该值只显示一次', 'data' => [
            'token' => $plainToken,
        ]];
    }

    private function tokenDelete(Request $request)
    {
        $id = intval($request->post('id'));
        $token = ApiToken::where('uid', Auth::id())->where('id', $id)->first();
        if (!$token) {
            return ['status' => -1, 'message' => '令牌不存在'];
        }

        $name = $token->name;
        $token->delete();

        OperationLog::write('api.token.delete', "删除 API 令牌 [{$name}]", [
            'uid' => Auth::id(),
            'source' => 'web',
            'target_type' => 'api_token',
            'target_id' => $id,
        ]);

        return ['status' => 0, 'message' => '删除成功'];
    }

}
