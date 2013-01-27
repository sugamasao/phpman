<?php
namespace phpman;

class Flow{
	private function map_pattern($map){
		$result = array();
		$var_keys = array(
						'str'=>array('name','action','media_path','theme_path','template_path','error_template','template_super')
						,'arr'=>array('modules','args')
					);
		foreach($var_keys as $t => $keys){
			foreach($keys as $k){
				$result[$k] = isset($map[$k]) ? $map[$k] : (($t == 'arr') ? array() : null);
			}
		}
		return $result;
	}
	public function read($map){
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		if(is_string($map) && preg_match('/^[\w\.]+$/',$map)) $map = array('patterns'=>array(''=>array('action'=>$map)));
		if(!isset($map['patterns']) || is_array($map['patterns'])) throw new InvalidArgumentException('patterns not found');
		foreach($map['patterns'] as $k => $v){
			if(is_int($k) || isset($map['patterns'][$k]['patterns'])){
				$map['patterns'][$k] = $this->map_pattern($map['patterns'][$k]);
			}
		}
	}
}
