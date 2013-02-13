<?php
namespace phpman;

class Flow{
	use \phpman\InstanceModule;
	private $branch_url;
	private $app_url;
	private $media_url;
	private $template_path;
	private $package_media_url = 'package/resources/media';		
	
	static private $get_maps = false;
	static private $output_maps = array();
	
	static private function entry_file(){
		foreach(debug_backtrace(false) as $d){
			if($d['file'] !== __FILE__) return $d['file'];
		}
		new \RuntimeException('no entry file');
	}
	public function __construct($app_url=null){
		$f = str_replace('\\','/',self::entry_file());
		$this->app_url = \phpman\Conf::get('app_url',$app_url);
		
		if(empty($this->app_url)) $this->app_url = dirname('http://localhost/'.preg_replace('/.+\/workspace\/(.+)/','\\1',$f));
		if(substr($this->app_url,-1) != '/') $this->app_url .= '/';
		$this->template_path = str_replace("\\",'/',\phpman\Conf::get('template_path',self::resource_path('templates')));
		if(substr($this->template_path,-1) != '/') $this->template_path .= '/';
		$this->media_url = str_replace("\\",'/',\phpman\Conf::get('media_url',$this->app_url.'resources/media/'));
		if(substr($this->media_url,-1) != '/') $this->media_url .= '/';
		$this->branch_url = $this->app_url.((($branch = substr(basename($f),0,-4)) !== 'index') ? $branch.'/' : '');
	}
	/**
	 * ワーキングディレクトリを返す
	 * @return string
	 */
	static public function work_path($path=null){
		$dir = str_replace('\\','/',\phpman\Conf::get('work_dir',getcwd().'/work/'));
		if(substr($dir,-1) != '/') $dir = $dir.'/';
		return $dir.$path;
	}
	/**
	 * リソースディレクトリを返す
	 * @return string
	 */
	static public function resource_path($path=null){
		$dir = str_replace('\\','/',\phpman\Conf::get('resource_dir',getcwd().'/resources/'));
		if(substr($dir,-1) != '/') $dir = $dir.'/';
		return $dir.$path;
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
	/**
	 * mapsを取得する
	 * @param string $file
	 * @return array
	 */
	static public function get_maps($file){
		$key = basename($file);
		if(!isset(self::$output_maps[$key])){
			self::$get_maps = true;
			self::$output_maps[$key] = array();
			try{
				ob_start();
					include_once($file);
				ob_end_clean();
			}catch(\Exception $e){
				\phpman\Log::error($e);
			}
		}
		return self::$output_maps[$key];
	}
	public function execute($map){
		$result_vars = array();
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		$map = $this->read($map);
		
		if(self::$get_maps){
			self::$output_maps[basename(self::entry_file())] = $map['patterns'];
			self::$get_maps = false;
			return;
		}		
		foreach($map['patterns'] as $k => $pattern){
			if(preg_match('/^'.(empty($k) ? '' : '\/').str_replace(array('\/','/','@#S'),array('@#S','\/','\/'),$k).'[\/]{0,1}$/',$pathinfo,$param_arr)){
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
	private function read($map){
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
													,'@'=>$r->getFilename()
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
		list($http,$https) = array($this->branch_url,str_replace('http://','https://',$this->branch_url));
		$conf_secure = (\phpman\Conf::get('secure',true) === true);
		$url_format_func = function($url,$map_secure,$conf_secure,$http,$https){
			$secure = ($conf_secure && $map_secure);
			$num = 0;
			$format = \phpman\Util::path_absolute(
						($map_secure ? $https : $http)
						,(empty($url)) ? '' : 
										substr(
											preg_replace_callback("/([^\\\\])(\(.*?[^\\\\]\))/"
												,function($n){return $n[1].'%s';}
												,' '.$url
												,-1
												,$num
											)
											,1
										)
					);
			return array('format'=>str_replace(array('\\\\','\\.','_ESC_'),array('_ESC_','.','\\'),$format)
						,'num'=>$num
					);
		};				
		foreach($map['patterns'] as $k => $v){
			if(!isset($v['name'])) $map['patterns'][$k]['name'] = $k;
			if(isset($v['action']) && strpos($v['action'],'::') === false){
				foreach($automap($k,$map['patterns'][$k]['action'],$map['patterns'][$k]['name']) as $murl => $am){
					$map['patterns'][$murl] = array_merge($map['patterns'][$k],$am);
					$map['patterns'][$murl] = array_merge($map['patterns'][$murl],$url_format_func($murl,$v['secure'],$conf_secure,$http,$https));
				}
				unset($map['patterns'][$k]);
			}else{
				$map['patterns'][$k] = array_merge($map['patterns'][$k],$url_format_func($k,$v['secure'],$conf_secure,$http,$https));
			}
		}
		return $map;
	}
}
