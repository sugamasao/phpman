<?php
namespace phpman;

trait Plugin{
	static private $_plug_funcs = [];
	private $_obj_plug_funcs = [];
	
	static public function set_class_plugin($o,$n=null){
		$g = get_called_class();
		if(is_string($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();
		if(is_string($n)) $n = [$n];
		
		$t = (is_object($o) ? 1 : 0) + (is_callable($o) ? 2 : 0);
		if($t === 1){
			if(!isset($n)) $n = get_class_methods($o);
			foreach(get_class_methods($o) as $k){
				if(in_array($k,$n)) self::$_plug_funcs[$g][$k][] = [$o,$k];
			}
		}else if($t === 3 && !empty($n)){
			foreach($n as $k) self::$_plug_funcs[$g][$k][] = $o;
		}
	}
	public function set_object_plugin($o,$n=null){
		if(is_string($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();
		if(is_string($n)) $n = [$n];
	
		$t = (is_object($o) ? 1 : 0) + (is_callable($o) ? 2 : 0);
		if($t === 1){
			if(!isset($n)) $n = get_class_methods($o);
			foreach(get_class_methods($o) as $k){
				if(in_array($k,$n)) $this->_obj_plug_funcs[$g][$k][] = [$o,$k];
			}
		}else if($t === 3 && !empty($n)){
			foreach($n as $k) $this->_obj_plug_funcs[$g][$k][] = $o;
		}
	}
	static protected function has_class_plugin($n){
		$g = get_called_class();
		return isset(self::$_plug_funcs[$g][$n]);
	}
	static protected function get_class_plugin_funcs($n){
		$g = get_called_class();
		return (isset(self::$_plug_funcs[$g][$n])) ? self::$_plug_funcs[$g][$n] : array();
	}
	protected function has_object_plugin($n){
		$g = get_class($this);
		if(isset(self::$_plug_funcs[$g][$n])) return true;
		return isset($this->_obj_plug_funcs[$g][$n]);
	}
	protected function get_object_plugin_funcs($n){
		$g = get_class($this);
		return array_merge(
					((isset(self::$_plug_funcs[$g][$n])) ? self::$_plug_funcs[$g][$n] : array())
					,((isset($this->_obj_plug_funcs[$g][$n])) ? $this->_obj_plug_funcs[$g][$n] : array())
				);
	}
	static protected function call_class_plugin_funcs($n){
		$r = null;
		$a = func_get_args();
		array_shift($a);
		foreach(static::get_class_plugin_funcs($n) as $o) $r = call_user_func_array($o,$a);
		return $r;
	}
	protected function call_object_plugin_funcs($n){
		$r = null;
		$a = func_get_args();
		array_shift($a);
		foreach(static::get_class_plugin_funcs($n) as $o) $r = call_user_func_array($o,$a);
		foreach($this->get_object_plugin_funcs($n) as $o) $r = call_user_func_array($o,$a);
		return $r;
	}
	static protected function call_func($o){
		if(!is_callable($o)) return;
		$a = func_get_args();
		array_shift($a);
		return call_user_func_array($o,$a);
	}
}