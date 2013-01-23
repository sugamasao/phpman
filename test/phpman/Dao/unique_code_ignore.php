<?php
\local\test\db\UniqueCodeIgnore::create_table();
\local\test\db\UniqueCodeIgnore::find_delete();


$obj = new \local\test\db\UniqueCodeIgnore();
eq(null,$obj->code1());
$obj->save();

foreach(\local\test\db\UniqueCodeIgnore::find() as $o){
	neq(null,$o->code1());
	eq(1,strlen($o->code1()));
	eq(true,ctype_digit($o->code1()));
	eq('9',$o->code1());
}
