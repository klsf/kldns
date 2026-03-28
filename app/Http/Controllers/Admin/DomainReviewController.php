<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DomainRecordReview;
use App\Services\DomainRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DomainReviewController extends Controller
{
    public function post(Request $request)
    {
        switch ($request->post('action')) {
            case 'select':
                return $this->select($request);
            case 'approve':
                return $this->approve($request);
            case 'reject':
                return $this->reject($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function select(Request $request)
    {
        $data = DomainRecordReview::search('admin')->orderBy('id', 'desc')->pageSelect();

        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function approve(Request $request)
    {
        $review = DomainRecordReview::find(intval($request->post('id')));
        if (!$review) {
            return ['status' => -1, 'message' => '审核单不存在'];
        }

        list($ok, $message) = app(DomainRecordService::class)->approveReview($review, Auth::guard('admin')->user(), trim((string)$request->post('remark')));

        return ['status' => $ok ? 0 : -1, 'message' => $message];
    }

    private function reject(Request $request)
    {
        $review = DomainRecordReview::find(intval($request->post('id')));
        if (!$review) {
            return ['status' => -1, 'message' => '审核单不存在'];
        }

        list($ok, $message) = app(DomainRecordService::class)->rejectReview($review, Auth::guard('admin')->user(), trim((string)$request->post('remark')));

        return ['status' => $ok ? 0 : -1, 'message' => $message];
    }
}
