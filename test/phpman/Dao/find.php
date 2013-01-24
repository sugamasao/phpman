<?php
use \phpman\Q;

\local\test\db\Find::create_table();
\local\test\db\Find::find_delete();

\local\test\db\SubFind::create_table();
\local\test\db\SubFind::find_delete();

\local\test\db\RefFind::create_table();
\local\test\db\RefFind::find_delete();

\local\test\db\RefRefFind::create_table();
\local\test\db\RefRefFind::find_delete();


$abc = r(new \local\test\db\Find())->order(4)->value1('abc')->value2('ABC')->save();
$def = r(new \local\test\db\Find())->order(3)->value1('def')->value2('DEF')->save();
$ghi = r(new \local\test\db\Find())->order(1)->value1('ghi')->value2('GHI')->updated('2008/12/24 10:00:00')->save();
$jkl = r(new \local\test\db\Find())->order(2)->value1('jkl')->value2('EDC')->save();
$aaa = r(new \local\test\db\Find())->order(2)->value1('aaa')->value2('AAA')->updated('2008/12/24 10:00:00')->save();
$bbb = r(new \local\test\db\Find())->order(2)->value1('bbb')->value2('Aaa')->save();
$ccc = r(new \local\test\db\Find())->order(2)->value1('ccc')->value2('aaa')->save();
$mno = r(new \local\test\db\Find())->order(2)->value1('mno')->value2(null)->save();

eq(8,sizeof(\local\test\db\Find::find_all()));
eq(5,sizeof(\local\test\db\Find::find_all(Q::eq('order',2))));
eq(3,sizeof(\local\test\db\Find::find_all(Q::eq('order',2),Q::eq('value2','aaa',Q::IGNORE))));


$sub1 = r(new \local\test\db\SubFind())->value('abc')->order(4)->save();
$sub2 = r(new \local\test\db\SubFind())->value('def')->order(3)->save();
$sub3 = r(new \local\test\db\SubFind())->value('ghi')->order(1)->save();
$sub4 = r(new \local\test\db\SubFind())->value('jkl')->order(2)->save();


eq(4,sizeof(
		\local\test\db\Find::find_all(
				Q::in('value1',\local\test\db\SubFind::find_sub('value'))
		)
	)
);

eq(3,sizeof(
		\local\test\db\Find::find_all(
				Q::in('value1',\local\test\db\SubFind::find_sub('value',Q::gte('order',2)))
		)
	)
);

$ref1 = r(new \local\test\db\RefFind())->parent_id($abc->id())->save();
$ref2 = r(new \local\test\db\RefFind())->parent_id($def->id())->save();
$ref3 = r(new \local\test\db\RefFind())->parent_id($ghi->id())->save();
$ref4 = r(new \local\test\db\RefFind())->parent_id($jkl->id())->save();
eq(4,sizeof(\local\test\db\RefFind::find_all()));
eq(1,sizeof(\local\test\db\RefFind::find_all(Q::eq('value','def'))));

eq(4,sizeof(\local\test\db\HasFind::find_all()));
$has1 = \local\test\db\HasFind::find_get(Q::eq('parent_id',$ref3->parent_id()));
if(eq(true,($has1->parent() instanceof \local\test\db\Find))){
	eq('ghi',$has1->parent()->value1());
}

$refref1 = r(new \local\test\db\RefRefFind())->parent_id($ref1->id())->save();
$refref2 = r(new \local\test\db\RefRefFind())->parent_id($ref2->id())->save();
$refref3 = r(new \local\test\db\RefRefFind())->parent_id($ref3->id())->save();
eq(3,sizeof(\local\test\db\RefRefFind::find_all()));
eq(1,sizeof(\local\test\db\RefRefFind::find_all(Q::eq('value','def'))));




foreach(\local\test\db\Find::find(Q::eq('value1','abc')) as $obj){
	eq('abc',$obj->value1());
}
foreach(\local\test\db\AbcFind::find() as $obj){
	eq('abc',$obj->value1());
}

eq(8,\local\test\db\Find::find_count());
eq(8,\local\test\db\Find::find_count('value1'));
eq(7,\local\test\db\Find::find_count('value2'));
eq(5,\local\test\db\Find::find_count(Q::eq('order',2)));
eq(4,\local\test\db\Find::find_count(
		Q::neq('value1','abc'),
		Q::ob(
				Q::b(Q::eq('order',2)),
				Q::b(Q::eq('order',4))
		),
		Q::neq('value1','aaa')
));
$q = new Q();
$q->add(Q::neq('value1','abc'));
$q->add(Q::ob(
		Q::b(Q::eq('order',2)),
		Q::b(Q::eq('order',4))
));
$q->add(Q::neq('value1','aaa'));
eq(4,\local\test\db\Find::find_count($q));

$q = new Q();
$q->add(Q::ob(
		Q::b(
			Q::eq('order',2)
			,Q::ob(
					Q::b(Q::eq('value1','ccc'))
					,Q::b(Q::eq('value2','AAA'))
			)
		),
		Q::b(Q::eq('order',4))
));
eq(3,\local\test\db\Find::find_count($q));


$paginator = new \phpman\Paginator(1,2);
eq(1,sizeof($result = \local\test\db\Find::find_all(Q::neq('value1','abc'),$paginator)));
eq('ghi',$result[0]->value1());
eq(7,$paginator->total());

$i = 0;
foreach(\local\test\db\Find::find(
		Q::neq('value1','abc'),
		Q::ob(
				Q::b(Q::eq('order',2)),
				Q::b(Q::eq('order',4))
		),
		Q::neq('value1','aaa')
) as $obj){
	$i++;
}
eq(4,$i);

$list = array('abc','def','ghi','jkl','aaa','bbb','ccc','mno');
$i = 0;
foreach(\local\test\db\Find::find() as $obj){
	eq($list[$i],$obj->value1());
	$i++;
}
foreach(\local\test\db\Find::find(Q::eq('value1','AbC',Q::IGNORE)) as $obj){
	eq('abc',$obj->value1());
}
foreach(\local\test\db\Find::find(Q::neq('value1','abc')) as $obj){
	neq('abc',$obj->value1());
}
try{
	\local\test\db\Find::find(Q::eq('value_error','abc'));
	fail();
}catch(\Exception $e){
	success();
}

$i = 0;
$r = array('aaa','bbb','ccc');
foreach(\local\test\db\Find::find(Q::startswith('value1,value2',array('aa'),Q::IGNORE)) as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value1());
	$i++;
}
eq(3,$i);

$i = 0;
$r = array('abc','jkl','ccc');
foreach(\local\test\db\Find::find(Q::endswith('value1,value2',array('c'),Q::IGNORE)) as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value1());
	$i++;
}
eq(3,$i);

$i = 0;
$r = array('abc','bbb');
foreach(\local\test\db\Find::find(Q::contains('value1,value2',array('b'))) as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value1());
	$i++;
}
eq(2,$i);

$i = 0;
$r = array('abc','jkl','ccc');
foreach(\local\test\db\Find::find(Q::endswith('value1,value2',array('C'),Q::IGNORE)) as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value1());
	$i++;
	$t[] = $obj->value1();
}
eq(3,$i);

$i = 0;
foreach(\local\test\db\Find::find(Q::in('value1',array('abc'))) as $obj){
	eq('abc',$obj->value1());
	$i++;
}
eq(1,$i);

foreach(\local\test\db\Find::find(Q::match('value1=abc')) as $obj){
	eq('abc',$obj->value1());
}
foreach(\local\test\db\Find::find(Q::match('value1=!abc')) as $obj){
	neq('abc',$obj->value1());
}
foreach(\local\test\db\Find::find(Q::match('abc')) as $obj){
	eq('abc',$obj->value1());
}
$i = 0;
$r = array('aaa','bbb','mno');
foreach(\local\test\db\Find::find(Q::neq('value1','ccc'),new \phpman\Paginator(1,3),Q::order('-id')) as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value1());
	$i++;
}
foreach(\local\test\db\Find::find(Q::neq('value1','abc'),new \phpman\Paginator(1,3),Q::order('id')) as $obj){
	eq('jkl',$obj->value1());
}
$i = 0;
$r = array('mno','aaa');
foreach(\local\test\db\Find::find(Q::neq('value1','ccc'),new \phpman\Paginator(1,2),Q::order('order,-id')) as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value1());
	$i++;
}
$result = \local\test\db\Find::find_all(Q::match('AAA',Q::IGNORE));
eq(3,sizeof($result));

$result = \local\test\db\Find::find_all(Q::match('AA',Q::IGNORE));
eq(3,sizeof($result));

$result = \local\test\db\Find::find_all(Q::eq('value2',null));
eq(1,sizeof($result));
$result = \local\test\db\Find::find_all(Q::neq('value2',null));
eq(7,sizeof($result));

$result = \local\test\db\Find::find_all(Q::eq('updated',null));
eq(6,sizeof($result));
$result = \local\test\db\Find::find_all(Q::neq('updated',null));
eq(2,sizeof($result));
eq('2008/12/24 10:00:00',$result[0]->fm_updated());

$c = 0;
for($i=0;$i<10;$i++){
	$a = $b = array();
	foreach(\local\test\db\Find::find_all(Q::random_order()) as $o) $a[] = $o->id();
	foreach(\local\test\db\Find::find_all(Q::random_order()) as $o) $b[] = $o->id();
	if($a === $b) $c++;
}
neq(10,$c);


$result = \local\test\db\Find::find_all(Q::ob(
		Q::b(Q::eq('value1','abc'))
		,Q::b(Q::eq('value2','EDC'))
));
eq(2,sizeof($result));

eq('EDC',\local\test\db\Find::find_get(Q::eq('value1','jkl'))->value2());

$i = 0;
$r = array('jkl','ccc');
foreach(\local\test\db\RefFind::find() as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value());
	$i++;
}
eq(2,$i);

$i = 0;
$r = array('jkl');
foreach(\local\test\db\RefRefFind::find() as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->value());
	$i++;
}
eq(1,$i);


$i = 0;
$r = array('jkl','ccc');
foreach(\local\test\db\HasFind::find() as $obj){
	eq(isset($r[$i]) ? $r[$i] : null,$obj->parent()->value1());
	$i++;
}
eq(2,$i);


$result = \local\test\db\Find::find_all(Q::in('value1',\local\test\db\SubFind::find_sub('value')));
eq(4,sizeof($result));
$result = \local\test\db\Find::find_all(Q::in('value1',\local\test\db\SubFind::find_sub('value',Q::lt('order',3))));
eq(2,sizeof($result));



