<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:42
 */

namespace App\Http\Controllers\Admin;


use App\Helper;
use App\Models\DomainRecord;
use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DomainRecordController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'select':
                return $this->select($request);
            case 'delete':
                return $this->delete($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function select(Request $request)
    {
        $data = DomainRecord::search('admin')->orderBy('id', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function delete(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        if (!$id || !$row = DomainRecord::find($id)) {
            $result['message'] = '记录不存在';
        } else {
            Helper::deleteRecord($row);
            if ($row->delete()) {
                OperationLog::write('record.delete', "后台删除解析记录 [{$row->name}.{$row->domain->domain}]", [
                    'uid' => $row->uid,
                    'admin_uid' => Auth::guard('admin')->id(),
                    'source' => 'admin',
                    'target_type' => 'domain_record',
                    'target_id' => $row->id,
                ]);
                $result = ['status' => 0, 'message' => '删除成功'];
            } else {
                $result['message'] = '删除失败，请稍后再试！';
            }
        }
        return $result;
    }

}
