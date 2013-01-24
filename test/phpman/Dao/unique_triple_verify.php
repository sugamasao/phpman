<?php
\local\test\db\UniqueTripleVerify::create_table();
\local\test\db\UniqueTripleVerify::find_delete();


$obj = new \local\test\db\UniqueTripleVerify();
$obj->u1(2);
$obj->u2(3);
$obj->u3(4);
try{
	$obj->save();
	success();
}catch(\phpman\Exception $e){
	fail();
	\phpman\Exception::clear();
}

$obj = new \local\test\db\UniqueTripleVerify();
$obj->u1(2);
$obj->u2(3);
$obj->u3(4);
try{
	$obj->save();
	fail();
}catch(\phpman\Exception $e){
	success();
	\phpman\Exception::clear();
}
$obj = new \local\test\db\UniqueTripleVerify();
$obj->u1(2);
$obj->u2(4);
$obj->u3(4);
try{
	$obj->save();
	success();
}catch(\phpman\Exception $e){
	fail();
	\phpman\Exception::clear();
}

