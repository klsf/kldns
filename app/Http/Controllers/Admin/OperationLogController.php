<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use Illuminate\Http\Request;

class OperationLogController extends Controller
{
    public function post(Request $request)
    {
        if ($request->post('action') !== 'select') {
            return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }

        $data = OperationLog::with([
            'user' => function ($builder) {
                $builder->select(['uid', 'username']);
            },
            'admin' => function ($builder) {
                $builder->select(['uid', 'username']);
            },
        ])->search()->orderBy('id', 'desc')->pageSelect();

        return ['status' => 0, 'message' => '', 'data' => $data];
    }
}
