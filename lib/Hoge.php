<?php
class Hoge extends \phpman\Object{
	use \phpman\StaticModule;
	
	public function abc(){
		static::set_module('hoge');
	}
}