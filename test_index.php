<?php
include_once('bootstrap.php');

$flow = new \phpman\Flow();
$flow->execute([
	'patterns'=>[
		'ABC'=>['action'=>'local.test.flow.AutoAction']
		,'DEF/(.+)/(.+)'=>['action'=>'local.test.flow.AutoAction::jkl']		
		,'template_abc'=>['name'=>'template_abc','template'=>'abc.html']
		,'template_abc/def'=>['name'=>'template_def','template'=>'abc.html']
		,'template_abc/def/(.+)'=>['name'=>'template_def_arg1','template'=>'abc.html']
		,'template_abc/def/(.+)/(.+)'=>['name'=>'template_def_arg2','template'=>'abc.html']
		,'redirect_url'=>['redirect'=>'http://rhaco.org']
		,'redirect_map'=>['redirect'=>'template_defa']
		,'package/action'=>['action'=>'local.test.flow.PackageAction']
		,'request'=>['action'=>'local.test.flow.RequestAction::index']
	]
	//	,'error_redirect'=>'template_abc'
	,'nomatch_redirect'=>'template_abc'
		
]);
