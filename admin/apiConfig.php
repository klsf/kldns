<?php
require_once('common.php');

$action=getRequest('action','post');
if($action=='dnspod'){
	$DnspodTokenID=getRequest('DnspodTokenID','post');
	$DnspodToken=getRequest('DnspodToken','post');
	if(!is_numeric($DnspodTokenID) || $DnspodTokenID<10000){
		$showMsg='TokenID格式错误';
	}elseif(strlen($DnspodToken)!=32) {
		$showMsg='Token格式错误';
	}else{
		$dnsApi = new Dnsapi\Dnspod($DnspodTokenID,$DnspodToken);
		if(!$dnsApi->checkToken()){
			$showMsg='Token验证失败，请重新填写';
		}else{
			$stmt = $db->prepare("insert into kldns_configs set `vkey`=:vkey,`value`=:value on duplicate key update `value`=:value");
			$stmt->execute(array(':vkey'=>'DnspodTokenID',':value'=>$DnspodTokenID));
			$stmt->execute(array(':vkey'=>'DnspodToken',':value'=>$DnspodToken));
			config('DnspodTokenID',$DnspodTokenID);
			config('DnspodToken',$DnspodToken);
			$showMsg='Dnspod Token验证成功并已成功保存！';
		}
	}

}elseif($action=='aliyun'){
	$AccessKeyId=getRequest('AliyunAccessKeyId','post');
	$AccessKeySecret=getRequest('AliyunAccessKeySecret','post');
	if(strlen($AccessKeyId) != 16){
		$showMsg='AccessKeyId格式错误';
	}elseif(strlen($AccessKeySecret)!=30) {
		$showMsg='AccessKeySecret格式错误';
	}else{
		$dnsApi = new Dnsapi\Aliyun($AccessKeyId,$AccessKeySecret);
		if(!$dnsApi->checkToken()){
			$showMsg='AccessKey验证失败，请重新填写';
		}else{
			$stmt = $db->prepare("insert into kldns_configs set `vkey`=:vkey,`value`=:value on duplicate key update `value`=:value");
			$stmt->execute(array(':vkey'=>'AliyunAccessKeyId',':value'=>$AccessKeyId));
			$stmt->execute(array(':vkey'=>'AliyunAccessKeySecret',':value'=>$AccessKeySecret));
			config('AliyunAccessKeyId',$AccessKeyId);
			config('AliyunAccessKeySecret',$AccessKeySecret);
			$showMsg='Aliyun AccessKey验证成功并已成功保存！';
		}
	}
}elseif($action=='cloudxns'){
	$CloudXnsApiKey=getRequest('CloudXnsApiKey','post');
	$CloudXnsSecretKey=getRequest('CloudXnsSecretKey','post');
	if(strlen($CloudXnsApiKey) != 32){
		$showMsg='CloudXnsApiKey格式错误';
	}elseif(strlen($CloudXnsSecretKey)!=16) {
		$showMsg='CloudXnsSecretKey格式错误';
	}else{
		$dnsApi = new Dnsapi\CloudXNS($CloudXnsApiKey,$CloudXnsSecretKey);
		if(!$dnsApi->checkToken()){
			$showMsg='AccessKey验证失败，请重新填写';
		}else{
			$stmt = $db->prepare("insert into kldns_configs set `vkey`=:vkey,`value`=:value on duplicate key update `value`=:value");
			$stmt->execute(array(':vkey'=>'CloudXnsApiKey',':value'=>$CloudXnsApiKey));
			$stmt->execute(array(':vkey'=>'CloudXnsSecretKey',':value'=>$CloudXnsSecretKey));
			config('CloudXnsApiKey',$CloudXnsApiKey);
			config('CloudXnsSecretKey',$CloudXnsSecretKey);
			$showMsg='CloudXns AccessKey验证成功并已成功保存！';
		}
	}
}

$title='域名API配置-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>域名API配置<a href="index.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<?php
						if(isset($showMsg)){
							echo '<div class="alert alert-danger text-center" role="alert">'.$showMsg.'</div>';
						}
						?>
						<div class="panel-heading text-center">DnsPod Token配置-[<a href="https://www.dnspod.cn/User/Security" target="_Blank">获取</a>]-[<a href="https://support.dnspod.cn/Kb/showarticle/tsid/227/" target="_Blank">帮助</a>]</div>
						<div class="panel-body">
							<div class="list-group text-success">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="dnspod">
									
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">TokenID</label>
										<div class="col-sm-10">
											<input type="text" name="DnspodTokenID" class="form-control" placeholder="DnsPod的TokenID" value="<?php echo config('DnspodTokenID')?>">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">Token</label>
										<div class="col-sm-10">
											<input type="text" name="DnspodToken" class="form-control" placeholder="DnsPod的Token" value="<?php echo config('DnspodToken')?>">
										</div>
									</div>
			                        
			                        <div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">保存并验证</button>
										</div>
									</div>
			                    </form>
							</div>

						</div>
						<div class="panel-heading text-center">AliYun AccessKey配置-[<a href="https://ak-console.aliyun.com/index#/accesskey" target="_Blank">获取</a>]</div>
						<div class="panel-body">
							<div class="list-group text-success">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="aliyun">
									
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">AccessKeyId</label>
										<div class="col-sm-10">
											<input type="text" name="AliyunAccessKeyId" class="form-control" placeholder="AliyunAccessKeyId" value="<?php echo config('AliyunAccessKeyId')?>">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">AccessKeySecret</label>
										<div class="col-sm-10">
											<input type="text" name="AliyunAccessKeySecret" class="form-control" placeholder="AccessKeySecret" value="<?php echo config('AliyunAccessKeySecret')?>">
										</div>
									</div>
			                        
			                        <div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">保存并验证</button>
										</div>
									</div>
			                    </form>
							</div>

						</div>
						<div class="panel-heading text-center">CloudXNS API KEY配置-[<a href="https://www.cloudxns.net/AccountManage/apimanage.html" target="_Blank">获取</a>]</div>
						<div class="panel-body">
							<div class="list-group text-success">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="cloudxns">
									
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">ApiKey</label>
										<div class="col-sm-10">
											<input type="text" name="CloudXnsApiKey" class="form-control" placeholder="ApiKey" value="<?php echo config('CloudXnsApiKey')?>">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">SecretKey</label>
										<div class="col-sm-10">
											<input type="text" name="CloudXnsSecretKey" class="form-control" placeholder="SecretKey" value="<?php echo config('CloudXnsSecretKey')?>">
										</div>
									</div>
			                        
			                        <div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">保存并验证</button>
										</div>
									</div>
			                    </form>
							</div>

						</div>
						
					</div>
				</div>
                
            </div>

<?php require_once('../foot.php');?>