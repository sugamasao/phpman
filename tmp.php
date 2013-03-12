<?php
include('bootstrap.php');

trait Base{
	public function __call($n,$args){
		
	}
}
class Hoge extends \phpman\Object{
	
}

$obj = new Hoge();

var_dump($obj);