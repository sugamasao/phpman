<?php
use \phpman\Q;
\local\test\db\Calc::create_table();
\local\test\db\Calc::find_delete();


r(new \local\test\db\Calc())->price(30)->type('B')->name('AAA')->save();
r(new \local\test\db\Calc())->price(20)->type('B')->name('ccc')->save();
r(new \local\test\db\Calc())->price(20)->type('A')->name('AAA')->save();
r(new \local\test\db\Calc())->price(10)->type('A')->name('BBB')->save();

eq(80,\local\test\db\Calc::find_sum('price'));
eq(30,\local\test\db\Calc::find_sum('price',Q::eq('type','A')));

eq(array('A'=>30,'B'=>50),\local\test\db\Calc::find_sum_by('price','type'));
eq(array('A'=>30),\local\test\db\Calc::find_sum_by('price','type',Q::eq('type','A')));

eq(30,\local\test\db\Calc::find_max('price'));
eq(20,\local\test\db\Calc::find_max('price',Q::eq('type','A')));
eq('ccc',\local\test\db\Calc::find_max('name'));
eq('BBB',\local\test\db\Calc::find_max('name',Q::eq('type','A')));


eq(10,\local\test\db\Calc::find_min('price'));
eq(20,\local\test\db\Calc::find_min('price',Q::eq('type','B')));


$result = \local\test\db\Calc::find_min_by('price','type');
eq(array('A'=>10,'B'=>20),$result);
eq(array('A'=>10),\local\test\db\Calc::find_min_by('price','type',Q::eq('type','A')));

eq(20,\local\test\db\Calc::find_avg('price'));
eq(15,\local\test\db\Calc::find_avg('price',Q::eq('type','A')));

eq(array('A'=>15,'B'=>25),\local\test\db\Calc::find_avg_by('price','type'));
eq(array('A'=>15),\local\test\db\Calc::find_avg_by('price','type',Q::eq('type','A')));

eq(array('A','B'),\local\test\db\Calc::find_distinct('type'));
$result = \local\test\db\Calc::find_distinct('name',Q::eq('type','A'));
eq(array('AAA','BBB'),$result);


eq(array('A'=>2,'B'=>2),\local\test\db\Calc::find_count_by('id','type'));
eq(array('BBB'=>1,'ccc'=>1,'AAA'=>2),\local\test\db\Calc::find_count_by('type','name'));


