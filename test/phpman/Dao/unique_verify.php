<?php
namespace test\phpman\Dao;
\local\test\db\UniqueVerify::create_table();
\local\test\db\UniqueVerify::find_delete();


$obj = new \local\test\db\UniqueVerify();
$obj->u1(2);
$obj->u2(3);
try{
	$obj->save();
	success();
}catch(\phpman\Exception $e){
	fail();
	\phpman\Exception::clear();
}

$obj = new \local\test\db\UniqueVerify();
$obj->u1(2);
$obj->u2(3);
try{
	$obj->save();
	fail();
}catch(\phpman\Exception $e){
	success();
	\phpman\Exception::clear();
}
$obj = new \local\test\db\UniqueVerify();
$obj->u1(2);
$obj->u2(4);
try{
	$obj->save();
	success();
}catch(\phpman\Exception $e){
	fail();
	\phpman\Exception::clear();
}
