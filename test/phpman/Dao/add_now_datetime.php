<?php
\local\test\db\DateTime::create_table();
\local\test\db\AddNowDateTime::find_delete();

$obj = new \local\test\db\AddNowDateTime();
eq(null,$obj->ts());
eq(null,$obj->date());
eq(null,$obj->idate());
$obj->save();

foreach(\local\test\db\AddNowDateTime::find() as $o){
	neq(null,$o->ts());
	neq(null,$o->date());
	neq(null,$o->idate());
}