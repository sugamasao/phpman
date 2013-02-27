<?php
namespace phpman;

trait Plugin{
	private $_instance_plug = array();
	static private $_plug = array();
	
	static public function plugin($o){
		$g = get_called_class();
		if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();
		self::$_plug[$g][] = $o;
	}
	static protected function plugins($n){
		$plugins = array();
		if(isset(self::$_plug[$g=get_called_class()])){
			foreach(self::$_plug[$g] as $k => $o){
				if(method_exists($o,$n)) $plugins[] = clone($o);
			}
		}
		return $plugins;
	}
	static protected function has_plugin($n){
		if(isset(self::$_plug[$g=get_called_class()])){
			foreach(self::$_plug[$g] as $k => $o){
				if(method_exists($o,$n)) return true;
			}
		}
		return false;
	}
	static protected function call_plugins($n){
		$r = null;
		$a = func_get_args();
		array_shift($a);
		foreach(static::plugins($n) as $o) $r = call_user_func_array(array($o,$n),$a);
		return $r;
	}

	
	public function instance_plugin($o){
		if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();
		$this->_instance_plug[] = $o;
	}
	protected function instance_plugins($n){
		$plugins = array();
		foreach($this->_instance_plug as $o){
			if(method_exists($o,$n)) $plugins[] = clone($o);
		}
		return $plugins;
	}
	protected function has_instance_plugin($n){
		foreach($this->_instance_plug as $o){
			if(method_exists($o,$n)) return true;
		}
		return false;
	}
	protected function call_instance_plugins($n){
		$r = null;
		$a = func_get_args();
		array_shift($a);
		foreach($this->instance_plugins($n) as $o) $r = call_user_func_array(array($o,$n),$a);
		return $r;
	}
}