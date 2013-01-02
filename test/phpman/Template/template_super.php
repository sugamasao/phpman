<?php
$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/template_super.html');
eq('abcd',$src);


$template = new \phpman\Template();
$template->template_super(__DIR__.'/resources/template_super_x.html');
$src = $template->read(__DIR__.'/resources/template_super.html');
eq('xc',$src);

