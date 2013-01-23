<?php
\local\test\db\NewDao::create_table();


$obj = new \local\test\db\NewDao();
$obj->value('aaa');
$obj->save();

$obj = new \local\test\db\NewDao();
$obj->value('bbb');
$obj->save();


foreach(\local\test\db\NewDao::find() as $o){
	neq(null,$o->value());
}
foreach(\local\test\db\NewDao::find(\phpman\Q::eq('value','aaa')) as $o){
	eq('aaa',$o->value());
}
