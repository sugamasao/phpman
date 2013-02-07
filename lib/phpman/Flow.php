<?php
namespace phpman;

class Flow{
	use \phpman\InstanceModule;
	private $template_path;
	private $media_url;
	
	public function __construct(){
		$this->template_path = getcwd().'/resrouces/templates';
	}
	public function template_path($path=null){
		if(isset($path)){
			$this->template_path = str_replace("\\",'/',$path);
			if(substr($this->template_path,-1) != '/') $this->template_path .= '/';
		}
		return $this->template_path;
	}
	/**
	 * メディアのURLを設定する
	 * @param string $path
	 * @return string
	 */
	public function media_url($path=null){
		if(isset($path)){
			$this->media_url = $path;
			if(substr($this->media_url,-1) != '/') $this->media_url .= '/';
		}
		return $this->media_url;
	}
	public function execute($map){
		$result_vars = array();
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		$map = $this->read($map);
		
		foreach($map['patterns'] as $k => $pattern){
			if(preg_match("/^".(empty($k) ? '' : "\/").str_replace(array("\/",'/','@#S'),array('@#S',"\/","\/"),$k).'[\/]{0,1}$/',$pathinfo,$param_arr)){
				if(!empty($pattern['action'])){
					list($class,$method) = explode('::',$pattern['action']);
					$r = new \ReflectionClass('\\'.str_replace('.','\\',$class));
					$ins = $r->newInstance();
					$result_vars = call_user_func_array(array($ins,$method),$param_arr);
				}
				if(isset($pattern['redirect'])){
					header('Location: '.$pattern['redirect']);
					exit;
				}else if(isset($pattern['template'])){
					$template = new \phpman\Template();
					$src = $template->read(\phpman\Util::path_absolute($this->template_path,$pattern['template']));
					print($src);
					return;
				}else{
					print(json_encode($result_vars));
					return;
				}
			}
		}
		print('ERRROR');
		return;
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
				throw new \InvalidArgumentException($class.' not found');
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
						,'template','template_path','template_super'
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
		if(!isset($map['patterns']) || !is_array($map['patterns'])) throw new \InvalidArgumentException('pattern not found');
		
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
			if(isset($map['patterns'][$k]['action']) && strpos('::',$map['patterns'][$k]['action']) === false){
				foreach($automap($k,$map['patterns'][$k]['action'],$map['patterns'][$k]['name']) as $murl => $am){
					$map['patterns'][$murl] = array_merge($map['patterns'][$k],$am);
				}
				unset($map['patterns'][$k]);
			}
		}
		return $map;
	}
}
