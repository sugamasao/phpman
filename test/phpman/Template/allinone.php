<?php
$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','abc');
eq('ABC',$src);
eq('IFOOTER',$src);

$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','def');
eq('DEF',$src);
eq('DFOOTER',$src);

$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','xyz');
eq('XYZ',$src);
eq('IFOOTER',$src);

$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','index');
eq('INDEX',$src);
eq('IFOOTER',$src);



