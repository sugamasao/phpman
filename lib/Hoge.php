<?php
class Hoge extends \phpman\Object{
	use \phpman\Module;
	
	public function abc(){
		static::set_module('hoge');
	}
}