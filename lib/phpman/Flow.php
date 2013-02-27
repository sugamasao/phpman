<?php
namespace phpman;

class Flow{
	use \phpman\Plugin;
	private $branch_url;
	private $app_url;
	private $media_url;
	private $template_path;
	private $package_media_url = 'package/resources/media';		
	
	static private $get_maps = false;
	static private $output_maps = array();
	
	public function __construct($app_url=null){
		$f = str_replace('\\','/',self::entry_file());
		$this->app_url = \phpman\Conf::get('app_url',$app_url);
		
		if(empty($this->app_url)) $this->app_url = dirname('http://localhost/'.preg_replace('/.+\/workspace\/(.+)/','\\1',$f));
		if(substr($this->app_url,-1) != '/') $this->app_url .= '/';
		$this->template_path = str_replace('\\','/',\phpman\Conf::get('template_path',self::resource_path('templates')));
		if(substr($this->template_path,-1) != '/') $this->template_path .= '/';
		$this->media_url = str_replace('\\','/',\phpman\Conf::get('media_url',$this->app_url.'resources/media/'));
		if(substr($this->media_url,-1) != '/') $this->media_url .= '/';
		$this->branch_url = $this->app_url.((($branch = substr(basename($f),0,-4)) !== 'index') ? $branch.'/' : '');
	}
	static private function entry_file(){
		foreach(debug_backtrace(false) as $d){
			if($d['file'] !== __FILE__) return $d['file'];
		}
		new \RuntimeException('no entry file');
	}
	/**
	 * ワーキングディレクトリを返す
	 * @return string $path
	 */
	static public function work_path($path=null){
		$dir = str_replace('\\','/',\phpman\Conf::get('work_dir',getcwd().'/work/'));
		if(substr($dir,-1) != '/') $dir = $dir.'/';
		return $dir.$path;
	}
	/**
	 * リソースディレクトリを返す
	 * @return string $path
	 */
	static public function resource_path($path=null){
		$dir = str_replace('\\','/',\phpman\Conf::get('resource_dir',getcwd().'/resources/'));
		if(substr($dir,-1) != '/') $dir = $dir.'/';
		return $dir.$path;
	}
	/**
	 * テンプレートディレクトリを指定する
	 * @param string $path
	 */
	public function template_path($path=null){
		if(isset($path)){
			$this->template_path = str_replace('\\','/',$path);
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
					include($file);
				ob_end_clean();
			}catch(\Exception $e){
				\phpman\Log::error($e);
			}
		}
		return self::$output_maps[$key];
	}
	private function redirect($url,$map){
		if(strpos($url,'://') !== false) \phpman\HttpHeader::redirect($url);
		foreach($map['patterns'] as $p){
			if($p['name'] == $url){
				\phpman\HttpHeader::redirect(str_replace('%s','',$p['format']));
			}
		}
		throw new \InvalidArgumentException($url.' not found');
	}
	private function template(array $vars,$path,$media=null){
		if(!isset($media)) $media = $this->media_url;
		$template = new \phpman\Template($media);
		$template->cp($vars);
		$src = $template->read($path);
		print($src);
		exit;
	}
	/**
	 * 実行する
	 * @param array $map
	 */
	public function execute($map){
		$result_vars = array();
		$pathinfo = preg_replace("/(.*?)\?.*/","\\1",(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null));
		$map = $this->read($map);

		if(self::$get_maps){
			self::$output_maps[basename(self::entry_file())] = $map['patterns'];
			self::$get_maps = false;
			return;
		}
		if(preg_match('/^\/'.preg_quote($this->package_media_url,'/').'\/(\d+)\/(.+)$/',$pathinfo,$m)){
			foreach($map['patterns'] as $p){
				if((int)$p['pattern_id'] === (int)$m[1] && isset($p['@'])){
					\phpman\HttpFile::attach($p['@'].'/resources/media/'.$m[2]);
				}
			}
			\phpman\HttpHeader::send_status(404);
			exit;
		}
		foreach($map['patterns'] as $k => $pattern){
			if(preg_match('/^'.(empty($k) ? '' : '\/').str_replace(array('\/','/','@#S'),array('@#S','\/','\/'),$k).'[\/]{0,1}$/',$pathinfo,$param_arr)){
				if($pattern['secure'] === true && \phpman\Conf::get('secure',true) !== false){
					if(substr(\phpman\Request::current_url(),0,5) === 'http:' &&
						(!isset($_SERVER['HTTP_X_FORWARDED_HOST']) 
							|| (isset($_SERVER['HTTP_X_FORWARDED_PORT']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']))
						)
					){
						header('Location: '.preg_replace('/^.+(:\/\/.+)$/','https\\1',\phpman\Request::current_url()));
						exit;
					}
				}		
				try{
					if(isset($pattern['redirect'])){
						$this->redirect($pattern['redirect'],$map);
					}
					$result_vars = array();
					if(!empty($pattern['action'])){
						list($class,$method) = explode('::',$pattern['action']);
						$ins = $this->str_reflection($class);
						if($ins instanceof \phpman\Plugin){
							foreach(array($map['plugins'],$pattern['plugins']) as $plugins){
								foreach($plugins as $m){
									$ins->instance_plugin($this->str_reflection($m));
								}
							}
						}
						$result_vars = call_user_func_array(array($ins,$method),$param_arr);
						if($result_vars === null) $result_vars = array();
					}
					if(isset($pattern['template'])){
						$this->template($result_vars,\phpman\Util::path_absolute($this->template_path,$pattern['template']));
					}else if(
						isset($pattern['@'])
						&& is_file($t=$pattern['@'].'/resources/templates/'.preg_replace('/^.+::/','',$pattern['action']).'.html')
					){
						$this->template($result_vars,$t,$this->branch_url.$this->package_media_url.'/'.$pattern['pattern_id']);
					}else{
						print(json_encode(array('result'=>$result_vars)));
						return;
					}
				}catch(\Exception $e){
					if(isset($map['error_status'])) \phpman\HttpHeader::send_status($map['error_status']);
					if(isset($pattern['@']) && is_file($t=$pattern['@'].'/resources/templates/error.html')){
						$this->template($result_vars,$t,$this->branch_url.$this->package_media_url.'/'.$pattern['pattern_id']);
					}
					if(isset($pattern['error_redirect'])){
						$this->redirect($pattern['error_redirect'],$map);
					}
					if(isset($pattern['error_template'])){
						$this->template($result_vars,\phpman\Util::path_absolute($this->template_path,$pattern['error_template']));
					}
					if(isset($map['error_redirect'])){
						$this->redirect($map['error_redirect'],$map);
					}
					if(isset($map['error_template'])){
						$this->template($result_vars,\phpman\Util::path_absolute($this->template_path,$map['error_template']));
					}
					print(json_encode(array('error'=>array('message'=>$e->getMessage()))));
					return;
				}
			}
		}
		if(isset($map['nomatch_redirect'])){
			$this->redirect($map['nomatch_redirect'],$map);
		}
		\phpman\HttpHeader::send_status(404);
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
													,'@'=>(basename($r->getFilename(),'php') === basename(dirname($r->getFilename())) ? dirname($r->getFilename()) : substr($r->getFilename(),0,-4))
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
						,'media_path'
						,'template','template_path','template_super'
						,'error_redirect','error_status','error_template'
						,'suffix','secure','mode'
				)
				,1=>array('plugins','args','vars')
		);
		$root_keys = array(
				0=>array('media_path'
						,'nomatch_redirect'
						,'error_redirect','error_status','error_template'
						,'secure'
				)
				,1=>array('plugins')
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
		$expand_map = $map;
		unset($expand_map['patterns']);
		$pattern_id = 1;
		foreach($map['patterns'] as $k => $v){
			$v['pattern_id'] = $pattern_id++;
			if(!isset($v['name'])) $v['name'] = $k;
			if(isset($v['action']) && strpos($v['action'],'::') === false){				
				foreach($automap($k,$v['action'],$v['name']) as $murl => $am){
					$v = array_merge($v,$am);
					$expand_map['patterns'][$murl] = array_merge($v,$url_format_func($murl,$v['secure'],$conf_secure,$http,$https));
				}
			}else{
				$expand_map['patterns'][$k] = array_merge($v,$url_format_func($k,$v['secure'],$conf_secure,$http,$https));
			}
		}
		return $expand_map;
	}
	private function str_reflection($package){
		if(is_object($package)) return $package;
		$class_name = substr($package,strrpos($package,'.')+1);
		try{
			$r = new \ReflectionClass('\\'.str_replace('.','\\',$package));
			return $r->newInstance();
		}catch(\ReflectionException $e){
			if(!empty($class_name)){
				try{
					$r = new \ReflectionClass($class_name);
					return $r->newInstance();
				}catch(\ReflectionException $f){
				}
			}
			throw $e;
		}
	}
}
