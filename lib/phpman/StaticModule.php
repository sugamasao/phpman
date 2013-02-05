<?php
namespace phpman;

trait StaticModule{
	static private $_module = array();
	
	static public function set_module($object){
		self::$_module[get_called_class()][] = $object;
	}
	static public function module($name){
		$r = null;
		if(isset(self::$_module[$g=get_called_class()])){
			$a = func_get_args();
			array_shift($a);
		
			foreach(self::$_module[$g] as $k => $o){
				if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) self::$_module[$g][$k] = $o = new $c();
				if(method_exists($o,$name)) $r = call_user_func_array(array($o,$name),$a);
			}
		}
		return $r;
	}
	static public function has_module($name){
		foreach((isset(self::$_module[$g=get_called_class()]) ? self::$_module[$g] : array()) as $k => $o){
			if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) self::$_module[$g][$k] = $o = new $c();
			if(method_exists($o,$name)) return true;
		}
		return false;
	}
}
