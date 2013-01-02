<?php
$obj = newclass('
 		class * extends \phpman\Object{
 		protected $aaa = "hoge";
 		protected $ccc = 123;
 		}
 		');
$self = new \phpman\Xml('abc',$obj);
eq('<abc><aaa>hoge</aaa><ccc>123</ccc></abc>',$self->get());

$n = get_class($obj);
$obj1 = clone($obj);
$obj2 = clone($obj);
$obj3 = clone($obj);
$obj2->ccc(456);
$obj3->ccc(789);
$arr = array($obj1,$obj2,$obj3);
$self = new \phpman\Xml('abc',$arr);
eq(
		sprintf('<abc>'
				.'<%s><aaa>hoge</aaa><ccc>123</ccc></%s>'
				.'<%s><aaa>hoge</aaa><ccc>456</ccc></%s>'
				.'<%s><aaa>hoge</aaa><ccc>789</ccc></%s>'
				.'</abc>',
				$n,$n,$n,$n,$n,$n
		),$self->get());



$obj = new \phpman\Request();
$obj->rm_vars();
$obj->vars('aaa','hoge');
$obj->vars('ccc',123);
$self = new \phpman\Xml('abc',$obj);
eq('<abc><aaa>hoge</aaa><ccc>123</ccc></abc>',$self->get());
