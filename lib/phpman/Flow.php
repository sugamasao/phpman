<?php
namespace phpman;

class Flow{
	public function read($map){
		$automap = function($pkg_id,$name,$class,$suffix){
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
		};
		$fixed_vars = function($fixed_keys,$map,$base){
			foreach($fixed_keys as $t => $keys){
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
			return $base;
		};
		$map_pattern_keys = array(
				0=>array('name','action','redirect'
						,'media_path','theme_path'
						,'template_path','template_super'
						,'error_redirect','error_status','error_template'
						,'suffix','secure','mode'
				)
				,1=>array('modules','args','vars')
		);
		$root_keys = array(
				0=>array('media_path','theme_path'
						,'nomatch_redirect'
						,'error_redirect','error_status','error_template'
						,'secure'
				)
				,1=>array('modules')
		);
		$gen_keys = array(
				0=>array('pkg_id','class','method','num','=','url','format','pattern')
		);
				
		$target_pattern = array();
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		if(is_string($map) && preg_match('/^[\w\.]+$/',$map)) $map = array('patterns'=>array(''=>array('action'=>$map)));
		if(!isset($map['patterns']) || !is_array($map['patterns'])) throw new InvalidArgumentException('pattern not found');
		
		foreach($map['patterns'] as $k => $v){
			if(is_int($k) || isset($map['patterns'][$k]['patterns'])){
				$maps_url = is_int($k) ? null : $k.'/';
				$kpattern = $map['patterns'][$k]['patterns'];
				unset($map['patterns'][$k]['patterns']);
				
				foreach($kpattern as $pk => $pv){
					$map['patterns'][$maps_url.$pk] = $fixed_vars($map_pattern_keys,$pv,$map['patterns'][$k]);
				}
			}else{
				$map['patterns'][$k] = $fixed_vars($map_pattern_keys,$map['patterns'][$k],array());
			}
		}
		return $map;
		
		/***
			$self = new self();
			$map = $self->read(array(
						'module'=>array('xyz.opq.Qstu')
						,'patterns'=>array(
							'abc'=>array('action'=>'abc.def.Ghi')
							,'def'=>array(
								'args'=>array('A'=>1)
								,'patterns'=>array(
									'ABC'=>array(
										'action'=>'abc.def.Ghi'
										,'args'=>array('B'=>2)
									)
								)
							)
						)
					));
			if(eq(true,isset($map['patterns']['abc']))){
				eq(true,array_key_exists('name',$map['patterns']['abc']));
			}
			if(eq(true,isset($map['patterns']['def/ABC']))){
				eq(true,array_key_exists('name',$map['patterns']['def/ABC']));
			}
			eq(null,$map);
		 */
	}
}
