<?php
\local\test\db\Replication::create_table();
\local\test\db\Replication::find_delete();


$result = \local\test\db\ReplicationSlave::find_all();
eq(0,sizeof($result));

try{
	$obj = new \local\test\db\ReplicationSlave();
	$obj->value('hoge')->save();
	fail();
}catch(\phpman\BadMethodCallException $e){
	success();
}

$result = \local\test\db\ReplicationSlave::find_all();
eq(0,sizeof($result));

try{
	$obj = new \local\test\db\Replication();
	$obj->value('hoge');
	$obj->save();
	success();
}catch(\phpman\BadMethodCallException $e){
	fail();
}

$result = \local\test\db\ReplicationSlave::find_all();
eq(1,sizeof($result));

$result = \local\test\db\Replication::find_all();
if(eq(1,sizeof($result))){
	eq('hoge',$result[0]->value());

	try{
		$result[0]->value('fuga');
		$result[0]->save();
		eq('fuga',$result[0]->value());
	}catch(\phpman\BadMethodCallException $e){
		fail();
	}
}

