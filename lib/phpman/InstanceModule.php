<?php
namespace phpman;

trait InstanceModule{
	private $_instance_module = array();
	/**
	 * インスタンスモジュールを追加する
	 * @param object $o
	 * @return mixed
	 */
	final public function set_object_module($o){
		$this->_instance_module[] = $o;
		return $this;
	}
	/**
	 *
	 * 指定のインスタンスモジュールを実行する
	 * @param string $n
	 * @return mixed
	 */
	final protected function object_module($n){
		$r = null;
		$a = func_get_args();
		array_shift($a);
		foreach($this->_instance_module as $o){
			if(method_exists($o,$n)) $r = call_user_func_array(array($o,$n),$a);
		}
		return $r;
	}
	/**
	 * 指定のインスタンスモジュールが存在するか
	 * @param string $n
	 * @return boolean
	 */
	final protected function has_object_module($n){
		if(isset($this->_instance_module)){
			foreach($this->_instance_module as $o){
				if(method_exists($o,$n)) return true;
			}
		}
		return false;
	}
}