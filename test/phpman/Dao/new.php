<?php

$obj = new \local\test\db\NewDao();
$obj->query(new \phpman\Daq(\phpman\DbConnect::create_table_sql($obj)));

$obj = new \local\test\db\NewDao();
$obj->value('aaa');
$obj->save();

$obj = new \local\test\db\NewDao();
$obj->value('bbb');
$obj->save();


foreach(\local\test\db\NewDao::find() as $o){
	eq(null,$o->value());
}

