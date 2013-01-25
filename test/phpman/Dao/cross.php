<?php
\local\test\db\CrossParent::create_table();
\local\test\db\CrossParent::find_delete();
\local\test\db\CrossChild::create_table();
\local\test\db\CrossChild::find_delete();

$p1 = r(new \local\test\db\CrossParent())->value('A')->save();
$p2 = r(new \local\test\db\CrossParent())->value('B')->save();
$c1 = r(new \local\test\db\CrossChild())->parent_id($p1->id())->save();
$c2 = r(new \local\test\db\CrossChild())->parent_id($p2->id())->save();

$result = array($p1->id()=>'A',$p2->id()=>'B');
foreach(\local\test\db\CrossChild::find_all() as $o){
	eq(true,($o->parent() instanceof \local\test\db\CrossParent));
	eq($result[$o->parent()->id()],$o->parent()->value());
}
