<?php
require_once('../inc/conn.php');
require_once('../inc/Dnsapi/Dnsapi.class.php');

$pwd=getRequest('pwd','post');
if(!empty($pwd)){
	$pwd=getPwd($pwd);
	if($pwd !== config('webAdmin')){
		$errorMsg='密码错误';
	}else{
		setCookie('kldns_webAdmin',$pwd,time()+3600*24*7,'/');
		exit("<script language='javascript'>alert('登录成功！进入管理后台！');window.location.href='index.php';</script>");
	}

}

$title='登录-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>管理后台登录</h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<?php
						if(isset($errorMsg)){
							echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
						}
						?>
						
						
						<div class="panel-body">
							<div class="list-group text-success">
								<form class="form-horizontal" action="#" method="post">
									<div class="form-group">
										<div class="col-xs-12">
											<input type="password" name="pwd" class="form-control" placeholder="管理员密码">
										</div>
									</div>
									<div class="form-group">	
  										<div class="col-xs-12">
											<button type="submit" class="btn btn-success btn-block">登录</button>
										</div>
									</div>
									
			                    </form>
							</div>

						</div>
						
					</div>
				</div>
                
            </div>

<?php require_once('../foot.php');?>