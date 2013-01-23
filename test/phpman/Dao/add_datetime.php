<?php
\local\test\db\AddDateTime::create_table();
\local\test\db\AddDateTime::find_delete();

$obj = new \local\test\db\AddDateTime();
eq(null,$obj->ts());
eq(null,$obj->date());
eq(null,$obj->idate());
$obj->save();

foreach(\local\test\db\AddNowDateTime::find() as $o){
	neq(null,$o->ts());
	neq(null,$o->date());
	neq(null,$o->idate());
}
