<?php
namespace phpman;

class Flow{
	private function map_pattern($url,$map,$base=array()){
		$var_keys = array(
						0=>array('name','action','redirect'
									,'media_path','theme_path'
									,'template_path','template_super'
									,'error_redirect','error_status','error_template'
									,'suffix','secure','mode'
						)
						,1=>array('modules','args','vars')
					);
		
		$root_keys = array(
							'media_path','theme_path'
							,'nomatch_redirect'
							,'error_redirect','error_status','error_template'
							,'secure'
						);
		$gen_keys = array(
						0=>array('pkg_id','class','method','num','=','url','format','pattern')
					);
		foreach($var_keys as $t => $keys){
			foreach($keys as $k){
				if($t == 0){
					$base[$k] = array_key_exists($k,$map) ? $map[$k] : null;
				}else{
					if(!isset($base[$k])) $base[$k] = array();
					if(!is_array($base[$k])) $base[$k] = array($base[$k]);
					if(isset($map[$k])) $base[$k] = array_merge((array)$base[$k],(array)$map[$k]);
				}
			}
		}
		if(!isset($base['name'])) $base['name'] = $url;
		return $base;
	}
	private function automap($pkg_id,$name,$class,$suffix){
		try{
			$r = new \ReflectionClass(str_replace('.','\\',$class));
			$automaps = $methodmaps = array();
			foreach($r->getMethods() as $m){
				if($m->isPublic() && !$m->isStatic() && substr($m->getName(),0,1) != '_'){
					$automap = (boolean)preg_match('/@automap[\s]*/',$m->getDocComment());
					if(empty($automaps) || $automap){
						$url = $k.(($m->getName() == 'index') ? '' : (($k == '') ? '' : '/').$m->getName()).str_repeat('/(.+)',$m->getNumberOfRequiredParameters());
						for($i=0;$i<=$m->getNumberOfParameters()-$m->getNumberOfRequiredParameters();$i++){
							$mapvar = array_merge($v,array('name'=>$name.'/'.$m->getName(),'class'=>$v['class'],'method'=>$m->getName(),'num'=>$i,'='=>dirname($r->getFilename()),'pkg_id'=>$pkg_id));
							if($automap){
								$automaps[$url.$suffix] = $mapvar;
							}else{
								$methodmaps[$url.$suffix] = $mapvar;
							}
							$url .= '/(.+)';
						}
					}
				}
			}
			$apps = array_merge($apps,(empty($automaps) ? $methodmaps : $automaps));
			unset($automaps,$methodmaps);
		}catch(\ReflectionException $e){
			throw new \InvalidArgumentException($v['class'].' not found');
		}
	}
	public function read($map){
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		if(is_string($map) && preg_match('/^[\w\.]+$/',$map)) $map = array('pattern'=>array(''=>array('action'=>$map)));
		if(!isset($map['pattern']) || !is_array($map['pattern'])) throw new InvalidArgumentException('pattern not found');
		foreach($map['pattern'] as $k => $v){
			if(is_int($k) || isset($map['pattern'][$k]['pattern'])){
				$maps_url = is_int($k) ? null : $k.'/';
				$kpattern = $map['pattern'][$k]['pattern'];
				unset($map['pattern'][$k]['pattern']);
				
				foreach($kpattern as $pk => $pv){
					$map['pattern'][$maps_url.$pk] = $this->map_pattern($maps_url.$pk,$pv,$map['pattern'][$k]);
				}
				unset($map['pattern'][$k]);
			}else{
				$map['pattern'][$k] = $this->map_pattern($k,$map['pattern'][$k]);
			}
		}
		return $map;
		
		/***
			$self = new self();
			$map = $self->read(array(
						'pattern'=>array(
							'abc'=>array('action'=>'abc.def.Ghi')
							,'def'=>array(
								'args'=>array('A'=>1)
								,'pattern'=>array(
									'ABC'=>array(
										'action'=>'abc.def.Ghi'
										,'args'=>array('B'=>2)
									)
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
			eq(null,$map);
		 */
	}
}
