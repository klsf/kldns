@extends('admin.layout.index')
@section('title', '后台首页')
@section('content')
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                添加域名简单教程
            </div>
            <div class="card-body">
                <div class="list-group-item">
                    1、点击菜单栏的<a href="/admin/config/dns">接口配置</a>，先对你使用的域名解析平台的接口进行配置！
                </div>
                <div class="list-group-item">
                    2、点击菜单栏的<a href="/admin/domain/list">域名列表</a>，然后点击添加》选择你配置的解析平台》获取，然后选择你要添加的域名，然后保存！
                </div>
            </div>
        </div>
    </div>
@endsection