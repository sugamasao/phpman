<?php
\local\test\db\DoublePrimary::create_table();
\local\test\db\DoublePrimary::find_delete();


try{
	$obj = new \local\test\db\DoublePrimary();
	$obj->id1(1)->id2(1)->value("hoge")->save();
}catch(\phpman\Exception $e){
	fail();
}
$p = new \local\test\db\DoublePrimary();
eq("hoge",$p->id1(1)->id2(1)->sync()->value());
