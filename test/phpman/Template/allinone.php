<?php
$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','abc');
meq('ABC',$src);
nmeq('INDEX',$src);
nmeq('DEF',$src);
meq('IFOOTER',$src);

$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','def');
meq('DEF',$src);
nmeq('INDEX',$src);
nmeq('ABC',$src);
nmeq('IFOOTER',$src);
meq('DFOOTER',$src);

$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','xyz');
meq('XYZ',$src);
nmeq('INDEX',$src);
nmeq('ABC',$src);
meq('IFOOTER',$src);

$template = new \phpman\Template();
$src = $template->read(__DIR__.'/resources/allinone.html','index');
meq('INDEX',$src);
meq('IFOOTER',$src);
nmeq('DFOOTER',$src);
nmeq('ABC',$src);


