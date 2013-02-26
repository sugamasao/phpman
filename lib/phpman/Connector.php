<?php
namespace phpman;

trait Connector{
	private $_instance_plug = array();
	static private $_plug = array();
	
	static public function plugin($o){
		$g = get_called_class();
		if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();
		self::$_plug[$g][] = $o;
	}
	static public function load_plugins($n){
		$plugins = array();
		if(isset(self::$_plug[$g=get_called_class()])){
			foreach(self::$_plug[$g] as $k => $o){
				if(method_exists($o,$n)) $plugins[] = $o;
			}
		}
		return $plugins;
	}
	/**
	 * インスタンスモジュールを追加する
	 * @param object $o
	 * @return mixed
	 */
	final public function instance_plugin($o){
		if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) $o = new $c();
		$this->_instance_plug[] = $o;
	}
	/**
	 *
	 * 指定のインスタンスモジュールを実行する
	 * @param string $n
	 * @return mixed
	 */
	final protected function load_instance_plugins($n){
		$plugins = array();
		foreach($this->_instance_plug as $o){
			if(method_exists($o,$n)) $plugins[] = $o;
		}
		return $plugins;
	}
}