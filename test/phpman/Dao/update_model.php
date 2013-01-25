<?php
use \phpman\Q;
\local\test\db\UpdateModel::create_table();
\local\test\db\UpdateModel::find_delete();

r(new \local\test\db\UpdateModel())->value('abc')->save();
r(new \local\test\db\UpdateModel())->value('def')->save();
r(new \local\test\db\UpdateModel())->value('def')->save();
r(new \local\test\db\UpdateModel())->value('def')->save();
r(new \local\test\db\UpdateModel())->value('ghi')->save();

eq(5,\local\test\db\UpdateModel::find_count());
\local\test\db\UpdateModel::find_delete(Q::eq('value','def'));
eq(2,\local\test\db\UpdateModel::find_count());


\local\test\db\UpdateModel::find_delete();
$d1 = r(new \local\test\db\UpdateModel())->value('abc')->save();
$d2 = r(new \local\test\db\UpdateModel())->value('def')->save();
$d3 = r(new \local\test\db\UpdateModel())->value('ghi')->save();

eq(3,\local\test\db\UpdateModel::find_count());
$obj = new \local\test\db\UpdateModel();
$obj->id($d1->id())->delete();
eq(2,\local\test\db\UpdateModel::find_count());
$obj = new \local\test\db\UpdateModel();
$obj->id($d3->id())->delete();
eq(1,\local\test\db\UpdateModel::find_count());
eq('def',\local\test\db\UpdateModel::find_get()->value());


\local\test\db\UpdateModel::find_delete();
$s1 = r(new \local\test\db\UpdateModel())->value('abc')->save();
$s2 = r(new \local\test\db\UpdateModel())->value('def')->save();
$s3 = r(new \local\test\db\UpdateModel())->value('ghi')->save();

eq(3,\local\test\db\UpdateModel::find_count());
$obj = new \local\test\db\UpdateModel();
$obj->id($s1->id())->sync();
eq('abc',$obj->value());

$obj->value('hoge');
$obj->save();
$obj = new \local\test\db\UpdateModel();
$obj->id($s1->id())->sync();
eq('hoge',$obj->value());


\local\test\db\UpdateModel::find_delete();
$s1 = r(new \local\test\db\UpdateModel())->value('abc')->save();
$s2 = r(new \local\test\db\UpdateModel())->value('def')->save();

eq(2,\local\test\db\UpdateModel::find_count());
$obj = new \local\test\db\UpdateModel();
$obj->id($s1->id())->sync();
eq('abc',$obj->value());
$obj = new \local\test\db\UpdateModel();
$obj->id($s2->id())->sync();
eq('def',$obj->value());

$obj = new \local\test\db\UpdateModel();
try{
	$obj->id($s2->id()+100)->sync();
	fail();
}catch(\phpman\NotfoundException $e){
	success();
}
\local\test\db\UpdateModel::find_delete();