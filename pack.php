<?php
include('bootstrap.php');

$libdir = __DIR__.'/lib/phpman';
$extends = $loaded = array();
foreach(\phpman\Util::ls($libdir) as $f){
	$src = file_get_contents($f->getPathname());
	$filename = substr($f->getFilename(),0,-4);
	
	if(preg_match('/extends ([A-Z]\w+)\{/',$src,$m)){
		$extends[$filename] = $m[1];
	}else{
		$loaded[$filename] = true;
	}
}
while(!empty($extends)){
	$k = key($extends);
	$v = $extends[$k];
	unset($extends[$k]);
	
	if(isset($loaded[$v])){
		$loaded[$k] = true;
	}else{
		$extends[$k] = $v;
	}
}
$output = '';
foreach($loaded as $filename => $v){
	$src = file_get_contents($libdir.'/'.$filename.'.php');
	$src = str_replace('<?php','',$src);
	$src = preg_replace('/namespace .+/','',$src);
	$src = preg_replace('/\/\*\*\*.+?\*\//ms','',$src);
	$src = preg_replace('/^[\s]+$/m','',$src);
	$output .= trim($src).PHP_EOL;
}
file_put_contents(__DIR__.'/phpman.php','<?php'.PHP_EOL.'namespace phpman;'.PHP_EOL.$output);

