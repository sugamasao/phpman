<?php

use \phpman\Q;



\local\test\db\CompositePrimaryKeys::create_table();
\local\test\db\CompositePrimaryKeys::find_delete();


r(new \local\test\db\CompositePrimaryKeys())->id1(1)->id2(1)->value('AAA1')->save();
r(new \local\test\db\CompositePrimaryKeys())->id1(1)->id2(2)->value('AAA2')->save();
r(new \local\test\db\CompositePrimaryKeys())->id1(1)->id2(3)->value('AAA3')->save();

r(new \local\test\db\CompositePrimaryKeys())->id1(2)->id2(1)->value('BBB1')->save();
r(new \local\test\db\CompositePrimaryKeys())->id1(2)->id2(2)->value('BBB2')->save();
r(new \local\test\db\CompositePrimaryKeys())->id1(2)->id2(3)->value('BBB3')->save();

\local\test\db\CompositePrimaryKeysRef::create_table();
\local\test\db\CompositePrimaryKeysRef::find_delete();
r(new \local\test\db\CompositePrimaryKeysRef())->ref_id(1)->type_id(1)->save();
r(new \local\test\db\CompositePrimaryKeysRef())->ref_id(2)->type_id(1)->save();
r(new \local\test\db\CompositePrimaryKeysRef())->ref_id(1)->type_id(2)->save();
r(new \local\test\db\CompositePrimaryKeysRef())->ref_id(2)->type_id(2)->save();


$i = 0;
$r = [
		[1,1,'AAA1'],
		[2,1,'BBB1'],
		[1,2,'AAA2'],
		[2,2,'BBB2'],
	];
foreach(\local\test\db\CompositePrimaryKeysRefValue::find(Q::order('type_id,id')) as $o){
	eq($r[$i][0],$o->ref_id());
	eq($r[$i][1],$o->type_id());
	eq($r[$i][2],$o->value());
	$i++;
}
eq(4,$i);
