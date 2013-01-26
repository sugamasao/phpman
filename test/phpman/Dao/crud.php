<?php
\local\test\db\Crud::create_table();
\local\test\db\Crud::find_delete();

$start = microtime(true);
eq(0,\local\test\db\Crud::find_count());
for($i=1;$i<=100;$i++){
	r(new \local\test\db\Crud())->value($i)->save();
}
$time = microtime(true) - $start;
if($time > 1) notice($time);
eq(100,\local\test\db\Crud::find_count());

$start = microtime(true);
foreach(\local\test\db\Crud::find() as $o){
	$o->value($o->value()+1)->save();
}
$time = microtime(true) - $start;
if($time > 1) notice($time);
eq(100,\local\test\db\Crud::find_count());

$start = microtime(true);
foreach(\local\test\db\Crud::find() as $o){
	$o->delete();
}
$time = microtime(true) - $start;
if($time > 1) notice($time);
eq(0,\local\test\db\Crud::find_count());
