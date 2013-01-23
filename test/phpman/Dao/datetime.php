<?php
\local\test\db\DateTime::create_table();
\local\test\db\DateTime::find_delete();

$obj = new \local\test\db\DateTime();
eq(null,$obj->ts());
eq(null,$obj->date());
eq(null,$obj->idate());
$obj->save();

foreach(\local\test\db\DateTime::find() as $o){
	eq(null,$o->ts());
	eq(null,$o->date());
	eq(null,$o->idate());
}