<?php
require_once('inc/common.php');
if(!isset($loginuser['uid'])){
	exit("<script>window.location.href='/';</script>");
}

$id = isset($_GET['id'])?$_GET['id']:null;

$stmt = $db->prepare('SELECT a.*,b.name as domain,b.dns FROM `kldns_records` as a left join `kldns_domains` as b on b.domain_id=a.domain_id WHERE a.`uid`=:uid and a.record_id=:id limit 1');
$stmt->execute(array(':uid'=>$loginuser['uid'],':id'=>$id));
if(!$id || !$row=$stmt->fetch(PDO::FETCH_ASSOC)){
	exit("<script language='javascript'>alert('要修改记录的不存在！');window.location.href='control.php';</script>");
}
$dnsApi = Dnsapi::getApi($row['dns']);
$domain_id = $row['domain_id'];

$action = isset($_POST['action'])?$_POST['action']:null;
//修改纪录
if($action == 'updaterecord'){
	$name = trim($_POST['name']);
	$type = trim($_POST['type']);
	$value = trim($_POST['value']);
	if($ret = $dnsApi->updateRecord($domain_id,$id,$name,$type,$value)){
		$sql = 'UPDATE `kldns_records` SET `name`=:name,`type`=:type,`value`=:value,`updatetime`=NOW() WHERE (`record_id`=:record_id)';
		$query = $db->prepare($sql);
		if(!$query->execute(array(':record_id'=>$ret['record_id'],':name'=>$name,':type'=>$type,':value'=>$value))){
			$errorMsg = '修改成功，保存数据库失败！';
			$actionlock='updaterecord';
		}else{
			header('Location: control.php');
			exit();
		}
	}else{
		$errorMsg = $dnsApi->errorMsg;
		$actionlock='updaterecord';
	}

}
if(!$record = $dnsApi->getRecordInfo($domain_id,$id)){
	exit("<script language='javascript'>alert('要修改记录的不存在！');window.location.href='control.php';</script>");
}

$title=$record['name'].'.'.$record['domain'].'-修改';//本页标题
require_once('head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>修改解析<a href="control.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<div class="panel-heading">
							<?php echo $record['name'].'.'.$record['domain'];?>-修改
						</div>
						<div class="panel-body tab-content">
							<div>
								<?php
								if(isset($actionlock) && $actionlock == 'updaterecord'){
									echo '<div class="alert alert-danger text-center" role="alert">Error!'.$errorMsg.'</div>';
								}
								?>
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" value="updaterecord">
									<div class="form-group">
										<label for="inputEmail3" class="col-sm-2 control-label">域名</label>
										<div class="col-sm-10">
											<input type="text" class="form-control" value="<?php echo $row['domain'];?>" disabled>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">记录</label>
										<div class="col-sm-10">
											<input type="text" name="name" class="form-control" value="<?php echo $record['name'];?>">
										</div>
									</div>
									<div class="form-group">
										<label for="inputEmail3" class="col-sm-2 control-label">类型</label>
										<div class="col-sm-10">
											<select name="type" class="form-control" size="1">
												<option value="A">A记录</option>
												<option value="CNAME" <?php if($record['type']=='CNAME'){echo'selected="selected"';}?>>CANME记录</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">记录值</label>
										<div class="col-sm-10">
											<input type="text" name="value" class="form-control" value="<?php echo $record['value'];?>">
										</div>
									</div>
									
									<div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">修改记录</button>
										</div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
                
            </div>
<?php require_once('foot.php');?>