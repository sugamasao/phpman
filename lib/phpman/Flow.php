<?php
namespace phpman;

class Flow{
	public function execute($map){
		$result_vars = array();
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		$map = $this->read($map);

		foreach($map['patterns'] as $k => $v){
			if(preg_match("/^".(empty($k) ? '' : "\/").str_replace(array("\/",'/','@#S'),array('@#S',"\/","\/"),$k).'[\/]{0,1}$/',$pathinfo,$param_arr)){
				if(!empty($map['patterns'][$k]['action'])){
					list($class,$method) = explode('::',$map['patterns'][$k]['action']);
					$r = new \ReflectionClass('\\'.str_replace('.','\\',$class));
					$ins = $r->newInstance();
					$result_vars = call_user_func_array(array($ins,$method),$param_arr);
				}
				if(isset($map[$k]['template'])){
					
				}else{
					print(json_encode($result_vars));
				}
			}
		}
	}
	public function read($map){
		$automap = function($url,$class,$name){
			$result = array();
			
			try{
				$r = new \ReflectionClass(str_replace('.','\\',$class));
				foreach($r->getMethods() as $m){
					if($m->isPublic() && !$m->isStatic() && substr($m->getName(),0,1) != '_'){
						if((boolean)preg_match('/@automap[\s]*/',$m->getDocComment())){
							$murl = $url.(($m->getName() == 'index') ? '' : (($url == '') ? '' : '/').$m->getName()).str_repeat('/(.+)',$m->getNumberOfRequiredParameters());
							
							for($i=0;$i<=$m->getNumberOfParameters()-$m->getNumberOfRequiredParameters();$i++){
								$result[$murl] = array(
													'name'=>$name.'/'.$m->getName()
													,'action'=>$class.'::'.$m->getName()
													,'num'=>$i
													,'='=>dirname($r->getFilename())
													);
								$murl .= '/(.+)';
							}
						}
					}
				}
			}catch(\ReflectionException $e){
				throw new exception\InvalidArgumentException($class.' not found');
			}
			return $result;
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
				
		$target_pattern = array();
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		if(is_string($map) && preg_match('/^[\w\.]+$/',$map)) $map = array('patterns'=>array(''=>array('action'=>$map)));
		if(!isset($map['patterns']) || !is_array($map['patterns'])) throw new exception\InvalidArgumentException('pattern not found');
		
		foreach($map['patterns'] as $k => $v){		
			if(is_int($k) || isset($map['patterns'][$k]['patterns'])){
				$kurl = is_int($k) ? null : $k.'/';
				$kpattern = $map['patterns'][$k]['patterns'];
				unset($map['patterns'][$k]['patterns']);
				
				foreach($kpattern as $pk => $pv){
					$map['patterns'][$kurl.$pk] = $fixed_vars($map_pattern_keys,$pv,$map['patterns'][$k]);
				}
			}else{
				$map['patterns'][$k] = $fixed_vars($map_pattern_keys,$map['patterns'][$k],array());
			}
		}
		foreach($map['patterns'] as $k => $v){
			if(strpos('::',$map['patterns'][$k]['action']) === false){
				foreach($automap($k,$map['patterns'][$k]['action'],$map['patterns'][$k]['name']) as $murl => $am){
					$map['patterns'][$murl] = array_merge($map['patterns'][$k],$am);
				}
				unset($map['patterns'][$k]);
			}
		}
		return $map;
	}
}
