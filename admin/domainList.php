<?php
require_once('common.php');

$action=getRequest('action','post');
if($action == 'zd'){
	$domain_id=getRequest('domain_id','post');
	$uid=getRequest('uid','post');
	if(!is_numeric($uid)){
		$alert='UID格式不正确！';
	}elseif(empty($domain_id)){
		$alert='域名ID不能为空！';
	}else{
		$sql = 'UPDATE `kldns_records` SET `name`=:name,`type`=:type,`value`=:value,`updatetime`=NOW() WHERE (`record_id`=:record_id)';
		$stmt = $db->prepare('UPDATE kldns_domains SET allow_uid=:uid WHERE (domain_id=:domain_id)');
		$stmt->execute(array(':domain_id'=>$domain_id,':uid'=>$uid));
	
	}

}

function getRecordCount($domain_id){
	global $db;
	$stmt=$db->prepare('SELECT record_id FROM `kldns_records` where domain_id=:domain_id');//获取记录总数
	$stmt->execute(array('domain_id'=>$domain_id));
	return $stmt->rowCount();
}

$action=getRequest('action','get');
if($action=='del'){
	$domain_id=getRequest('domain_id','get');
	if(!empty($domain_id)){
		$stmt = $db->prepare("DELETE FROM `kldns_domains` WHERE (`domain_id`=:domain_id) limit 1");
		if($stmt->execute(array(':domain_id'=>$domain_id))){
			$stmt = $db->prepare("DELETE FROM `kldns_records` WHERE (`domain_id`=:domain_id) limit 1");
			$stmt->execute(array(':domain_id'=>$domain_id));
		}
	}
}

$stmt=$db->prepare('SELECT * FROM `kldns_domains`');//获取域名列表
$stmt->execute();
$domainList=$stmt->fetchAll(PDO::FETCH_ASSOC);

$title='域名列表-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>域名列表<a href="index.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-success">
						<div class="panel-heading text-center">域名列表</div>
						<div class="panel-body">
							<div class="list-group text-success">
								<div class="list-group-item">[UID]代表指定某个用户能解析此域名，[UID:0]代表所有有均可以解析此域名！</div>
								<div class="list-group-item"><form method="post" class="form-inline"><input type="hidden" name="action" value="zd"><div class="form-group"><label>&nbsp;指定域名&nbsp;&nbsp;&nbsp;</label><select name="domain_id" class="form-control">
								<?php
								foreach ($domainList as $value) {
									echo '<option value="'.$value['domain_id'].'">'.$value['name'].'</option>';
								}
								?>
								</select></div><div class="form-group"><label>&nbsp;&nbsp;&nbsp;所属权归&nbsp;&nbsp;&nbsp;</label><input type="number" name="uid" size=4 class="form-control" placeholder="输入用户UID">&nbsp;&nbsp;&nbsp;使用！</div><button type="submit" class="btn btn-success">确定</button></form></div>
								<?php
								$img['dnspod']='<img src="/assets/images/dnspod.jpg" width=50 height=18>&nbsp;&nbsp;';
								$img['aliyun']='<img src="/assets/images/aliyun.jpg" width=50 height=18>&nbsp;&nbsp;';
								$img['cloudxns']='<img src="/assets/images/cloudxns.jpg" width=50 height=18>&nbsp;&nbsp;';
								if(!empty($domainList)){
									foreach ($domainList as $value) {
										echo'<a href="recordList.php?domain_id='.$value['domain_id'].'" class="list-group-item">'.$img[$value['dns']].$value['name'].'-[UID:'.$value['allow_uid'].'] <span class="badge">'.getRecordCount($value['domain_id']).'</span></a>';
									}
								}
								?>
								
							</div>

						</div>
						<div class="panel-body">
							<div class="list-group text-center">
								<a href="addDomain.php" class="list-group-item">添加域名</a>
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
<?php if(!empty($alert)){ echo "alert('{$alert}')";}?>
	$(document).on("click",".delUser",function(){
		var uid=$(this).attr('uid');
		var url="ajax.php?action=deluser&uid="+uid;
		loadScript(url);
	});
}); 
</script>
<?php require_once('../foot.php');?>