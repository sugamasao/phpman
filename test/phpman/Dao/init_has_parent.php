<?php
\local\test\db\InitHasParent::create_table();
\local\test\db\ExtraInitHasParent::create_table();


$obj = new \local\test\db\InitHasParent();
$columns = $obj->columns();
eq(2,sizeof($columns));
foreach($columns as $column){
	eq(true,($column instanceof \phpman\Column));
}

try{
	$result = \local\test\db\ExtraInitHasParent::find_all();
	success();
}catch(Excepton $e){
	fail();
}
