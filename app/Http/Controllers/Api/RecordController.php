<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Klsf\Dns\Helper as DnsHelper;
use App\Models\ApiToken;
use App\Models\Domain;
use App\Models\DomainRecord;
use App\Models\DomainRecordReview;
use App\Services\DomainRecordService;
use Illuminate\Http\Request;

class RecordController extends Controller
{
    public function domains(Request $request)
    {
        list($token, $user, $response) = $this->auth($request);
        if (!$user) {
            return $response;
        }

        $data = Domain::with('dnsConfig')->available($user->gid)->get()->map(function ($domain) {
            $lines = [];
            if ($domain->dnsConfig && ($_dns = DnsHelper::getModel($domain->dnsConfig->dns))) {
                $_dns->config($domain->dnsConfig->config);
                $lines = $_dns->getRecordLine($domain->domain_id, $domain->domain);
            }

            return [
                'did' => $domain->did,
                'domain' => $domain->domain,
                'point' => $domain->point,
                'desc' => $domain->desc,
                'record_types' => $domain->record_type_list,
                'review_mode' => intval($domain->review_mode),
                'review_mode_text' => $domain->review_mode_text,
                'beian' => intval($domain->beian),
                'beian_text' => $domain->beian_text,
                'line' => $lines,
            ];
        })->values();

        return response()->json(['status' => 0, 'message' => '', 'data' => $data]);
    }

    public function records(Request $request)
    {
        list($token, $user, $response) = $this->auth($request);
        if (!$user) {
            return $response;
        }

        $query = DomainRecord::with(['domain' => function ($builder) {
            $builder->select(['did', 'domain', 'record_types', 'review_mode', 'beian']);
        }])->where('uid', $user->uid)->orderBy('id', 'desc');

        if ($request->query('did')) {
            $query->where('did', intval($request->query('did')));
        }
        if ($request->query('type')) {
            $query->where('type', strtoupper(trim((string)$request->query('type'))));
        }

        return response()->json(['status' => 0, 'message' => '', 'data' => $query->get()]);
    }

    public function reviews(Request $request)
    {
        list($token, $user, $response) = $this->auth($request);
        if (!$user) {
            return $response;
        }

        $query = DomainRecordReview::with(['domain' => function ($builder) {
            $builder->select(['did', 'domain', 'record_types', 'review_mode', 'beian']);
        }])->where('uid', $user->uid)->orderBy('id', 'desc');

        return response()->json(['status' => 0, 'message' => '', 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        list($token, $user, $response) = $this->auth($request, true);
        if (!$user) {
            return $response;
        }

        list($ok, $message) = app(DomainRecordService::class)->submit($user, $request->all(), ['source' => 'api']);

        return response()->json(['status' => $ok ? 0 : -1, 'message' => $message]);
    }

    public function update(Request $request, $id)
    {
        list($token, $user, $response) = $this->auth($request, true);
        if (!$user) {
            return $response;
        }

        $input = $request->all();
        $input['id'] = intval($id);
        list($ok, $message) = app(DomainRecordService::class)->submit($user, $input, ['source' => 'api']);

        return response()->json(['status' => $ok ? 0 : -1, 'message' => $message]);
    }

    public function delete(Request $request, $id)
    {
        list($token, $user, $response) = $this->auth($request, true);
        if (!$user) {
            return $response;
        }

        list($ok, $message) = app(DomainRecordService::class)->delete($user, intval($id), ['source' => 'api']);

        return response()->json(['status' => $ok ? 0 : -1, 'message' => $message]);
    }

    private function auth(Request $request, $needVerified = false)
    {
        $plainToken = trim((string)$request->bearerToken());
        if ($plainToken === '') {
            return [null, null, response()->json(['status' => -1, 'message' => '缺少 Bearer Token'], 401)];
        }

        $token = ApiToken::where('token_hash', hash('sha256', $plainToken))->first();
        if (!$token) {
            return [null, null, response()->json(['status' => -1, 'message' => '令牌不存在或已失效'], 401)];
        }

        if (intval($token->expires_at) > 0 && intval($token->expires_at) < time()) {
            return [null, null, response()->json(['status' => -1, 'message' => '令牌已过期'], 401)];
        }

        $user = $token->user;
        if (!$user) {
            return [null, null, response()->json(['status' => -1, 'message' => '用户不存在'], 401)];
        }

        if ($needVerified && intval($user->status) !== 2) {
            return [null, null, response()->json(['status' => -1, 'message' => '当前用户未完成认证'], 403)];
        }

        $token->last_used_at = time();
        $token->save();

        return [$token, $user, null];
    }
}
