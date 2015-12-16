<?php
require_once('common.php');
require_once('../inc/Page.class.php');

$stmt=$db->prepare('SELECT uid FROM `kldns_users`');//获取用户总数
$stmt->execute();
$page=new Page($stmt->rowCount(),10);
$limit=$page->limit;
$stmt=$db->prepare('SELECT * FROM `kldns_users` order by uid desc '.$limit);//获取用户列表
$stmt->execute();
$list=$stmt->fetchAll(PDO::FETCH_ASSOC);

$title='用户列表-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>用户列表<a href="index.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<div class="panel-body tab-content">
							<div class="table-responsive">
								<table class="table table-striped table-bordered bootstrap-datatable datatable">
									<thead>
										<tr>
											<th>#UID</th>
											<th>用户名</th>
											<th>邮箱</th>
											<th>注册时间</th>
											<th class="text-right">操作</th>
										</tr>
									</thead>
									<tbody>
									<?php
									if (!empty($list)) {
										foreach ($list as $value) {
											echo '<tr id="List_'.$value['uid'].'"><td>'.$value['uid'].'</td><td>'.$value['user'].'</td><td>'.$value['email'].'</td><td>'.$value['regtime'].'</td><td align="right"><a href="userUpdate.php?uid='.$value['uid'].'" class="dns-btn btn-success"><span class="glyphicon glyphicon-edit"></span></a>&nbsp;&nbsp;<span class="dns-btn btn-warning delUser" uid="'.$value['uid'].'"><span class="glyphicon glyphicon-trash"></span></span></td></tr>';
										}
										echo '<tr><td colspan=5 align=center>'.$page->showpage().'</td></tr>';
									}
									
									?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
                
            </div>

<script type="text/javascript">
function loadScript(c) {
	var a = document.createElement("script");
	a.onload = a.onreadystatechange = function() {
		if (!this.readyState || this.readyState === "loaded" || this.readyState === "complete") {
			a.onload = a.onreadystatechange = null;
			if (a.parentNode) {
				a.parentNode.removeChild(a)
			}
		}
	};
	a.src = c;
	document.getElementsByTagName("head")[0].appendChild(a)
}
$(function () {  
	$(document).on("click",".delUser",function(){
		var uid=$(this).attr('uid');
		var url="ajax.php?action=deluser&uid="+uid;
		loadScript(url);
	});
}); 
</script>
<?php require_once('../foot.php');?>