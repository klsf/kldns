<?php
require_once('common.php');


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
						<div class="panel-heading text-center">DnsPod域名列表</div>
						<div class="panel-body">
							<div class="list-group text-success">
								<?php
								$img['dnspod']='<img src="/assets/images/dnspod.jpg" width=50 height=18>&nbsp;&nbsp;';
								$img['aliyun']='<img src="/assets/images/aliyun.jpg" width=50 height=18>&nbsp;&nbsp;';
								$img['cloudxns']='<img src="/assets/images/cloudxns.jpg" width=50 height=18>&nbsp;&nbsp;';
								if(!empty($domainList)){
									foreach ($domainList as $value) {
										echo'<a href="recordList.php?domain_id='.$value['domain_id'].'" class="list-group-item">'.$img[$value['dns']].$value['name'].' <span class="badge">'.getRecordCount($value['domain_id']).'</span></a>';
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
	$(document).on("click",".delUser",function(){
		var uid=$(this).attr('uid');
		var url="ajax.php?action=deluser&uid="+uid;
		loadScript(url);
	});
}); 
</script>
<?php require_once('../foot.php');?>