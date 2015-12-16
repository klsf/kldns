<?php
require_once('common.php');

$action=getRequest('action','post');
if($action=='add'){
	$dns=getRequest('dns','post');
	$domain=getRequest('domain','post');
	
	$dnsApi = Dnsapi::getApi($dns);
	if(!$domain=$dnsApi->getDomainInfo($domain)){
		$errorMsg=$dnsApi->errorMsg;
	}else{
		$stmt = $db->prepare('INSERT INTO `kldns_domains` (`domain_id`, `dns`, `name`) VALUES (:domain_id, :dns, :name)');
		if(!$stmt->execute(array(':domain_id'=>$domain['domain_id'],':dns'=>$dns,':name'=>trim($domain['name'],'.')))){
			$errorMsg = '添加失败，保存数据库失败！';
		}else{
			header('Location: domainList.php');
			exit();
		}
	}

}

$title='添加域名-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>添加域名<a href="domainList.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<div class="panel-body">
							<div class="list-group text-success">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="add">
									<?php
									if(isset($errorMsg)){
										echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
									}
									?>
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">解析平台</label>
										<div class="col-sm-10">
											<select name="dns" class="form-control ropdown-toggle" id="dnsSelect">
												<option value ="dnspod">DnsPod</option>
												<option value ="aliyun">AliYun</option>
												<option value ="cloudxns">CloudXNS</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">域名</label>
										<div class="col-sm-8">
											<select name="domain" class="form-control ropdown-toggle" id="domainSelect">
											
											</select>
										</div>
										<div class="col-sm-2">
											<span class="btn btn-warning" id="reloadBtn">刷新</span>
										</div>
									</div>
			                        
			                        <div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">确认添加</button>
										</div>
									</div>
			                    </form>
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

	$(document).on("click","#reloadBtn",function(){
		var dns = $('#dnsSelect').val();
		var url="ajax.php?action=domainlist&dns="+dns;
		loadScript(url);
	});
}); 
</script>
<?php require_once('../foot.php');?>