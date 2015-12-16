<?php
require_once('common.php');

$title='首页-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>管理后台</h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<div class="panel-heading text-center">系统设置</div>
						<div class="panel-body">
							<div class="list-group text-center">
								<a href="webConfig.php" class="list-group-item">网站配置</a>
								<a href="apiConfig.php" class="list-group-item">API配置</a>
							</div>

						</div>
						<div class="panel-heading text-center">数据管理</div>
						<div class="panel-body">
							<div class="list-group text-center">
								<a href="userList.php" class="list-group-item">用户列表</a>
								<a href="recordList.php" class="list-group-item">记录列表</a>
								<a href="domainList.php" class="list-group-item">域名列表</a>
							</div>

						</div>
					</div>
				</div>
                
            </div>


<?php require_once('../foot.php');?>