<?php
use \phpman\Q;

\local\test\db\JoinA::create_table();
\local\test\db\JoinA::find_delete();

\local\test\db\JoinB::create_table();
\local\test\db\JoinB::find_delete();

\local\test\db\JoinC::create_table();
\local\test\db\JoinC::find_delete();

$a1 = r(new \local\test\db\JoinA())->save();
$a2 = r(new \local\test\db\JoinA())->save();
$a3 = r(new \local\test\db\JoinA())->save();
$a4 = r(new \local\test\db\JoinA())->save();
$a5 = r(new \local\test\db\JoinA())->save();
$a6 = r(new \local\test\db\JoinA())->save();

$b1 = r(new \local\test\db\JoinB())->name("aaa")->save();
$b2 = r(new \local\test\db\JoinB())->name("bbb")->save();

$c1 = r(new \local\test\db\JoinC())->a_id($a1->id())->b_id($b1->id())->save();
$c2 = r(new \local\test\db\JoinC())->a_id($a2->id())->b_id($b1->id())->save();
$c3 = r(new \local\test\db\JoinC())->a_id($a3->id())->b_id($b1->id())->save();
$c4 = r(new \local\test\db\JoinC())->a_id($a4->id())->b_id($b2->id())->save();
$c5 = r(new \local\test\db\JoinC())->a_id($a4->id())->b_id($b1->id())->save();
$c6 = r(new \local\test\db\JoinC())->a_id($a5->id())->b_id($b2->id())->save();
$c7 = r(new \local\test\db\JoinC())->a_id($a5->id())->b_id($b1->id())->save();

$re = \local\test\db\JoinABC::find_all();
eq(7,sizeof($re));

$re = \local\test\db\JoinABC::find_all(Q::eq("name","aaa"));
eq(5,sizeof($re));

$re = \local\test\db\JoinABC::find_all(Q::eq("name","bbb"));
eq(2,sizeof($re));
