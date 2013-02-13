<?php
include_once('bootstrap.php');

$flow = new \phpman\Flow();
$flow->execute([
	'patterns'=>[
		'ABC'=>['action'=>'local.test.flow.AutoAction']
		,'DEF/(.+)/(.+)'=>['action'=>'local.test.flow.AutoAction::jkl']		
		,'template_abc'=>['template'=>'abc.html']
		,'template_abc/def'=>['name'=>'template_def','template'=>'abc.html']
		,'template_abc/def/(.+)'=>['name'=>'template_def_arg1','template'=>'abc.html']
		,'template_abc/def/(.+)/(.+)'=>['name'=>'template_def_arg2','template'=>'abc.html']
		,'redirect_abc'=>['redirect'=>'http://rhaco.org']
	]
]);
