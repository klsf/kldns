<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:42
 */

namespace App\Http\Controllers\Admin;


use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'store':
                return $this->store($request);
            case 'select':
                return $this->select($request);
            case 'delete':
                return $this->delete($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }

    private function store(Request $request)
    {
        $result = ['status' => -1];
        $gid = intval($request->post('gid'));
        $name = $request->post('name');

        if ($gid && !$row = UserGroup::find($gid)) {
            $result['message'] = '用户组不存在';
        } elseif (strlen($name) < 1) {
            $result['message'] = '输入用户组名称';
        } else {
            if ($user = UserGroup::updateOrCreate(['gid' => $gid], [
                'name' => $name
            ])) {
                $result = ['status' => 0, 'message' => ($gid ? '修改' : '添加') . '成功'];
            } else {
                $result['message'] = '操作失败，请稍候再试！';
            }
        }
        return $result;
    }

    private function select(Request $request)
    {
        $data = UserGroup::where('gid', '>', 99)->orderBy('gid', 'desc')->pageSelect();
        return ['status' => 0, 'message' => '', 'data' => $data];
    }

    private function delete(Request $request)
    {
        $result = ['status' => -1];
        $id = intval($request->post('id'));
        if ($id && $id < 101) {
            $result['message'] = '不能删除系统默认组';
        } elseif (!$id || !$row = UserGroup::find($id)) {
            $result['message'] = '用户组不存在';
        } elseif ($row->delete()) {
            User::where('gid', $id)->update(['gid' => 100]);//重置改组会员为默认组
            $result = ['status' => 0, 'message' => '删除成功'];
        } else {
            $result['message'] = '删除失败，请稍后再试！';
        }
        return $result;
    }

}