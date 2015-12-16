<?php
require_once('inc/common.php');

$action=getRequest('action','post');
if($action=='login'){
	$user=getSafe(getRequest('user','post'));
	$pwd=getSafe(getRequest('pwd','post'));
	if(strlen($user)<3 || strlen($pwd)<6){
		$loginMsg='请输入正确的用户名和密码';
	}else{
		$pwd=getPwd($pwd);

		$sql = 'SELECT uid FROM `kldns_users` WHERE `user`=:user and `pwd`=:pwd limit 1';
		$stmt = $db->prepare($sql);
		$stmt->execute(array(':user'=>$user,':pwd'=>$pwd));
		if($row=$stmt->fetch(PDO::FETCH_ASSOC)){
			$sid=getSid();
			$sql="UPDATE `kldns_users` SET `sid`=:sid,lasttime=logintime,lastip=loginip,logintime=NOW(),loginip=:ip WHERE (`uid`=:uid) limit 1";
			$stmt = $db->prepare($sql);
			$stmt->execute(array(':uid'=>$row['uid'],':sid'=>$sid,':ip'=>getIp()));
			setCookie('kldns_sid',$sid,time()+3600*24*7,'/');
			exit("<script language='javascript'>alert('登录成功！进入控制面板！');window.location.href='/control.php';</script>");
		}else{
			$loginMsg='登录失败，用户名或密码不正确';
		}
	}
}elseif($action=='reg'){
	$user=getSafe(getRequest('user','post'));
	$pwd=getSafe(getRequest('pwd','post'));
	$email=getSafe(getRequest('email','post'));
	$code=getSafe(getRequest('code','post'));

	$sql = 'SELECT uid FROM `kldns_users` WHERE `user`=:user limit 1';
	$checkUser = $db->prepare($sql);
	$checkUser->execute(array(':user'=>$user));
	$sql = 'SELECT uid FROM `kldns_users` WHERE `email`=:email limit 1';
	$checkEmail = $db->prepare($sql);
	$checkEmail->execute(array(':email'=>$email));

	if(strlen($user)<3 || strlen($pwd)<6){
		$regMsg='用户名或密码格式不正确';
	}elseif(!preg_match('/^[a-zA-Z0-9\-\_]+@[a-zA-Z0-9\-]+\.[a-zA-Z]+$/',$email)){
		$regMsg='邮箱格式不正确';
	}elseif(strlen($code)!=4 || !isset($_COOKIE['verification']) || md5(strtolower($code))!==$_COOKIE['verification']){
		$regMsg='验证码错误';
	}elseif($checkUser->fetch()){
		$regMsg='此用户名已存在';	
	}elseif($checkEmail->fetch()){
		$regMsg='该邮箱已经注册过';	
	}else{
		setCookie('verification',null,-1,'/');//销毁验证码，以防利用这个验证码重复注册
		$pwd=getPwd($pwd);
		$sid=getSid();
		$sql = "INSERT INTO `kldns_users` (`user`, `email`, `pwd`, `sid`, `regtime`, `lasttime`, `lastip`, `logintime`, `loginip`) VALUES (:user,:email, :pwd, :sid, NOW(), NOW(), :ip, NOW(), :ip)";
		$stmt = $db->prepare($sql);
		if($stmt->execute(array(':user'=>$user,':email'=>$email,':pwd'=>$pwd,':sid'=>$sid,':ip'=>getIp()))){
			setCookie('kldns_sid',$sid,time()+3600*24*7,'/');
			exit("<script language='javascript'>alert('注册成功！马上进入控制面板！');window.location.href='/control.php';</script>");
		}else{
			$regMsg='注册失败:保存数据库出错'.$stmt->errorInfo()[2];
		}	
	}
}
$title='域名控制台';//本页标题
require_once('head.php');
?>
            <div class="row">
				<div class="col-xs-12">
					<pre><h4><?php echo config('webName');?></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<div class="panel-heading">
							<ul class="nav nav-tabs">
								<li class="col-xs-6 text-center active"><a href="#login" data-toggle="tab">登录</a></li>
								<li class="col-xs-6 text-center"><a href="#register" data-toggle="tab" id="regtab">注册</a></li>
							</ul>
						</div>
						<div class="panel-body tab-content">
							<div class="tab-pane fade in active" id="login">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="login">
									<?php
									if(isset($loginMsg)){
										echo '<div class="alert alert-danger text-center" role="alert">'.$loginMsg.'</div>';
									}
									?>
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">用户名</label>
										<div class="col-sm-10">
											<input type="text" name="user" class="form-control" placeholder="用户名">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">密码</label>
										<div class="col-sm-10">
											<input type="password" name="pwd" class="form-control" placeholder="密码">
										</div>
									</div>
			                        
			                        <div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">立即登录</button>
										</div>
									</div>
			                    </form>
							</div>
							
							<div class="tab-pane fade" id="register">
								<form class="form-horizontal" action="#" method="post">
									<input type="hidden" name="action" class="form-control" value="reg">
									<?php
									if($action=='reg'){
										echo'<script type="text/javascript">$("#regtab").click();</script>';
									}
									if(isset($regMsg)){
										echo '<div class="alert alert-danger text-center" role="alert">'.$regMsg.'</div>';
									}
									?>
			                    	<div class="form-group">
										<label class="col-sm-2 control-label">用户名</label>
										<div class="col-sm-10">
											<input type="text" name="user" class="form-control" placeholder="最短需要3位">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">密码</label>
										<div class="col-sm-10">
											<input type="password" name="pwd" class="form-control" placeholder="长度不能低于6">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">邮箱</label>
										<div class="col-sm-10">
											<input type="text" name="email" class="form-control" placeholder="有效邮箱地址">
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-2 control-label">验证码</label>
										<label class="col-sm-2 control-label"><img src="/code.php" onclick="this.src='/code.php?'+Math.random();" title="点击更换验证码"></label>
										<div class="col-sm-8">
											<input type="text" name="code" class="form-control">
										</div>
									</div>

			                    	<div class="form-group">	
  										<div class="col-sm-offset-2 col-sm-10">
											<button type="submit" class="btn btn-success btn-block">免费注册</button>
										</div>
									</div>
			                    </form>
							</div>
						</div>
					</div>
				</div>
				
            </div>
<?php require_once('foot.php');?>