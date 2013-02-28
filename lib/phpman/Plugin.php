<?php
namespace phpman;

trait Plugin{
	static private $_plug_funcs = [];
	private $_obj_plug_funcs = [];
	
	static public function set_class_plugin($o,$n=null){
		$g = get_called_class();
		if(is_string($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();		
		$t = (is_object($o) ? 1 : 0) + (is_callable($o) ? 2 : 0);
		if($t === 1){
			self::$_plug_funcs[$g][] = $o;
		}else if($t === 3 && !empty($n)){
			self::$_plug_funcs[$g][] = [$o,(string)$n];
		}
	}
	public function set_object_plugin($o,$n=null){
		if(is_string($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();	
		$t = (is_object($o) ? 1 : 0) + (is_callable($o) ? 2 : 0);
		if($t === 1){
			$this->_obj_plug_funcs[$g][] = $o;
		}else if($t === 3 && !empty($n)){
			$this->_obj_plug_funcs[$g][] = [$o,(string)$n];
		}
	}
	static protected function has_class_plugin($n){
		$g = get_called_class();
		if(isset(self::$_plug_funcs[$g])){
			foreach(self::$_plug_funcs[$g] as $o){
				if(is_array($o)){
					if($n == $o[1]) return true;
				}else if(method_exists($o,$n)){
					return true;
				}
			}
		}
		return false;
	}
	protected function has_object_plugin($n){
		if(static::has_class_plugin($n)) return true;

		$g = get_class($this);
		if(isset($this->_obj_plug_funcs[$g])){
			foreach($this->_obj_plug_funcs[$g] as $o){
				if(is_array($o)){
					if($n == $o[1]) return true;
				}else if(method_exists($o,$n)){
					return true;
				}
			}
		}
		return false;
	}
	
	static protected function get_class_plugin_funcs($n){
		$rtn = [];
		$g = get_called_class();
		if(isset(self::$_plug_funcs[$g])){
			foreach(self::$_plug_funcs[$g] as $o){
				if(is_array($o)){
					if($n == $o[1]) $rtn[] = $o;
				}else if(method_exists($o,$n)){
					$rtn[] = [$o,$n];
				}
			}
		}
		return $rtn;
	}
	protected function get_object_plugin_funcs($n){
		$rtn = static::get_class_plugin_funcs($n);
		$g = get_class($this);
		if(isset($this->_obj_plug_funcs[$g])){
			foreach($this->_obj_plug_funcs[$g] as $o){
				if(is_array($o)){
					if($n == $o[1]) $rtn[] = $o;
				}else if(method_exists($o,$n)){
					$rtn[] = [$o,$n];
				}
			}
		}
		return $rtn;
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