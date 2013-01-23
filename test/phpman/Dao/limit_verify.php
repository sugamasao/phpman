<?php
\local\test\db\LimitVerify::create_table();
\local\test\db\LimitVerify::find_delete();


$obj = new \local\test\db\LimitVerify();
$obj->value1('123');
$obj->value2(3);
try{
	$obj->save();
	success();
}catch(\phpman\Exception $e){
	\phpman\Exception::clear();
	fail();
}
$obj = new \local\test\db\LimitVerify();
$obj->value1('1234');
$obj->value2(4);
try{
	$obj->save();
	fail();
}catch(\phpman\Exception $e){
	\phpman\Exception::clear();
	success();
}
$obj = new \local\test\db\LimitVerify();
$obj->value1('1');
$obj->value2(1);
try{
	$obj->save();
	fail();
}catch(\phpman\Exception $e){
	\phpman\Exception::clear();
	success();
}

$obj = new \local\test\db\LimitVerify();
try{
	$obj->save();
	success();
}catch(\phpman\Exception $e){
	\phpman\Exception::clear();
	fail();
}
