<?php
$flow = new \phpman\Flow();
$maps = $flow->get_maps('/Users/tokushima/Documents/workspace/phpman/test_index.php');

if(eq(true,isset($maps['template_abc']))){
	eq('template_abc',$maps['template_abc']['name']);
}
