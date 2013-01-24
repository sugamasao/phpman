<?php




/**
 * @var serial $id
 * @var string $value
 */
class UpdateModel extends Dao{
	protected $id;
	protected $value;
}
UpdateModel::find_delete();

r(new UpdateModel())->value("abc")->save();
r(new UpdateModel())->value("def")->save();
r(new UpdateModel())->value("def")->save();
r(new UpdateModel())->value("def")->save();
r(new UpdateModel())->value("ghi")->save();

eq(5,UpdateModel::find_count());
UpdateModel::find_delete(Q::eq("value","def"));
eq(2,UpdateModel::find_count());


UpdateModel::find_delete();
$d1 = r(new UpdateModel())->value("abc")->save();
$d2 = r(new UpdateModel())->value("def")->save();
$d3 = r(new UpdateModel())->value("ghi")->save();

eq(3,UpdateModel::find_count());
$obj = new UpdateModel();
$obj->id($d1->id())->delete();
eq(2,UpdateModel::find_count());
$obj = new UpdateModel();
$obj->id($d3->id())->delete();
eq(1,UpdateModel::find_count());
eq("def",UpdateModel::find_get()->value());


UpdateModel::find_delete();
$s1 = r(new UpdateModel())->value("abc")->save();
$s2 = r(new UpdateModel())->value("def")->save();
$s3 = r(new UpdateModel())->value("ghi")->save();

eq(3,UpdateModel::find_count());
$obj = new UpdateModel();
$obj->id($s1->id())->sync();
eq("abc",$obj->value());

$obj->value("hoge");
$obj->save();
$obj = new UpdateModel();
$obj->id($s1->id())->sync();
eq("hoge",$obj->value());


UpdateModel::find_delete();
$s1 = r(new UpdateModel())->value("abc")->save();
$s2 = r(new UpdateModel())->value("def")->save();

eq(2,UpdateModel::find_count());
$obj = new UpdateModel();
$obj->id($s1->id())->sync();
eq("abc",$obj->value());
$obj = new UpdateModel();
$obj->id($s2->id())->sync();
eq("def",$obj->value());

$obj = new UpdateModel();
try{
	$obj->id($s2->id()+100)->sync();
	fail();
}catch(\org\rhaco\store\db\exception\NotfoundDaoException $e){
	success();
}
UpdateModel::find_delete();

/**
 * @var serial $id
 * @var string $value
 */
class CrossParent extends Dao{
	protected $id;
	protected $value;	
}
/**
 * @var serial $id
 * @var integer $parent_id
 * @var CrossParent $parent @['cond'=>'parent_id()id']
 */
class CrossChild extends Dao{
	protected $id;
	protected $parent_id;
	protected $parent;
}

CrossParent::find_delete();
CrossChild::find_delete();

$p1 = r(new CrossParent())->value("A")->save();
$p2 = r(new CrossParent())->value("B")->save();
$c1 = r(new CrossChild())->parent_id($p1->id())->save();
$c2 = r(new CrossChild())->parent_id($p2->id())->save();

$result = array($p1->id()=>"A",$p2->id()=>"B");
foreach(CrossChild::find_all() as $o){
	eq(true,($o->parent() instanceof CrossParent));
	eq($result[$o->parent()->id()],$o->parent()->value());
}


/**
 * @var serial $id
 * @var string $value
 */
class Replication extends Dao{
	protected $id;
	protected $value;
}
Replication::find_delete();
Replication::commit();

/**
 * @class @['table'=>'replication','update'=>false,'create'=>false,'delete'=>false]
 * @var serial $id
 * @var string $value
 */
class ReplicationSlave extends Dao{
	protected $id;
	protected $value;
}

$result = ReplicationSlave::find_all();
eq(0,sizeof($result));

try{
	$obj = new ReplicationSlave();
	$obj->value("hoge")->save();
	fail();
}catch(\BadMethodCallException $e){
	success();
}

$result = ReplicationSlave::find_all();
eq(0,sizeof($result));

try{
	$obj = new Replication();
	$obj->value("hoge");
	$obj->save();
	success();
}catch(\BadMethodCallException $e){
	fail();
}

$result = ReplicationSlave::find_all();
eq(1,sizeof($result));

$result = Replication::find_all();
if(eq(1,sizeof($result))){
	eq("hoge",$result[0]->value());

	try{
		$result[0]->value("fuga");
		$result[0]->save();
		eq("fuga",$result[0]->value());
	}catch(\BadMethodCallException $e){
		fail();
	}
}

