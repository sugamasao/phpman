<?php
namespace local\test\flow;

class AutoAction{
	/**
	 * @automap
	 */
	public function index(){
		return array('aaaa'=>'index');
	}
	
	public function abc(){
		return array('aaaa'=>'abc');		
	}
	/**
	 * @automap
	 */
	public function def(){
		return array('aaaa'=>'def');
	}
	/**
	 * @automap
	 */
	public function ghi($a){
		return array('aaaa'=>'ghi');
	}
	/**
	 * @automap
	 */
	public function jkl($a,$b,$c=null){
		return array('aaaa'=>'jkl');
	}
}