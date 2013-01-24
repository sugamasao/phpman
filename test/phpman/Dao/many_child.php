<?php
\local\test\db\ManyParent::create_table();
\local\test\db\ManyParent::find_delete();

\local\test\db\ManyChild::create_table();
\local\test\db\ManyChild::find_delete();


$p1 = r(new \local\test\db\ManyParent())->value('parent1')->save();
$p2 = r(new \local\test\db\ManyParent())->value('parent2')->save();

$c1 = r(new \local\test\db\ManyChild())->parent_id($p1->id())->value('child1-1')->save();
$c2 = r(new \local\test\db\ManyChild())->parent_id($p1->id())->value('child1-2')->save();
$c3 = r(new \local\test\db\ManyChild())->parent_id($p1->id())->value('child1-3')->save();
$c4 = r(new \local\test\db\ManyChild())->parent_id($p2->id())->value('child2-1')->save();
$c5 = r(new \local\test\db\ManyChild())->parent_id($p2->id())->value('child2-2')->save();

$size = array(3,2);
$i = 0;
foreach(\local\test\db\ManyParent::find() as $r){
	eq($size[$i],sizeof($r->children()));
	$i++;
}
$i = 0;
foreach(\local\test\db\ManyParent::find_all() as $r){
	eq($size[$i],sizeof($r->children()));
	foreach($r->children() as $child){
		eq(true,($child instanceof \local\test\db\ManyChild));
		eq($r->id(),$child->parent_id());
	}
	$i++;
}
