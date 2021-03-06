<?php
namespace phpman;

class Util{
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function file_read($filename){
		if(!is_readable($filename) || !is_file($filename)) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		return file_get_contents($filename);
	}
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 */
	static public function file_write($filename,$src=null,$lock=true){
		if(empty($filename)) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		$b = is_file($filename);
		self::mkdir(dirname($filename));
		if(false === file_put_contents($filename,(string)$src,($lock ? LOCK_EX : 0))) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		if(!$b) chmod($filename,0777);
	}
	/**
	 * ファイルに追記する
	 * @param string $filename ファイルパス
	 * @param string $src 追加する内容
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function file_append($filename,$src=null,$lock=true){
		self::mkdir(dirname($filename));
		if(false === file_put_contents($filename,(string)$src,FILE_APPEND|(($lock) ? LOCK_EX : 0))) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param oct $permission
	 */
	static public function mkdir($source,$permission=0775){
		$bool = true;
		if(!is_dir($source)){
			try{
				$list = explode('/',str_replace('\\','/',$source));
				$dir = '';
				foreach($list as $d){
					$dir = $dir.$d.'/';
					if(!is_dir($dir)){
						$bool = mkdir($dir);
						if(!$bool) return $bool;
						chmod($dir,$permission);
					}
				}
			}catch(\ErrorException $e){
				throw new \InvalidArgumentException(sprintf('permission denied `%s`',$source));
			}
		}
		return $bool;
	}
	/**
	 * 移動
	 * @param string $source 移動もとのファイルパス
	 * @param string $dest 移動後のファイルパス
	 */
	static public function mv($source,$dest){
		if(is_file($source) || is_dir($source)){
			self::mkdir(dirname($dest));
			return rename($source,$dest);
		}
		throw new \InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * 削除
	 * $sourceがフォルダで$inc_selfがfalseの場合は$sourceフォルダ以下のみ削除
	 * @param string $source 削除するパス
	 * @param boolean $inc_self $sourceも削除するか
	 * @return boolean
	 */
	static public function rm($source,$inc_self=true){
		if(!is_dir($source) && !is_file($source)) return true;
		if(!$inc_self){
			foreach(self::dir($source) as $d) self::rm($d);
			foreach(self::ls($source) as $f) self::rm($f);
			return true;
		}
		if(is_writable($source)){
			if(is_dir($source)){
				if($handle = opendir($source)){
					$list = array();
					while($pointer = readdir($handle)){
						if($pointer != '.' && $pointer != '..') $list[] = sprintf('%s/%s',$source,$pointer);
					}
					closedir($handle);
					foreach($list as $path){
						if(!self::rm($path)) return false;
					}
				}
				if(rmdir($source)){
					clearstatcache();
					return true;
				}
			}else if(is_file($source) && unlink($source)){
				clearstatcache();
				return true;
			}
		}
		throw new \InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * コピー
	 * $sourceがフォルダの場合はそれ以下もコピーする
	 * @param string $source コピー元のファイルパス
	 * @param string $dest コピー先のファイルパス
	 */
	static public function copy($source,$dest){
		if(!is_dir($source) && !is_file($source)) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$source));
		if(is_dir($source)){
			$bool = true;
			if($handle = opendir($source)){
				while($pointer = readdir($handle)){
					if($pointer != '.' && $pointer != '..'){
						$srcname = sprintf('%s/%s',$source,$pointer);
						$destname = sprintf('%s/%s',$dest,$pointer);
						if(false === ($bool = self::copy($srcname,$destname))) break;
					}
				}
				closedir($handle);
			}
			return $bool;
		}else{
			$dest = (is_dir($dest))	? $dest.basename($source) : $dest;
			if(is_writable(dirname($dest))){
				copy($source,$dest);
			}
			return is_file($dest);
		}
	}
	/**
	 * ディレクトリ内のイテレータ
	 * @param string $directory  検索対象のファイルパス
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return RecursiveDirectoryIterator
	 */
	static public function ls($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)){
			$it = new \RecursiveDirectoryIterator($directory,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS);
			if($recursive) $it = new \RecursiveIteratorIterator($it);
			return $it;
		}
		throw new \InvalidArgumentException(sprintf('permission denied `%s`',$directory));
	}
	static private function parse_filename($filename){
		$filename = preg_replace("/[\/]+/",'/',str_replace("\\",'/',trim($filename)));
		return (substr($filename,-1) == '/') ? substr($filename,0,-1) : $filename;
	}
	
	/**
	 * 絶対パスを返す
	 * @param string $a
	 * @param string $b
	 * @return string
	 */
	static public function path_absolute($a,$b){
		if($b === '' || $b === null) return $a;
		if($a === '' || $a === null || preg_match("/^[a-zA-Z]+:/",$b)) return $b;
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$a,$h)){
			$a = preg_replace("/^(.+?)[".(($b[0] === '#') ? '#' : "#\?")."].*$/","\\1",$a);
			if($b[0] == '#' || $b[0] == '?') return $a.$b;
			if(substr($a,-1) != '/') $b = (substr($b,0,2) == './') ? '.'.$b : (($b[0] != '.' && $b[0] != '/') ? '../'.$b : $b);
			if($b[0] == '/' && isset($h[0])) return $h[0].$b;
		}else if($b[0] == '/'){
			return $b;
		}
		$p = array(array('://','/./','//'),array('#R#','/','/'),array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#T#\\1","\\1#W#\\2",''),array('#R#','#T#','#W#'),array('://','/',':/'));
		$a = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$a));
		$b = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$b));
		$d = $t = $r = '';
		if(strpos($a,'#R#')){
			list($r) = explode('/',$a,2);
			$a = substr($a,strlen($r));
			$b = str_replace('#T#','',$b);
		}
		$al = preg_split("/\//",$a,-1,PREG_SPLIT_NO_EMPTY);
		$bl = preg_split("/\//",$b,-1,PREG_SPLIT_NO_EMPTY);

		for($i=0;$i<sizeof($al)-substr_count($b,'../');$i++){
			if($al[$i] != '.' && $al[$i] != '..') $d .= $al[$i].'/';
		}
		for($i=0;$i<sizeof($bl);$i++){
			if($bl[$i] != '.' && $bl[$i] != '..') $t .= '/'.$bl[$i];
		}
		$t = (!empty($d)) ? substr($t,1) : $t;
		$d = (!empty($d) && $d[0] != '/' && substr($d,0,3) != '#T#' && !strpos($d,'#W#')) ? '/'.$d : $d;
		return str_replace($p[4],$p[5],$r.$d.$t);
		/***
		 eq("http://www.rhaco.org/doc/ja/index.html",self::path_absolute("http://www.rhaco.org/","/doc/ja/index.html"));
		eq("http://www.rhaco.org/doc/ja/index.html",self::path_absolute("http://www.rhaco.org/","../doc/ja/index.html"));
		eq("http://www.rhaco.org/doc/ja/index.html",self::path_absolute("http://www.rhaco.org/","./doc/ja/index.html"));
		eq("http://www.rhaco.org/doc/ja/index.html",self::path_absolute("http://www.rhaco.org/doc/ja/","./index.html"));
		eq("http://www.rhaco.org/doc/index.html",self::path_absolute("http://www.rhaco.org/doc/ja","./index.html"));
		eq("http://www.rhaco.org/doc/index.html",self::path_absolute("http://www.rhaco.org/doc/ja/","../index.html"));
		eq("http://www.rhaco.org/index.html",self::path_absolute("http://www.rhaco.org/doc/ja/","../../index.html"));
		eq("http://www.rhaco.org/index.html",self::path_absolute("http://www.rhaco.org/doc/ja/","../././.././index.html"));
		eq("/www/rhaco/index.html",self::path_absolute("/www/rhaco/","index.html"));
		eq("/www.rhaco.org/doc/ja/index.html",self::path_absolute("/www.rhaco.org/doc/ja/","index.html"));
		eq("/www.rhaco.org/doc/index.html",self::path_absolute("/www.rhaco.org/doc/ja/","../index.html"));
		eq("/www.rhaco.org/doc/ja/action.html/index.html",self::path_absolute('/www.rhaco.org/doc/ja/action.html','index.html'));
		eq("/www.rhaco.org/index.html",self::path_absolute("/www.rhaco.org/doc/ja/","../../index.html"));
		eq("/www.rhaco.org/index.html",self::path_absolute("/www.rhaco.org/doc/ja/","../././.././index.html"));
		eq("c:/www.rhaco.org/doc/index.html",self::path_absolute("c:/www.rhaco.org/doc/ja/","../index.html"));
		eq("http://www.rhaco.org/index.html",self::path_absolute("http://www.rhaco.org/doc/ja","/index.html"));
		eq("http://www.rhaco.org/doc/ja/index.html",self::path_absolute('http://www.rhaco.org/doc/ja/action.html','index.html'));
		eq("http://www.rhaco.org/doc/ja/sample.cgi?param=test",self::path_absolute('http://www.rhaco.org/doc/ja/sample.cgi?query=key','?param=test'));
		eq("http://www.rhaco.org/doc/index.html",self::path_absolute('http://www.rhaco.org/doc/ja/action.html', '../../index.html'));
		eq("http://www.rhaco.org/?param=test",self::path_absolute('http://www.rhaco.org/doc/ja/sample.cgi?query=key', '../../../?param=test'));
		eq("/doc/ja/index.html",self::path_absolute('/',"/doc/ja/index.html"));
		eq("/index.html",self::path_absolute('/',"index.html"));
		eq("http://www.rhaco.org/login",self::path_absolute("http://www.rhaco.org","/login"));
		eq("http://www.rhaco.org/login",self::path_absolute("http://www.rhaco.org/login",""));
		eq("http://www.rhaco.org/login.cgi",self::path_absolute("http://www.rhaco.org/logout.cgi","login.cgi"));
		eq("http://www.rhaco.org/hoge/login.cgi",self::path_absolute("http://www.rhaco.org/hoge/logout.cgi","login.cgi"));
		eq("http://www.rhaco.org/hoge/login.cgi",self::path_absolute("http://www.rhaco.org/hoge/#abc/aa","login.cgi"));
		eq("http://www.rhaco.org/hoge/abc.html#login",self::path_absolute("http://www.rhaco.org/hoge/abc.html","#login"));
		eq("http://www.rhaco.org/hoge/abc.html#login",self::path_absolute("http://www.rhaco.org/hoge/abc.html#logout","#login"));
		eq("http://www.rhaco.org/hoge/abc.html?abc=aa#login",self::path_absolute("http://www.rhaco.org/hoge/abc.html?abc=aa#logout","#login"));
		eq("javascript::alert('')",self::path_absolute("http://www.rhaco.org/hoge/abc.html","javascript::alert('')"));
		eq("mailto::hoge@rhaco.org",self::path_absolute("http://www.rhaco.org/hoge/abc.html","mailto::hoge@rhaco.org"));
		eq("http://www.rhaco.org/hoge/login.cgi",self::path_absolute("http://www.rhaco.org/hoge/?aa=bb/","login.cgi"));
		eq("http://www.rhaco.org/login",self::path_absolute("http://rhaco.org/hoge/hoge","http://www.rhaco.org/login"));
		eq("http://localhost:8888/spec/css/style.css",self::path_absolute("http://localhost:8888/spec/","./css/style.css"));
		*/
	}
	/**
	 * パスの前後にスラッシュを追加／削除を行う
	 * @param string $path ファイルパス
	 * @param boolean $prefix 先頭にスラッシュを存在させるか
	 * @param boolean $postfix 末尾にスラッシュを存在させるか
	 * @return string
	 */
	static public function path_slash($path,$prefix,$postfix){
		if($path == '/') return ($postfix === true) ? '/' : '';
		if(!empty($path)){
			if($prefix === true){
				if($path[0] != '/') $path = '/'.$path;
			}else if($prefix === false){
				if($path[0] == '/') $path = substr($path,1);
			}
			if($postfix === true){
				if(substr($path,-1) != '/') $path = $path.'/';
			}else if($postfix === false){
				if(substr($path,-1) == '/') $path = substr($path,0,-1);
			}
		}
		return $path;
		/***
		 eq("/abc/",self::path_slash("/abc/",null,null));
		eq("/abc/",self::path_slash("abc",true,true));
		eq("/abc/",self::path_slash("/abc/",true,true));
		eq("abc/",self::path_slash("/abc/",false,true));
		eq("/abc",self::path_slash("/abc/",true,false));
		eq("abc",self::path_slash("/abc/",false,false));
		*/
	}
}
