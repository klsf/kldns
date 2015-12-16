<?php
require_once('common.php');
require_once('../inc/Page.class.php');
$domain_id=getRequest('domain_id','get');
if(empty($domain_id)){
	$stmt=$db->prepare('SELECT record_id FROM `kldns_records`');//获取记录总数
	$stmt->execute();
}else{
	$stmt=$db->prepare('SELECT record_id FROM `kldns_records` where domain_id=:domain_id');//获取记录总数
	$stmt->execute(array('domain_id'=>$domain_id));
}
$page=new Page($stmt->rowCount(),10);
$limit=$page->limit;
if(empty($domain_id)){
	$stmt=$db->prepare('SELECT a.*,b.`user`,c.`name` as domain_name FROM `kldns_records` AS a LEFT JOIN `kldns_users` AS b ON b.uid = a.uid LEFT JOIN `kldns_domains` as c ON c.domain_id=a.domain_id order by updatetime desc '.$limit);//获取记录列表
	$stmt->execute();
}else{
	$stmt=$db->prepare('SELECT a.*,b.`user`,c.`name` as domain_name FROM `kldns_records` AS a LEFT JOIN `kldns_users` AS b ON b.uid = a.uid LEFT JOIN `kldns_domains` as c ON c.domain_id=a.domain_id where a.domain_id=:domain_id order by updatetime desc '.$limit);//获取记录列表
	$stmt->execute(array('domain_id'=>$domain_id));
}
$list=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$db->prepare('SELECT * FROM `kldns_domains`');//获取域名列表
$stmt->execute();
$domainList=$stmt->fetchAll(PDO::FETCH_ASSOC);


$title='记录列表-管理后台';//本页标题
require_once('../head.php');
?>
			<div class="row">
				<div class="col-xs-12">
					<pre><h4>记录列表<a href="index.php" class="dns-btn btn-info" style="float: right;">返回</a></h4></pre>
				</div>
				<div class="col-xs-12">
					<div class="panel panel-info">
						<div class="panel-heading">
							域名：<select class="btn btn-success dropdown-toggle" id="domainSelect">
								<option value ="0">全部</option>
								<?php
								if(!empty($domainList)){
									foreach ($domainList as $value) {
										if($value['domain_id']==$domain_id){
											echo '<option value ="'.$value['domain_id'].'" selected = "selected">'.$value['name'].'</option>';
										}else{
											echo '<option value ="'.$value['domain_id'].'">'.$value['name'].'</option>';
										}
									}
								}
								?>

							</select>
							<?php
							if(!empty($domain_id)){
								echo '<a href="domainList.php?action=del&domain_id='.$domain_id.'" onClick="if(!confirm(\'删除域名不会删除已解析的记录，确定删除？\')){return false;}" class="btn btn-danger" style="float: right;">删除</a>';
							}
							?>
						</div>
						<div class="panel-body tab-content">
							<div class="table-responsive">
								<table class="table table-striped table-bordered bootstrap-datatable datatable">
									<thead>
										<tr>
											<th>#ID</th>
											<th>域名</th>
											<th>所属用用户</th>
											<th>更新时间</th>
											<th class="text-right">操作</th>
										</tr>
									</thead>
									<tbody>
									<?php
									if (!empty($list)) {
										foreach ($list as $value) {
											echo '<tr id="List_'.$value['record_id'].'"><td>'.$value['record_id'].'</td><td>'.$value['name'].'.'.$value['domain_name'].'</td><td>'.$value['user'].'</td><td>'.$value['updatetime'].'</td><td align="right"><a href="http://'.$value['name'].'.'.$value['domain_name'].'" target="_Blank" class="dns-btn btn-info"><span class="glyphicon glyphicon-eye-open"></span></a>&nbsp;&nbsp;<span class="dns-btn btn-warning delRecord" domain_id='.$value['domain_id'].' record_id="'.$value['record_id'].'"><span class="glyphicon glyphicon-trash"></span></span></td></tr>';
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

	//改变域名选择
	$('#domainSelect').change(function(){
		var domain_id=$(this).children('option:selected').val();
		window.location.href="?domain_id="+domain_id;

	});

	$(document).on("click",".delRecord",function(){
		var record_id=$(this).attr('record_id'),
			domain_id=$(this).attr('domain_id');
		var url="ajax.php?action=delrecord&record_id="+record_id+'&domain_id='+domain_id;
		loadScript(url);
	});
}); 
</script>
<?php require_once('../foot.php');?>