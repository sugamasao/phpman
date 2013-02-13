<?php
include_once('bootstrap.php');

$flow = new \phpman\Flow();
$flow->execute(array(
	'patterns'=>array(
		'ABC'=>array('action'=>'local.test.flow.AutoAction')
		,'DEF/(.+)/(.+)'=>array('action'=>'local.test.flow.AutoAction::jkl')			
		,'template_abc'=>array('template'=>'abc.html')
		,'redirect_abc'=>array('redirect'=>'http://rhaco.org')
	)
));
