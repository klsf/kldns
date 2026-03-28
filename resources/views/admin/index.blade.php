@extends('admin.layout.index')
@section('title', '后台首页')
@section('content')
    <div class="page-header">
        <div>
            <h1>后台总览</h1>
            <p>这里是二级域名分发与解析管理的后台入口，用来接入 DNS 平台、配置开放策略，并统一管理用户、域名和解析记录。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="feature-grid mb-4">
            <div class="feature-card glass-card">
                <h4><i class="fas fa-plug mr-2"></i>接入解析平台</h4>
                <p>先到 <a href="/admin/config/dns">接口配置</a> 接入 DNS 平台 API，系统才能同步主域并执行解析写入、修改和删除操作。</p>
            </div>
            <div class="feature-card glass-card">
                <h4><i class="fas fa-sitemap mr-2"></i>管理开放域名</h4>
                <p>在 <a href="/admin/domain/list">域名列表</a> 中把主域加入资源池，并设置开放范围、积分消耗和说明信息。</p>
            </div>
            <div class="feature-card glass-card">
                <h4><i class="fas fa-user-shield mr-2"></i>控制用户权限</h4>
                <p>通过用户组、积分和系统策略控制谁可以申请哪些资源，并统一查看解析使用情况。</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header">推荐使用流程</div>
            <div class="card-body">
                <div class="list-group-item">1、先完成 DNS 平台接入，确认接口权限、主域同步和解析操作都能正常执行。</div>
                <div class="list-group-item">2、再把主域加入资源池，设置开放用户组、积分规则和前台展示说明。</div>
                <div class="list-group-item">3、最后检查系统配置、自动巡检和通知能力，再正式开放用户自助申请与解析。</div>
            </div>
        </div>
    </div>
@endsection
