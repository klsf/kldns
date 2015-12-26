<?php
require_once('common.php');

$action=getRequest('action','post');
if($action=='set'){
	$webName=getRequest('webName','post');
	$webFoot=getRequest('webFoot','post');
	$webAdmin=getRequest('webAdmin','post');

	if(strlen($webName)<2){
		$errorMsg='网站名称长度不能小于2';
	}elseif($webAdmin && strlen($webAdmin)<5){
		$errorMsg='新密码太简单';
	}else{
		$webFoot=base64_encode($webFoot);
		$stmt = $db->prepare("insert into kldns_configs set `vkey`=:vkey,`value`=:value on duplicate key update `value`=:value");
		$stmt->execute(array(':vkey'=>'webName',':value'=>$webName));
		$stmt->execute(array(':vkey'=>'webFoot',':value'=>$webFoot));
		if($webAdmin){
			$stmt->execute(array(':vkey'=>'webAdmin',':value'=>getPwd($webAdmin)));
		}
		config('webName',$webName);
		config('webFoot',$webFoot);
		
	}

}

$title='网站设置-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>网站基本信息设置<a href="index.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<?php
						if(isset($errorMsg)){
							echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
						}
						?>
						
						<div class="panel-heading text-center">网站设置</div>
						<div class="panel-body">
							<div class="list-group text-success">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="set">
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">网站名称</label>
										<div class="col-sm-10">
											<input type="text" name="webName" class="form-control" value="<?php echo config('webName')?>">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">全局底部信息</label>
										<div class="col-sm-10">
											<textarea name="webFoot" class="form-control" rows="5"><?php echo htmlspecialchars(base64_decode(config('webFoot')));?></textarea>
										</div>
									</div>

									<div class="form-group">
										<label class="col-sm-2 control-label">修改后台密码</label>
										<div class="col-sm-10">
											<input type="text" name="webAdmin" class="form-control" placeholder="留空则不修改">
										</div>
									</div>

			                        <div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">保存配置</button>
										</div>
									</div>
			                    </form>
							</div>

						</div>
						
					</div>
				</div>
                
            </div>

<?php require_once('../foot.php');?>