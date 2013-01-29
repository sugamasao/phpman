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
		if(is_string($map) && preg_match('/^[\w\.]+$/',$map)) $map = array('pattern'=>array(''=>array('action'=>$map)));
		if(!isset($map['pattern']) || !is_array($map['pattern'])) throw new InvalidArgumentException('pattern not found');
		foreach($map['pattern'] as $k => $v){
			$maps_url = is_int($k) ? null : $k.'/';			
			
			if(is_int($k) || isset($map['pattern'][$k]['pattern'])){
				foreach($map['pattern'][$k]['pattern'] as $pk => $pv){
					$map['pattern'][$maps_url.$pk] = $this->map_pattern($pv);
				}
				unset($map['pattern'][$k]['pattern']);
			}else{
				$map['pattern'][$k] = $this->map_pattern($map['pattern'][$k]);
			}
		}
		return $map;
		
		/***
			$self = new self();
			$map = $self->read(array(
						'pattern'=>array(
							'abc'=>array('action'=>'abc.def.Ghi')
							,'def'=>array(
								'pattern'=>array(
									'ABC'=>array('action'=>'abc.def.Ghi')
								)
							)
						)
					));
			if(eq(true,isset($map['pattern']['abc']))){
				eq(true,array_key_exists('name', $map['pattern']['abc']));
			}
			if(eq(true,isset($map['pattern']['def/ABC']))){
				eq(true,array_key_exists('name', $map['pattern']['def/ABC']));
			}
		 */
	}
}
