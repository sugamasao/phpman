<?php
namespace phpman;
/**
 * アーカイブの作成、解凍を行う
 * @author tokushima
 */
class Archive{
	private $base_dir;
	private $tree = array(5=>array(),0=>array());

	public function __construct($dir=null){
		if(isset($dir) &&  is_dir($dir)){
			$this->base_dir = $dir;
			$this->add($dir);
		}
	}	
	/**
	 * エントリ名から取り除くパスを設定する
	 * @param string $base_dir アーカイブ内部での名前から取り除く文字
	 * @return $this
	 */
	public function base_dir($base_dir){
		$this->base_dir = $base_dir;
		return $this;
	}
	/**
	 * 指定したパスからアーカイブに追加する
	 * @param string $path 追加するファイルへのパス
	 * @param string $base_dir アーカイブ内部での名前から取り除く文字
	 * @return $this
	 */	
	public function add($path,$base_dir=null){
		if(!isset($base_dir)) $base_dir = $this->base_dir;
		if(is_dir($path)){
			if($base_dir != $path) $this->tree[5][$this->source($path,$base_dir)] = $path;
			$l = $this->dirs($path);
			foreach($l[0] as $p) $this->tree[0][$this->source($p,$base_dir)] = $p;
			foreach($l[5] as $p) $this->tree[5][$this->source($p,$base_dir)] = $p;
		}else if(is_file($path)){
			$this->tree[0][$this->source($path,$base_dir)] = $path;
		}
		return $this;
	}
	/**
	 * tarを出力する
	 * @param string $filename 出力するファイルパス
	 * @return $this
	 */
	public function write($filename){
		$fp = fopen($filename,'wb');
		foreach(array(5,0) as $t){
			if(!empty($this->tree[$t])) ksort($this->tree[$t]);
			foreach($this->tree[$t] as $a => $n){
				if(strpos($n,'/.') === false){
					if($t == 0){
						$i = stat($n);
						$rp = fopen($n,'rb');
							fwrite($fp,$this->tar_head($t,$a,filesize($n),fileperms($n),$i[4],$i[5],filemtime($n)));
							while(!feof($rp)){
								$buf = fread($rp,512);
								if($buf !== '') fwrite($fp,pack('a512',$buf));
							}
						fclose($rp);
					}else{
						fwrite($fp,$this->tar_head($t,$a,0,0777));
					}
				}
			}
		}
		fwrite($fp,pack('a1024',null));
		fclose($fp);
		return $this;		
	}
	private function tar_head($type,$filename,$filesize=0,$fileperms=0777,$uid=0,$gid=0,$update_date=null){
		if(strlen($filename) > 99) throw new \InvalidArgumentException('invalid filename (max length 100) `'.$filename.'`');
		if($update_date === null) $update_date = time();
		$checksum = 256;
		$first = pack('a100a8a8a8a12A12',$filename,
						sprintf('%06s ',decoct($fileperms)),sprintf('%06s ',decoct($uid)),sprintf('%06s ',decoct($gid)),
						sprintf('%011s ',decoct(($type === 0) ? $filesize : 0)),sprintf('%11s',decoct($update_date)));
		$last = pack('a1a100a6a2a32a32a8a8a155a12',$type,null,null,null,null,null,null,null,null,null);
		for($i=0;$i<strlen($first);$i++) $checksum += ord($first[$i]);
		for($i=0;$i<strlen($last);$i++) $checksum += ord($last[$i]);
		return $first.pack('a8',sprintf('%6s ',decoct($checksum))).$last;
	}
	/**
	 * tgzを出力する
	 * @param string $filename 出力するファイルパス
	 * @return $this
	 */
	public function gzwrite($filename){
		$fp = gzopen($filename,'wb9');
			$this->write($filename.'.tar');
			$fr = fopen($filename.'.tar','rb');
				while(!feof($fr)){
					gzwrite($fp,fread($fr,4096));
				}
			fclose($fr);
		gzclose($fp);
		unlink($filename.'.tar');
		chmod($filename,0777);
		return $this;
	}
	/**
	 * zipを出力する
	 * @param string $filename 出力するファイルパス
	 * @return $this
	 */
	public function zipwrite($filename){
		$zip = new \ZipArchive();
		if($zip->open($filename,\ZipArchive::CREATE) === true){
			foreach(array(5,0) as $t){
				ksort($this->tree[$t]);
				foreach($this->tree[$t] as $a => $n){
					if(strpos($n,'/.') === false){
						if($t == 0){
							$zip->addFile($n,$a);
						}else{
							$zip->addEmptyDir($a);
						}
					}
				}
			}
			$zip->close();
			chmod($filename,0777);
		}
		return $this;
	}
	private function source($path,$base_dir){
		$source = (strpos($path,$base_dir) !== false) ? str_replace($base_dir,'',$path) : $path;
		if(strpos($source,'://') !== false) $source = preg_replace('/^.*:\/\/(.+)$/','\\1',$source);
		if($source[0] == '/') $source = substr($source,1);
		return $source;		
	}
	private function dirs($dir){
		$list = array(5=>array(),0=>array());
		if($h = opendir($dir)){
			while($p = readdir($h)){
				if($p != '.' && $p != '..'){
					$s = sprintf('%s/%s',$dir,$p);
					if(is_dir($s)){
						$list[5][$s] = $s;
						$r = $this->dirs($s);
						$list[5] = array_merge($list[5],$r[5]);
						$list[0] = array_merge($list[0],$r[0]);
					}else{
						$list[0][$s] = $s;
					}
				}
			}
			closedir($h);
		}
		return $list;
	}

	/**
	 * tarを解凍する
	 * @param string $inpath 解凍するファイルパス
	 * @param string $outpath 展開先のファイルパス
	 */
	static public function untar($inpath,$outpath){
		if(substr($outpath,-1) != '/') $outpath = $outpath.'/';
		if(!is_dir($outpath)) Util::mkdir($outpath,0777);
		$fr = fopen($inpath,'rb');

		while(!feof($fr)){
			$buf = fread($fr,512);
			if(strlen($buf) < 512) break;
			$data = unpack('a100name/a8mode/a8uid/a8gid/a12size/a12mtime/'
							.'a8chksum/'
							.'a1typeflg/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix',
							$buf);
			if(!empty($data['name'])){
				if($data['name'][0] == '/') $data['name'] = substr($data['name'],1);
				$f = $outpath.$data['name'];
				switch((int)$data['typeflg']){
					case 0:	
						$size = base_convert($data['size'],8,10);
						$cur = ftell($fr);
						if(!is_dir(dirname($f))) Util::mkdir(dirname($f),0777);
						$fw = fopen($f,'wb');
							for($i=0;$i<=$size;$i+=512){
								fwrite($fw,fread($fr,512));
							}
						fclose($fw);
						$skip = $cur + (ceil($size / 512) * 512);
						fseek($fr,$skip,SEEK_SET);
						break;
					case 5:
						if(!is_dir($f)) Util::mkdir($f,0777);
						break;
				}
			}
		}
		fclose($fr);
	}
	/**
	 * tar.gz(tgz)を解凍してファイル書き出しを行う
	 * @param string $inpath 解凍するファイルパス
	 * @param string $outpath 解凍先のファイルパス
	 */
	static public function untgz($inpath,$outpath){
		$fr = gzopen($inpath,'rb');
		$ft = fopen($outpath.'.tar','wb');
			while(!gzeof($fr)) fwrite($ft,gzread($fr,4096));
		fclose($ft);
		gzclose($fr);
		self::untar($outpath.'.tar',$outpath);
		unlink($outpath.'.tar');
		return true;
	}
	static public function unzip($inpath,$outpath){
		if(substr($outpath,-1) != '/') $outpath = $outpath.'/';
		if(!is_dir($outpath)) Util::mkdir($outpath,0777);
		$zip = new \ZipArchive();
		if($zip->open($inpath) !== true) throw new \ErrorException('failed to open stream');
		$zip->extractTo($outpath);
		$zip->close();
	}
}
/**
 * 定義情報を格納するクラス
 * @author tokushima
 */
class Conf{
	static private $value = array();
	/**
	 * 定義情報をセットする
	 * @param string $class
	 * @param string $key
	 * @param mixed $value
	 */
	static public function set($class,$key,$value){
		$class = str_replace("\\",'.',$class);
		if($class[0] === '.') $class = substr($class,1);
		if(func_num_args() > 3){
			$value = func_get_args();
			array_shift($value);
			array_shift($value);
		}
		if(!isset(self::$value[$class]) || !array_key_exists($key,self::$value[$class])) self::$value[$class][$key] = $value;
	}
	/**
	 * 定義されているか
	 * @param string $class
	 * @param string $key
	 * @return boolean
	 */
	static public function exists($class,$key){
		return (isset(self::$value[$class]) && array_key_exists($key,self::$value[$class]));
	}
	/**
	 * 定義情報を取得する
	 * @param string $key
	 * @param mixed $default
	 */
	static public function get($key,$default=null,$return_vars=null){
		if(strpos($key,'@') === false){
			list(,$d) = debug_backtrace(false);
			$class = str_replace('\\','.',$d['class']);
			if($class[0] === '.') $class = substr($class,1);
			if(preg_match('/^(.+?\.[A-Z]\w*)/',$class,$m)) $class = $m[1];
		}else{
			list($class,$key) = explode('@',$key,2);
		}
		$result = self::exists($class,$key) ? self::$value[$class][$key] : $default;
		if(is_array($return_vars)){
			if(empty($return_vars) && !is_array($result)) return array($result);
			$result_vars = array();
			foreach($return_vars as $var_name) $result_vars[] = isset($result[$var_name]) ? $result[$var_name] : null;
			return $result_vars;
		}
		return $result;
	}
}
/**
 * 例外の集合
 * @author tokushima
 * @var integer $line
 * @var string $code
 * @var string $file
 * @var text $message
 */
class Exception extends \Exception{
	static private $self;
	protected $id;
	private $messages = array();
	protected $group;

	public function __construct($message=null,$group='exceptions'){
		if(is_object($group)){
			$class_name = is_object($group) ? get_class($group) : $group;
			$l = str_replace("\\",'.',$class_name);
			$s = basename(str_replace("\\",'/',$class_name));
			if(isset($message)) $this->message = str_replace(array('{L}','{S}'),array($l,$s),$message);
			$this->group = $l;
		}else{
			if(isset($message)) $this->message = $message;
			$this->group = $group;
		}
	}
	final public function getGroup(){
		return $this->group;
	}

	/**
	 * 現在の例外群を返す
	 * @return self
	 */
	static public function trace(){
		return (isset(self::$self) ? self::$self : new self());
	}
	/**
	 * ID
	 * @return string
	 */
	static public function id(){
		return (self::$self !== null) ? self::$self->id : '000000';
	}
	/**
	 * IDの復元
	 * @param string $id
	 */
	static public function parse_id($id){
		return sprintf('%04d, %02d: %s',base_convert(substr($id,0,2),36,10),base_convert(substr($id,2,1),36,10),substr($id,3,3));
	}
	/**
	 * Exceptionを追加する
	 * @param Exception $exception 例外
	 * @param string $group グループ名
	 */
	static public function add(\Exception $exception,$group=null){
		if(self::$self === null){
			$self = new self('multiple exceptions');
			$self->id = strtoupper(base_convert(date('md'),10,36).base_convert(date('G'),10,36).base_convert(mt_rand(1296,46655),10,36));
			self::$self = $self;
		}
		if($exception instanceof self){
			foreach($exception->messages as $key => $es){
				foreach($es as $e) self::$self->messages[$key][] = $e;
			}
		}else{
			if(empty($group)) $group = ($exception instanceof self) ? $exception->getGroup() : 'exceptions';
			if(is_object($group)) $group = str_replace("\\",'.',get_class($group));
			self::$self->messages[$group][] = $exception;
		}
	}
	/**
	 * 追加されたExceptionのクリア
	 */
	static public function clear(){
		self::$self = null;
	}
	/**
	 * 追加されたExceptionからメッセージ配列を取得
	 * @param string $group グループ名
	 * @return string[]
	 */
	static public function messages($group=null){
		$result = array();
		foreach(self::gets($group) as $m) $result[] = $m->getMessage();
		return $result;
	}
	/**
	 * 追加されたExceptionからException配列を取得
	 * @param string $group グループ名
	 * @return Exception[]
	 */
	static public function gets($group=null){
		if(!self::has($group)) return array();
		if(!empty($group)) return self::$self->messages[$group];
		$result = array();
		foreach(self::$self->messages as $k => $exceptions) $result = array_merge($result,$exceptions);
		return $result;
	}
	/**
	 * 追加されたグループ名一覧
	 * @return string[]
	 */
	static public function groups(){
		if(!self::has()) return array();
		return array_keys(self::$self->messages);
	}
	/**
	 * Exceptionが追加されているか
	 * @param string $group グループ名
	 * @return boolean
	 */
	static public function has($group=null){
		return (isset(self::$self) && ((empty($group) && !empty(self::$self->messages)) || (!empty($group) && isset(self::$self->messages[$group]))));
	}
	/**
	 * Exceptionが追加されていればthrowする
	 * @param string $group グループ名
	 */
	static public function throw_over($group=null){
		if(self::has($group)) throw self::$self;
	}
	/**
	 * (non-PHPdoc)
	 * @see Exception::__toString()
	 */
	public function __toString(){
		if(self::$self === null || empty(self::$self->messages)) return null;
		$exceptions = self::gets();
		return count($exceptions).' exceptions [#'.self::$self->id.']: '
				.PHP_EOL.implode(PHP_EOL.PHP_EOL,array_map(function($e){ return (string)$e; },$exceptions));
	}
}
/**
 * 基底クラス
 * @author tokushima
 */
class Object{
	static private $_m = array(array(),array(),array());
	private $_im = array(array(),array());
	protected $_;

	/**
	 * クラスのアノテーションを取得する
	 * @param string $n アノテーション名
	 * @param mixed $df デフォルト値
	 * @return mixed
	 */
	final static public function anon($n,$df=null){
		$c = get_called_class();
		if(!isset(self::$_m[1][$c])){
			$d = '';
			$r = new \ReflectionClass($c);
			while($r->getName() != __CLASS__){
				$d = $r->getDocComment().$d;
				$r = $r->getParentClass();
			}
			self::$_m[1][$c] = self::anon_decode($d,'class');
		}
		return isset(self::$_m[1][$c][$n]) ? self::$_m[1][$c][$n] : $df;
	}
	/**
	 * アノテーション文字列をデコードする
	 * @param text $d デコード対象となる文字列
	 * @param string $name デコード対象のアノテーション名
	 * @param string $ns_name 型宣言を取得する場合の名前空間
	 * @param string $doc_name 説明を取得する場合の添字
	 * @throws \InvalidArgumentException
	 */
	final static public function anon_decode($d,$name,$ns_name=null,$doc_name=null){
		$result = array();
		$decode_func = function($s){
			if(empty($s)) return array();
			if(PHP_MAJOR_VERSION > 5 || PHP_MINOR_VERSION > 3){
				$d = @eval('return '.$s.';');
			}else{
				if(preg_match_all('/([\"\']).+?\\1/',$s,$m)){
					foreach($m[0] as $v) $s = str_replace($v,str_replace(array('[',']'),array('#{#','#}#'),$v),$s);
				}
				$d = @eval('return '.str_replace(array('[',']','#{#','#}#'),array('array(',')','[',']'),$s).';');
			}
			if(!is_array($d)) throw new \InvalidArgumentException('annotation error : `'.$s.'`');
			return $d;
		};
		if($ns_name !== null && preg_match_all("/@".$name."\s([\.\w_]+[\[\]\{\}]*)\s\\\$([\w_]+)(.*)/",$d,$m)){
			foreach($m[2] as $k => $n){
				$as = (false !== ($s=strpos($m[3][$k],'@['))) ? substr($m[3][$k],$s+1,strrpos($m[3][$k],']')-$s) : null;
				$decode = $decode_func($as);
				$result[$n] = (isset($result[$n])) ? array_merge($result[$n],$decode) : $decode;

				if(!empty($doc_name)) $result[$n][$doc_name] = ($s===false) ? $m[3][$k] : substr($m[3][$k],0,$s);
				list($result[$n]['type'],$result[$n]['attr']) = (false != ($h = strpos($m[1][$k],'{}')) || false !== ($l = strpos($m[1][$k],'[]'))) ? array(substr($m[1][$k],0,-2),(isset($h) && $h !== false) ? 'h' : 'a') : array($m[1][$k],null);
				if(!ctype_lower($t=$result[$n]['type'])){
					if($t[0]!='\\') $t='\\'.$t;
					if(!class_exists($t=str_replace('.','\\',$t)) && !class_exists($t='\\'.$ns_name.$t)) throw new \InvalidArgumentException($t.' '.$result[$n]['type'].' not found');
					$result[$n]['type'] = (($t[0] !== '\\') ? '\\' : '').str_replace('.','\\',$t);
				}
			}
		}else if(preg_match_all("/@".$name."\s.*@(\[.*\])/",$d,$m)){
			foreach($m[1] as $j){
				$decode = $decode_func($j);
				$result = array_merge($result,$decode);
			}
		}
		return $result;
	}
	final public function __construct(){
		$c = get_class($this);
		if(!isset(self::$_m[0][$c])){
			self::$_m[0][$c] = array();
			$d = null;
			$t = new \ReflectionClass($this);
			$ns = $t->getNamespaceName();
			while($t->getName() != __CLASS__){
				$d = $t->getDocComment().$d;
				$t = $t->getParentClass();
			}
			$d = preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array('/'.'**','*'.'/'),'',$d));
			self::$_m[0][$c] = self::anon_decode($d,'var',$ns);
			foreach(array_keys(self::$_m[0][$c]) as $n){
				if(self::$_m[0][$c][$n]['type'] == 'serial'){
					self::$_m[0][$c][$n]['primary'] = true;
				}else if(self::$_m[0][$c][$n]['type'] == 'choice' && method_exists($this,'__choices_'.$n.'__')){
					self::$_m[0][$c][$n]['choices'] = $this->{'__choices_'.$n.'__'}();
				}
			}
			if(method_exists($this,'__anon__')) $this->__anon__($d);
		}
		if(method_exists($this,'__new__')){
			$args = func_get_args();
			call_user_func_array(array($this,'__new__'),$args);
		}
		if(method_exists($this,'__init__')) $this->__init__();
	}
	final public function __call($n,$args){
		if($n[0] != '_'){
			list($c,$p) = (in_array($n,array_keys(get_object_vars($this)))) ? array((empty($args) ? 'get' : 'set'),$n) : (preg_match("/^([a-z]+)_([a-zA-Z].*)$/",$n,$m) ? array($m[1],$m[2]) : array(null,null));
			if(method_exists($this,$am=('___'.$c.'___'))){
				$this->_ = $p;
				return call_user_func_array(array($this,(method_exists($this,$m=('__'.$c.'_'.$p.'__')) ? $m : $am)),$args);
			}
		}
		throw new \ErrorException(get_class($this).'::'.$n.' method not found');

	}
	final public function __destruct(){
		if(method_exists($this,'__del__')) $this->__del__();
	}
	final public function __toString(){
		return (method_exists($this,'__str__')) ? (string)$this->__str__() : get_class($this);
	}
	/**
	 * クラスモジュールを追加する
	 * @param object $o
	 */
	final static public function set_module($o){
		self::$_m[2][get_called_class()][] = $o;
	}
	/**
	 * 指定のクラスモジュールを実行する
	 * @param string $n
	 * @return mixed
	 */
	final static protected function module($n){
		$r = null;
		if(isset(self::$_m[2][$g=get_called_class()])){
			$a = func_get_args();
			array_shift($a);

			foreach(self::$_m[2][$g] as $k => $o){
				if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) self::$_m[2][$g][$k] = $o = new $c();
				if(method_exists($o,$n)) $r = call_user_func_array(array($o,$n),$a);
			}
		}
		return $r;
	}
	/**
	 * 指定のクラスモジュールが存在するか
	 * @param string $n
	 * @return boolean
	 */
	final static protected function has_module($n){
		foreach((isset(self::$_m[2][$g=get_called_class()]) ? self::$_m[2][$g] : array()) as $k => $o){
			if(!is_object($o) && class_exists(($c='\\'.str_replace('.','\\',$o)))) self::$_m[2][$g][$k] = $o = new $c();
			if(method_exists($o,$n)) return true;
		}
		return false;
	}
	/**
	 * インスタンスモジュールを追加する
	 * @param object $o
	 * @return mixed
	 */
	final public function set_object_module($o){
		$this->_im[1][] = $o;
		return $this;
	}
	/**
	 * 
	 * 指定のインスタンスモジュールを実行する
	 * @param string $n
	 * @return mixed
	 */
	final protected function object_module($n){
		$r = null;
		$a = func_get_args();
		array_shift($a);
		foreach($this->_im[1] as $o){
			if(method_exists($o,$n)) $r = call_user_func_array(array($o,$n),$a);
		}
		return $r;
	}
	/**
	 * 指定のインスタンスモジュールが存在するか
	 * @param string $n
	 * @return boolean
	 */
	final protected function has_object_module($n){
		if(isset($this->_im[1])){
			foreach($this->_im[1] as $o){
				if(method_exists($o,$n)) return true;
			}
		}
		return false;
	}
	/**
	 * プロパティのアノテーションを取得する
	 * @param string $p プロパティ名
	 * @param string $n アノテーション名
	 * @param mixed $d デフォルト値
	 * @parama boolean $f 値をデフォルト値で上書きするか
	 * @return mixed
	 */
	final public function prop_anon($p,$n,$d=null,$f=false){
		if($f) $this->_im[0][$p][$n] = $d;
		$v = isset($this->_im[0][$p][$n]) ? $this->_im[0][$p][$n] : ((isset(self::$_m[0][get_class($this)][$p][$n])) ? self::$_m[0][get_class($this)][$p][$n] : $d);
		if(is_string($v) && $d !== $v) $v = preg_replace('/array\((.+?)\)/','[\\1]',$v);
		return $v;
	}
	/**
	 * アクセス可能なプロパティを取得する
	 * @return mixed{}
	 */
	final public function props(){
		$r = array();
		foreach(get_object_vars($this) as $n => $v){
			if($n[0] != '_') $r[$n] = $this->{$n}();
		}
		return $r;

	}
	/**
	 * 連想配列としての値を返す
	 * @return array
	 */
	public function hash(){
		if(method_exists($this,'__hash__')) return $this->__hash__();
		$r = array();
		foreach($this->props() as $n => $v){
			if($this->prop_anon($n,'get') !== false && $this->prop_anon($n,'hash') !== false){
				switch($this->prop_anon($n,'type')){
					case 'boolean': $r[$n] = $v; break;
					default: $r[$n] = $this->{'fm_'.$n}();
				}
			}
		}
		return $r;

	}
	final private function ___get___(){
		if($this->prop_anon($this->_,'get') === false) throw new \InvalidArgumentException('not permitted');
		if($this->prop_anon($this->_,'attr') !== null) return (is_array($this->{$this->_})) ? $this->{$this->_} : (is_null($this->{$this->_}) ? array() : array($this->{$this->_}));
		return $this->{$this->_};
	}
	final private function ___set___($v){
		if($this->prop_anon($this->_,'set') === false) throw new \InvalidArgumentException('not permitted');
		$t = $this->prop_anon($this->_,'type');
		switch($this->prop_anon($this->_,'attr')){
			case 'a':
				foreach(func_get_args() as $a) $this->{$this->_}[] = $this->set_prop($this->_,$t,$a);
				break;
			case 'h':
				$v = (func_num_args() === 2) ? array(func_get_arg(0)=>func_get_arg(1)) : (is_array($v) ? $v : array((string)$v=>$v));
				foreach($v as $k => $a) $this->{$this->_}[$k] = $this->set_prop($this->_,$t,$a);
				break;
			default:
				$this->{$this->_} = $this->set_prop($this->_,$t,$v);
		}
		return $this;
	}
	/**
	 * プロパティに値をセットする
	 * @param string $name
	 * @param string $type
	 * @param mixed $value
	 * @throws \InvalidArgumentException
	 */
	protected function set_prop($name,$type,$value){
		try{
			$c = get_class($this);
			return Validation::set($type,$value,isset(self::$_m[0][$c][$name]) ? self::$_m[0][$c][$name] : array());
		}catch(\InvalidArgumentException $e){
			throw new \InvalidArgumentException($this->_.' must be an '.$type);
		}
	}
	final private function ___rm___(){
		if($this->prop_anon($this->_,'set') === false) throw new \InvalidArgumentException('not permitted');
		if($this->prop_anon($this->_,'attr') === null){
			$this->{$this->_} = null;
		}else{
			if(func_num_args() == 0){
				$this->{$this->_} = array();
			}else{
				foreach(func_get_args() as $k) unset($this->{$this->_}[$k]);
			}
		}
	}
	final private function ___fm___($f=null,$d=null){
		$p = $this->_;
		$v = (method_exists($this,$m=('__get_'.$p.'__'))) ? call_user_func(array($this,$m)) : $this->___get___();
		switch($this->prop_anon($p,'type')){
			case 'timestamp': return ($v === null) ? null : (date((empty($f) ? 'Y/m/d H:i:s' : $f),(int)$v));
			case 'date': return ($v === null) ? null : (date((empty($f) ? 'Y/m/d' : $f),(int)$v));
			case 'time':
				if($v === null) return 0;
				$h = floor($v / 3600);
				$i = floor(($v - ($h * 3600)) / 60);
				$s = floor($v - ($h * 3600) - ($i * 60));
				$m = str_replace(' ','0',rtrim(str_replace('0',' ',(substr(($v - ($h * 3600) - ($i * 60) - $s),2,12)))));
				return (($h == 0) ? '' : $h.':').(sprintf('%02d:%02d',$i,$s)).(($m == 0) ? '' : '.'.$m);
			case 'intdate': if($v === null) return null;
							return str_replace(array('Y','m','d'),array(substr($v,0,-4),substr($v,-4,2),substr($v,-2,2)),(empty($f) ? 'Y/m/d' : $f));
			case 'boolean': return ($v) ? (isset($d) ? $d : 'true') : (empty($f) ? 'false' : $f);
		}
		return $v;
	}
	final private function ___ar___($i=null,$j=null){
		$v = $this->___get___();
		$a = is_array($v) ? $v : (($v === null) ? array() : array($v));
		if(isset($i)){
			$c = 0;
			$l = ((isset($j) ? $j : sizeof($a)) + $i);
			$r = array();
			foreach($a as $k => $p){
				if($i <= $c && $l > $c) $r[$k] = $p;
				$c++;
			}
			return $r;
		}
		return $a;
	}
	final private function ___in___($k=null,$d=null){
		$v = $this->___get___();
		return (isset($k)) ? ((is_array($v) && isset($v[$k]) && $v[$k] !== null) ? $v[$k] : $d) : $d;
	}
	final private function ___is___($k=null){
		$v = $this->___get___();
		if($this->prop_anon($this->_,'attr') !== null){
			if($k === null) return !empty($v);
			$v = isset($v[$k]) ? $v[$k] : null;
		}
		switch($this->prop_anon($this->_,'type')){
			case 'string':
			case 'text': return (isset($v) && $v !== '');
		}
		return (boolean)(($this->prop_anon($this->_,'type') == 'boolean') ? $v : isset($v));
	}
}
/**
 * ページを管理するモデル
 * @author tokushima
 */
class Paginator implements \IteratorAggregate{
	private $query_name = 'page';
	private $vars = array();
	private $current;
	private $offset;
	private $limit;
	private $order;
	private $total;
	private $first;
	private $last;
	private $contents = array();
	private $dynamic = false;
	private $tmp = array(null,null,array(),null,false);

	public function getIterator(){
		return new \ArrayIterator(array(
						'current'=>$this->current()
						,'limit'=>$this->limit()
						,'offset'=>$this->offset()
						,'total'=>$this->total()
						,'order'=>$this->order()
				));

	}
	/**
	 * pageを表すクエリの名前
	 * @param string $name
	 * @return string
	 */
	public function query_name($name=null){
		if(isset($name)) $this->query_name = $name;
		return (empty($this->query_name)) ? 'page' : $this->query_name;
	}
	/**
	 * query文字列とする値をセットする
	 * @param string $key
	 * @param string $value
	 */
	public function vars($key,$value){
		$this->vars[$key] = $value;
	}
	/**
	 * 現在位置
	 * @param integer $value
	 * @return mixed
	 */
	public function current($value=null){
		if(isset($value) && !$this->dynamic){
			$value = intval($value);
			$this->current = ($value === 0) ? 1 : $value;
			$this->offset = $this->limit * round(abs($this->current - 1));
		}
		return $this->current;
	}
	/**
	 * 終了位置
	 * @param integer $value
	 * @return integer
	 */
	public function limit($value=null){
		if(isset($value)) $this->limit = $value;
		return $this->limit;
	}
	/**
	 * 開始位置
	 * @param integer $value
	 * @return integer
	 */
	public function offset($value=null){
		if(isset($value)) $this->offset = $value;
		return $this->offset;
	}
	/**
	 * 最後のソートキー
	 * @param string $value
	 * @param boolean $asc
	 * return string
	 */
	public function order($value=null,$asc=true){
		if(isset($value)) $this->order = ($asc ? '' :'-').(string)(is_array($value) ? array_shift($value) : $value);
		return $this->order;
	}
	/**
	 * 合計
	 * @param integer $value
	 * @return integer
	 */
	public function total($value=null){
		if(isset($value) && !$this->dynamic){
			$this->total = intval($value);
			$this->first = 1;
			$this->last = ($this->total == 0 || $this->limit == 0) ? 0 : intval(ceil($this->total / $this->limit));
		}
		return $this->total;
	}
	/**
	 * 最初のページ番号
	 * @return integer
	 */
	public function first(){
		return $this->first;
	}
	/**
	 * 最後のページ番号
	 * @return integer
	 */
	public function last(){
		return $this->last;
	}
	/**
	 * 指定のページ番号が最初のページか
	 * @param integer $page
	 * @return boolean
	 */
	public function is_first($page){
		return ($this->which_first($page) !== $this->first);
	}
	/**
	 * 指定のページ番号が最後のページか
	 * @param integer $page
	 * @return boolean
	 */
	public function is_last($page){
		return ($this->which_last($page) !== $this->last());
	}
	/**
	 * 動的コンテンツのPaginaterか
	 * @return boolean
	 */
	public function is_dynamic(){
		return $this->dynamic;
	}
	/**
	 * コンテンツ
	 * @param mixed $mixed
	 * @return array
	 */
	public function contents($mixed=null){
		if(isset($mixed)){
			if($this->dynamic){
				if(!$this->tmp[4] && $this->current == (isset($this->tmp[3]) ? (isset($mixed[$this->tmp[3]]) ? $mixed[$this->tmp[3]] : null) : $mixed)) $this->tmp[4] = true;
				if($this->tmp[4]){
					if($this->tmp[0] === null && ($size=sizeof($this->contents)) <= $this->limit){
						if(($size+1) > $this->limit){
							$this->tmp[0] = $mixed;
						}else{
							$this->contents[] = $mixed;
						}
					}
				}else{
					if(sizeof($this->tmp[2]) >= $this->limit) array_shift($this->tmp[2]);
					$this->tmp[2][] = $mixed;
				}
			}else{
				$this->total($this->total+1);
				if($this->page_first() <= $this->total && $this->total <= ($this->offset + $this->limit)){
					$this->contents[] = $mixed;
				}
			}
		}
		return $this->contents;
	}
	/**
	 * 動的コンテンツのPaginater
	 * @param integer $paginate_by １ページの要素数
	 * @param string $marker 基点となる値
	 * @param string $key 対象とするキー
	 * @return self
	 */
	static public function dynamic_contents($paginate_by=20,$marker=null,$key=null){
		$self = new self($paginate_by);
		$self->dynamic = true;
		$self->tmp[3] = $key;
		$self->current = $marker;
		$self->total = $self->first = $self->last = null;
		return $self;

	}
	public function __construct($paginate_by=20,$current=1,$total=0){
		$this->limit($paginate_by);
		$this->total($total);
		$this->current($current);

	}
	/**
	 * 
	 * 配列をvarsにセットする
	 * @param string[] $array
	 * @return self $this
	 */
	public function cp(array $array){
		foreach($array as $name => $value){
			if(ctype_alpha($name[0])) $this->vars[$name] = (string)$value;
		}
		return $this;
	}
	/**
	 * 次のページ番号
	 * @return integer
	 */
	public function next(){
		if($this->dynamic) return $this->tmp[0];
		return $this->current + 1;

	}
	/**
	 * 前のページ番号
	 * @return integer
	 */
	public function prev(){
		if($this->dynamic){
			if(!isset($this->tmp[1]) && sizeof($this->tmp[2]) > 0) $this->tmp[1] = array_shift($this->tmp[2]);
			return $this->tmp[1];
		}
		return $this->current - 1;

	}
	/**
	 * 次のページがあるか
	 * @return boolean
	 */
	public function is_next(){
		if($this->dynamic) return isset($this->tmp[0]);
		return ($this->last > $this->current);

	}
	/**
	 * 前のページがあるか
	 * @return boolean
	 */
	public function is_prev(){
		if($this->dynamic) return ($this->prev() !== null);
		return ($this->current > 1);

	}
	/**
	 * 前のページを表すクエリ
	 * @return string
	 */
	public function query_prev(){
		$prev = $this->prev();
		$vars = array_merge($this->vars,array($this->query_name()=>($this->dynamic && isset($this->tmp[3]) ? (isset($prev[$this->tmp[3]]) ? $prev[$this->tmp[3]] : null) : $prev)));
		if(isset($this->order)) $vars['order'] = $this->order;
		return Query::get($vars);

	}
	/**
	 * 次のページを表すクエリ
	 * @return string
	 */
	public function query_next(){
		$vars = array_merge($this->vars,array($this->query_name()=>(($this->dynamic) ? $this->tmp[0] : $this->next())));
		if(isset($this->order)) $vars['order'] = $this->order;
		return Query::get($vars);

	}
	/**
	 * orderを変更するクエリ
	 * @param string $order
	 * @param string $pre_order
	 * @return string
	 */
	public function query_order($order){
		if(isset($this->vars['order'])){
			$this->order = $this->vars['order'];
			unset($this->vars['order']);
		}
		return Query::get(array_merge(
							$this->vars
							,array('order'=>$order,'porder'=>$this->order())
						));

	}
	/**
	 * 指定のページを表すクエリ
	 * @param integer $current 現在のページ番号
	 * @return string
	 */
	public function query($current){
		$vars = array_merge($this->vars,array($this->query_name()=>$current));
		if(isset($this->order)) $vars['order'] = $this->order;
		return Query::get($vars);

	}

	/**
	 * コンテンツを追加する
	 * @param mixed $mixed
	 * @return boolean
	 */
	public function add($mixed){
		$this->contents($mixed);
		return (sizeof($this->contents) <= $this->limit);
	}
	/**
	 * 現在のページの最初の位置
	 * @return integer
	 */
	public function page_first(){
		if($this->dynamic) return null;
		return $this->offset + 1;
	}
	/**
	 * 現在のページの最後の位置
	 * @return integer
	 */
	public function page_last(){
		if($this->dynamic) return null;
		return (($this->offset + $this->limit) < $this->total) ? ($this->offset + $this->limit) : $this->total;
	}
	/**
	 * ページの最初の位置を返す
	 * @param integer $paginate
	 * @return integer
	 */
	public function which_first($paginate=null){
		if($this->dynamic) return null;
		if($paginate === null) return $this->first;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		$last = ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
		return (($last - $paginate) > 0) ? ($last - $paginate) : $first;
	}
	/**
	 * ページの最後の位置を返す
	 * @param integer $paginate
	 * @return integer
	 */
	public function which_last($paginate=null){
		if($this->dynamic) return null;
		if($paginate === null) return $this->last;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		return ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
	}
	/**
	 * ページとして有効な範囲のページ番号を有する配列を作成する
	 * @param integer $counter ページ数
	 * @return integer[]
	 */
	public function range($counter=10){
		if($this->dynamic) return array();
		if($this->which_last($counter) > 0) return range((int)$this->which_first($counter),(int)$this->which_last($counter));
		return array(1);
	}
	/**
	 * rangeが存在するか
	 * @return boolean
	 */
	public function has_range(){
		return (!$this->dynamic && $this->last > 1);

	}
}
/**
 * query文字列を作成する
 * @author tokushima
 */
class Query{
	/**
	 * query文字列に変換する
	 * @param mixed $var query文字列化する変数
	 * @param string $name ベースとなる名前
	 * @param boolean $null nullの値を表現するか
	 * @param boolean $array 配列を表現するか
	 * @return string
	 */
	static public function get($var,$name=null,$null=true,$array=true){

		$result = "";
		foreach(self::expand_vars($vars,$var,$name,$array) as $v){
			if(($null || ($v[1] !== null && $v[1] !== '')) && is_string($v[1])) $result .= $v[0].'='.urlencode($v[1]).'&';
		}
		return (empty($result)) ? $result : substr($result,0,-1);
	}
	/**
	 * 
	 * @param mixed{} $vars マージ元の値
	 * @param mixed $value 展開する値
	 * @param string $name ベースとなる名前
	 * @param boolean $array 配列を表現するか
	 */
	static public function expand_vars(&$vars,$value,$name=null,$array=true){
		if(!is_array($vars)) $vars = array();
		if($value instanceof File){
			$vars[] = array($name,$value);
		}else{
			$ar = array();
			if(is_object($value)){
				if($value instanceof \Traversable){
					foreach($value as $k => $v) $ar[$k] = $v;
				}else{
					foreach(get_object_vars($value) as $k => $v) $ar[$k] = $v;
				}
				$value = $ar;
			}
			if(is_array($value)){
				foreach($value as $k => $v){
					self::expand_vars($vars,$v,(isset($name) ? $name.(($array) ? '['.$k.']' : '') : $k),$array);
				}
			}else if(!is_numeric($name)){
				if(is_bool($value)) $value = ($value) ? 'true' : 'false';
				$vars[] = array($name,(string)$value);
			}
		}
		return $vars;

	}
}
/**
 * リクエストを処理する
 * @author tokushima
 */
class Request implements \IteratorAggregate{
	private $vars = array();
	private $files = array();
	private $args;

	public function __construct(){
		if('' != ($pathinfo = (array_key_exists('PATH_INFO',$_SERVER)) ? $_SERVER['PATH_INFO'] : null)){
			if($pathinfo[0] != '/') $pathinfo = '/'.$pathinfo;
			$this->args = preg_replace("/(.*?)\?.*/","\\1",$pathinfo);
		}
		if(isset($_SERVER['REQUEST_METHOD'])){
			$args = func_get_args();
			if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST'){
				if(isset($_POST) && is_array($_POST)){
					foreach($_POST as $k => $v) $this->vars[$k] = (get_magic_quotes_gpc() && is_string($v)) ? stripslashes($v) : $v;
				}
				if(isset($_FILES) && is_array($_FILES)){
					foreach($_FILES as $k => $v) $this->files[$k] = $v;
				}
			}else if(isset($_GET) && is_array($_GET)){
				foreach($_GET as $k => $v) $this->vars[$k] = (get_magic_quotes_gpc() && is_string($v)) ? stripslashes($v) : $v;
			}
			if(isset($_COOKIE) && is_array($_COOKIE)){
				foreach($_COOKIE as $k => $v){
					if($k[0] != '_' && $k != session_name()) $this->vars[$k] = $v;
				}
			}
		}else if(isset($_SERVER['argv'])){
			$argv = $_SERVER['argv'];
			array_shift($argv);
			if(isset($argv[0]) && $argv[0][0] != '-'){
				$this->args = implode(' ',$argv);
			}else{
				$size = sizeof($argv);
				for($i=0;$i<$size;$i++){
					if($argv[$i][0] == '-'){
						if(isset($argv[$i+1]) && $argv[$i+1][0] != '-'){
							$this->vars[substr($argv[$i],1)] = $argv[$i+1];
							$i++;
						}else{
							$this->vars[substr($argv[$i],1)] = '';
						}
					}
				}
			}
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator(){
		return new \ArrayIterator($this->vars);

	}
	/**
	 * 現在のURLを返す
	 * @return string
	 */
	static public function current_url($port_https=443,$port_http=80){
		$port = isset($_SERVER['HTTPS']) ? (($_SERVER['HTTPS'] === 'on') ? $port_https : $port_http) : null;
		if(!isset($port)){
			if(isset($_SERVER['HTTP_X_FORWARDED_PORT'])){
				$port = $_SERVER['HTTP_X_FORWARDED_PORT'];
			}else if(isset($_SERVER['HTTP_X_FORWARDED_PROTO'])){
				$port = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? $port_https : $port_http;
			}else if(isset($_SERVER['SERVER_PORT']) && !isset($_SERVER['HTTP_X_FORWARDED_HOST'])){
				$port = $_SERVER['SERVER_PORT'];
			}else{
				$port = $port_http;
			}
		}
		$server = preg_replace("/^(.+):\d+$/","\\1",isset($_SERVER['HTTP_X_FORWARDED_HOST']) ?
					$_SERVER['HTTP_X_FORWARDED_HOST'] :
					(
						isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] :
						(isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')
					));
		$path = isset($_SERVER['REQUEST_URI']) ?
					preg_replace("/^(.+)\?.*$/","\\1",$_SERVER['REQUEST_URI']) :
					(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'].(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '') : '');
		if($port != $port_http && $port != $port_https) $server = $server.':'.$port;
		if(empty($server)) return null;
		return (($port == $port_https) ? 'https' : 'http').'://'.preg_replace("/^(.+?)\?.*/","\\1",$server).$path;
	}
	/**
	 * 現在のリクエストクエリを返す
	 * @param boolean $sep 先頭に?をつけるか
	 * @return string
	 */
	static public function request_string($sep=false){
		$query = ((isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'].'&' : '').file_get_contents('php://input');
		return (($sep && !empty($query)) ? '?' : '').$query;
	}
	/**
	 * POSTされたか
	 * @return boolean
	 */
	public function is_post(){
		return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST');
	}
	/**
	 * CLIで実行されたか
	 * @return boolean
	 */
	public function is_cli(){
		return (php_sapi_name() == 'cli' || !isset($_SERVER['REQUEST_METHOD']));
	}
	/**
	 * ユーザエージェント
	 * @return string
	 */
	static public function user_agent(){
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}
	/**
	 * クッキーへの書き出し
	 * @param string $name 書き込む変数名
	 * @param int $expire 有効期限(秒) (+ time)
	 * @param string $path パスの有効範囲
	 * @param boolean $subdomain サブドメインでも有効とするか
	 * @param boolean $secure httpsの場合のみ書き出しを行うか
	 */
	public function write_cookie($name,$expire=null,$path=null,$subdomain=false,$secure=false){
		if($expire === null) $expire = 1209600;
		$domain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
		if($subdomain && substr_count($domain,'.') >= 2) $domain = preg_replace("/.+(\.[^\.]+\.[^\.]+)$/","\\1",$domain);
		if(empty($path)) $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		setcookie($name,$this->in_vars($name),time() + $expire,$path,$domain,$secure);
	}
	/**
	 * クッキーから削除
	 * 登録時と同条件のものが削除される
	 * @param string $name クッキー名
	 */
	public function delete_cookie($name,$path=null,$subdomain=false,$secure=false){
		$domain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
		if($subdomain && substr_count($domain,'.') >= 2) $domain = preg_replace("/.+(\.[^\.]+\.[^\.]+)$/","\\1",$domain);
		if(empty($path)) $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		setcookie($name,null,time() - 1209600,$path,$domain,$secure);
		$this->rm_vars($name);
	}
	/**
	 * クッキーから呼び出された値か
	 * @param string $name
	 * @return boolean
	 */
	public function is_cookie($name){
		return (isset($_COOKIE[$name]));
	}
	/**
	 * pathinfo または argv
	 * @return string
	 */
	public function args(){
		return $this->args;
	}
	/**
	 * 変数の設定
	 * @param string $key
	 * @param mixed $value
	 */
	public function vars($key,$value){
		$this->vars[$key] = $value;
	}
	/**
	 * 変数の取得
	 * @param string $n
	 * @param mixed $d 未定義の場合の値
	 * @return mixed
	 */
	public function in_vars($n,$d=null){
		return array_key_exists($n,$this->vars) ? $this->vars[$n] : $d;
	}
	/**
	 * キーが存在するか
	 * @param string $n
	 * @return boolean
	 */
	public function is_vars($n){
		return array_key_exists($n,$this->vars);
	}
	/**
	 * 変数の削除
	 */
	public function rm_vars(){
		if(func_num_args() === 0){
			$this->vars = array();
		}else{
			foreach(func_get_args() as $n) unset($this->vars[$n]);
		}
	}
	/**
	 * 変数の一覧を返す
	 * @return array
	 */
	public function ar_vars(){
		return $this->vars;
	}
	/**
	 * 添付ファイル情報の取得
	 * @param string $n
	 * @return array
	 */
	public function in_files($n){
		return array_key_exists($n,$this->files) ? $this->files[$n] :  null;
	}
	/**
	 * 添付されたファイルがあるか
	 * @param array $file_info
	 * @return boolean
	 */
	public function has_file($file_info){
		return isset($file_info['tmp_name']) && is_file($file_info['tmp_name']);
	}
	/**
	 * 添付ファイルのオリジナルファイル名の取得
	 * @param array $file_info
	 * @return string
	 */
	public function file_original_name($file_info){
		return isset($file_info['name']) ? $file_info['name'] : null;
	}
	/**
	 * 添付ファイルのファイルパスの取得
	 * @param array $file_info
	 * @return string
	 */
	public function file_path($file_info){
		return isset($file_info['tmp_name']) ? $file_info['tmp_name'] : null;
	}
	/**
	 * 添付ファイルを移動します
	 * @param array $file_info
	 * @param string $newname
	 */
	public function move_file($file_info,$newname){
		if(!$this->has_file($file_info)) throw new \LogicException('file not found ');
		if(!is_dir(dirname($newname))) Util::mkdir(dirname($newname));
		copy($file_info['tmp_name'],$newname);
		chmod($newname,0777);
		unlink($file_info['tmp_name']);
	}
}
/**
 * 文字列を表します
 * @author tokushima
 *
 */
class String{
	private $value;

	public function __construct($v){
		$this->value = $v;
	}
	/**
	 * 値を取得
	 * @return string
	 */
	public function get(){
		return $this->value;
	}
	/**
	 * 値をセットする
	 * @param string $v
	 */
	public function set($v){
		$this->value = $v;
	}
	public function __toString(){
		return $this->value;
	}
	/**
	 * オブジェクトに値をセットして返す
	 * @param mixed $obj
	 * @param string $src
	 * @return self
	 */
	static public function ref(&$obj,$src){
		$obj = new self($src);
		return $obj;
	}
}
class TemplateHelper{
	final public function htmlencode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,'UTF-8',mb_detect_encoding($value));
			return htmlentities($value,ENT_QUOTES,'UTF-8');
		}
		return $value;
	}
}
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
		$a = str_replace("\\",'/',$a);
		if($b === '' || $b === null) return $a;
		$b = str_replace("\\",'/',$b);
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

	}
}
class Validation{
	/**
	 * 
	 * @param string $t
	 * @param mixed $v
	 * @throws \InvalidArgumentException
	 */
	static public function set($t,$v,$p=array()){
		if($v === null) return null;
		switch($t){
			case null: return $v;
			case 'string':
			case 'text':
				if(is_array($v)) throw new \InvalidArgumentException();
				$v =is_bool($v) ? (($v) ? 'true' : 'false') : ((string)$v);
				return ($t == 'text') ? $v : str_replace(array("\r\n","\r","\n"),'',$v);
			default:
				if($v === '') return null;
				switch($t){
					case 'number':
						if(!is_numeric($v)) throw new \InvalidArgumentException();
						$dp = isset($p['decimal_places']) ? $p['decimal_places'] : null;
						return (float)(isset($dp) ? (floor($v * pow(10,$dp)) / pow(10,$dp)) : $v);
					case 'serial':
					case 'integer':
						if(!is_numeric($v) || (int)$v != $v) throw new \InvalidArgumentException();
						return (int)$v;
					case 'boolean':
						if(is_string($v)){
							$v = ($v === 'true' || $v === '1') ? true : (($v === 'false' || $v === '0') ? false : $v);
						}else if(is_int($v)){
							$v = ($v === 1) ? true : (($v === 0) ? false : $v);
						}
						if(!is_bool($v)) throw new \InvalidArgumentException();
						return (boolean)$v;
					case 'timestamp':
					case 'date':
						if(ctype_digit((string)$v) || (substr($v,0,1) == '-' && ctype_digit(substr($v,1)))) return (int)$v;
						if(preg_match('/^0+$/',preg_replace('/[^\d]/','',$v))) return null;
						$time = strtotime($v);
						if($time === false) throw new \InvalidArgumentException();
						return $time;
					case 'time':
						if(is_numeric($v)) return $v;
						$d = array_reverse(preg_split("/[^\d\.]+/",$v));
						if($d[0] === '') array_shift($d);
						list($s,$m,$h) = array((isset($d[0]) ? (float)$d[0] : 0),(isset($d[1]) ? (float)$d[1] : 0),(isset($d[2]) ? (float)$d[2] : 0));
						if(sizeof($d) > 3 || $m > 59 || $s > 59 || strpos($h,'.') !== false || strpos($m,'.') !== false) throw new \InvalidArgumentException();
						return ($h * 3600) + ($m*60) + ((int)$s) + ($s-((int)$s));
					case 'intdate':
						if(preg_match("/^\d\d\d\d\d+$/",$v)){
							$v = sprintf('%08d',$v);
							list($y,$m,$d) = array((int)substr($v,0,-4),(int)substr($v,-4,2),(int)substr($v,-2,2));
						}else{
							$x = preg_split("/[^\d]+/",$v);
							if(sizeof($x) < 3) throw new \InvalidArgumentException();
							list($y,$m,$d) = array((int)$x[0],(int)$x[1],(int)$x[2]);
						}
						if($m < 1 || $m > 12 || $d < 1 || $d > 31 || (in_array($m,array(4,6,9,11)) && $d > 30) || (in_array($m,array(1,3,5,7,8,10,12)) && $d > 31)
								|| ($m == 2 && ($d > 29 || (!(($y % 4 == 0) && (($y % 100 != 0) || ($y % 400 == 0)) ) && $d > 28)))
						) throw new \InvalidArgumentException();
						return (int)sprintf('%d%02d%02d',$y,$m,$d);
					case 'email':
						$v = trim($v);
						if(!preg_match('/^[\w\''.preg_quote('./!#$%&*+-=?^_`{|}~','/').']+@(?:[A-Z0-9-]+\.)+[A-Z]{2,6}$/i',$v)
								|| strlen($v) > 255 || strpos($v,'..') !== false || strpos($v,'.@') !== false || $v[0] === '.') throw new \InvalidArgumentException();
						return $v;
					case 'alnum':
						if(!ctype_alnum(str_replace('_','',$v))) throw new \InvalidArgumentException();
						return $v;
					case 'choice':
						$v = is_bool($v) ? (($v) ? 'true' : 'false') : $v;
						$ch = isset($p['choices']) ? $p['choices'] : null;
						if(!isset($ch) || !in_array($v,$ch,true)) throw new \InvalidArgumentException();
						return $v;
					case 'mixed': return $v;
					default:
						if(!($v instanceof $t)) throw new \InvalidArgumentException();
						return $v;
				}
		}
	}
}
/**
 * XMLを処理する
 * @author tokushima
 */
class Xml implements \IteratorAggregate{
	private $attr = array();
	private $plain_attr = array();
	private $name;
	private $value;
	private $close_empty = true;

	private $plain;
	private $pos;
	private $esc = true;

	public function __construct($name=null,$value=null){
		if($value === null && is_object($name)){
			$n = explode('\\',get_class($name));
			$this->name = array_pop($n);
			$this->value($name);
		}else{
			$this->name = trim($name);
			$this->value($value);
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator(){
		return new \ArrayIterator($this->attr);
	}
	/**
	 * 値が無い場合は閉じを省略する
	 * @param boolean
	 * @return boolean
	 */
	final public function close_empty(){
		if(func_num_args() > 0) $this->close_empty = (boolean)func_get_arg(0);
		return $this->close_empty;
	}
	/**
	 * エスケープするか
	 * @param boolean $bool
	 */
	final public function escape($bool){
		$this->esc = (boolean)$bool;
		return $this;
	}
	/**
	 * setできた文字列
	 * @return string
	 */
	final public function plain(){
		return $this->plain;
	}
	/**
	 * 子要素検索時のカーソル
	 * @return integer
	 */
	final public function cur(){
		return $this->pos;
	}
	/**
	 * 要素名
	 * @return string
	 */
	final public function name($name=null){
		if(isset($name)) $this->name = $name;
		return $this->name;
	}
	private function get_value($v){
		if($v instanceof self){
			$v = $v->get();
		}else if(is_bool($v)){
			$v = ($v) ? 'true' : 'false';
		}else if($v === ''){
			$v = null;
		}else if(is_array($v) || is_object($v)){
			if(!($v instanceof \Traversable) && ($v instanceof Object)) $v = $v->hash();
			$r = '';
			foreach($v as $k => $c){
				if(is_numeric($k) && is_object($c)){
					$e = explode('\\',get_class($c));
					$k = array_pop($e);
				}
				if(is_numeric($k)) $k = 'data';
				$x = new self($k,$c);
				$x->escape($this->esc);
				$r .= $x->get();
			}
			$v = $r;
		}else if($this->esc && strpos($v,'<![CDATA[') === false && preg_match("/&|<|>|\&[^#\da-zA-Z]/",$v)){
			$v = '<![CDATA['.$v.']]>';
		}
		return $v;
	}
	/**
	 * 値を設定、取得する
	 * @param mixed
	 * @param boolean
	 * @return string
	 */
	final public function value(){
		if(func_num_args() > 0) $this->value = $this->get_value(func_get_arg(0));
		if(strpos($this->value,'<![CDATA[') === 0) return substr($this->value,9,-3);
		return $this->value;

	}
	/**
	 * 値を追加する
	 * ２つ目のパラメータがあるとアトリビュートの追加となる
	 * @param mixed $arg
	 */
	final public function add($arg){
		if(func_num_args() == 2){
			$this->attr(func_get_arg(0),func_get_arg(1));
		}else{
			$this->value .= $this->get_value(func_get_arg(0));
		}
		return $this;

	}
	/**
	 * アトリビュートを取得する
	 * @param string $n 取得するアトリビュート名
	 * @param string $d アトリビュートが存在しない場合の代替値
	 * @return string
	 */
	final public function in_attr($n,$d=null){
		return isset($this->attr[strtolower($n)]) ? ($this->esc ? htmlentities($this->attr[strtolower($n)],ENT_QUOTES,'UTF-8') : $this->attr[strtolower($n)]) : (isset($d) ? (string)$d : null);

	}
	/**
	 * アトリビュートから削除する
	 * パラメータが一つも無ければ全件削除
	 */
	final public function rm_attr(){
		if(func_num_args() === 0){
			$this->attr = array();
		}else{
			foreach(func_get_args() as $n) unset($this->attr[$n]);
		}

	}
	/**
	 * アトリビュートがあるか
	 * @param string $name
	 * @return boolean
	 */
	final public function is_attr($name){
		return array_key_exists($name,$this->attr);

	}
	/**
	 * アトリビュートを設定
	 * @return self $this
	 */
	final public function attr($key,$value){
		$this->attr[strtolower($key)] = is_bool($value) ? (($value) ? 'true' : 'false') : $value;
		return $this;

	}
	/**
	 * 値の無いアトリビュートを設定
	 * @param string $v
	 */
	final public function plain_attr($v){
		$this->plain_attr[] = $v;
	}
	/**
	 * XML文字列を返す
	 */
	public function get($encoding=null){
		if($this->name === null) throw new \LogicException('undef name');
		$attr = '';
		$value = ($this->value === null || $this->value === '') ? null : (string)$this->value;
		foreach($this->attr as $k => $v) $attr .= ' '.$k.'="'.$this->in_attr($k).'"';
		return ((empty($encoding)) ? '' : '<?xml version="1.0" encoding="'.$encoding.'" ?'.'>'.PHP_EOL)
				.('<'.$this->name.$attr.(implode(' ',$this->plain_attr)).(($this->close_empty && !isset($value)) ? ' /' : '').'>')
				.$this->value
				.((!$this->close_empty || isset($value)) ? sprintf('</%s>',$this->name) : '');

	}
	public function __toString(){
		return $this->get();

	}
	/**
	 * 文字列からXMLを探す
	 * @param mixed $x 見つかった場合にインスタンスがセットされる
	 * @param string $plain 対象の文字列
	 * @param string $name 要素名
	 * @return boolean
	 */
	static public function set(&$x,$plain,$name=null){
		return self::_set($x,$plain,$name);

	}
	static private function _set(&$x,$plain,$name=null,$vtag=null){
		$plain = (string)$plain;
		$name = (string)$name;
		if(empty($name) && preg_match("/<([\w\:\-]+)[\s][^>]*?>|<([\w\:\-]+)>/is",$plain,$m)){
			$name = str_replace(array("\r\n","\r","\n"),'',(empty($m[1]) ? $m[2] : $m[1]));
		}
		$qname = preg_quote($name,'/');
		if(!preg_match("/<(".$qname.")([\s][^>]*?)>|<(".$qname.")>/is",$plain,$parse,PREG_OFFSET_CAPTURE)) return false;
		$x = new self();
		$x->pos = $parse[0][1];
		$balance = 0;
		$attrs = '';

		if(substr($parse[0][0],-2) == '/>'){
			$x->name = $parse[1][0];
			$x->plain = empty($vtag) ? $parse[0][0] : preg_replace('/'.preg_quote(substr($vtag,0,-1).' />','/').'/',$vtag,$parse[0][0],1);
			$attrs = $parse[2][0];
		}else if(preg_match_all("/<[\/]{0,1}".$qname."[\s][^>]*[^\/]>|<[\/]{0,1}".$qname."[\s]*>/is",$plain,$list,PREG_OFFSET_CAPTURE,$x->pos)){
			foreach($list[0] as $arg){
				if(($balance += (($arg[0][1] == '/') ? -1 : 1)) <= 0 &&
						preg_match("/^(<(".$qname.")([\s]*[^>]*)>)(.*)(<\/\\2[\s]*>)$/is",
							substr($plain,$x->pos,($arg[1] + strlen($arg[0]) - $x->pos)),
							$match
						)
				){
					$x->plain = $match[0];
					$x->name = $match[2];
					$x->value = ($match[4] === '' || $match[4] === null) ? null : $match[4];
					$attrs = $match[3];
					break;
				}
			}
			if(!isset($x->plain)){
				return self::_set($x,preg_replace('/'.preg_quote($list[0][0][0],'/').'/',substr($list[0][0][0],0,-1).' />',$plain,1),$name,$list[0][0][0]);
			}
		}
		if(!isset($x->plain)) return false;
		if(!empty($attrs)){
			if(preg_match_all("/[\s]+([\w\-\:]+)[\s]*=[\s]*([\"\'])([^\\2]*?)\\2/ms",$attrs,$attr)){
				foreach($attr[0] as $id => $value){
					$x->attr($attr[1][$id],$attr[3][$id]);
					$attrs = str_replace($value,'',$attrs);
				}
			}
			if(preg_match_all("/([\w\-]+)/",$attrs,$attr)){
				foreach($attr[1] as $v) $x->attr($v,$v);
			}
		}
		return true;
	}
	/**
	 * 指定の要素を検索する
	 * @param string $tag_name 要素名
	 * @param integer $offset 開始位置
	 * @param integer $length 取得する最大数
	 * @return XmlIterator
	 */
	public function in($name,$offset=0,$length=0){
		return new XmlIterator($name,$this->value(),$offset,$length);

	}
	/**
	 * パスで検索する
	 * @param string $path 検索文字列
	 * @return mixed
	 */
	public function f($path){
		$arg = (func_num_args() == 2) ? func_get_arg(1) : null;
		$paths = explode('.',$path);
		$last = (strpos($path,'(') === false) ? null : array_pop($paths);
		$tag = clone($this);
		$route = array();
		if($arg !== null) $arg = (is_bool($arg)) ? (($arg) ? 'true' : 'false') : strval($arg);

		foreach($paths as $p){
			$pos = 0;
			$t = null;
			if(preg_match("/^(.+)\[([\d]+?)\]$/",$p,$matchs)) list($tmp,$p,$pos) = $matchs;
			foreach($tag->in($p,$pos,1) as $t);
			if(!isset($t) || !($t instanceof self)){
				$tag = null;
				break;
			}
			$route[] = $tag = $t;
		}
		if($tag instanceof self){
			if($arg === null){
				switch($last){
					case '': return $tag;
					case 'plain()': return $tag->plain();
					case 'value()': return $tag->value();
					default:
						if(preg_match("/^(attr|in)\((.+?)\)$/",$last,$matchs)){
							list($null,$type,$name) = $matchs;
							if($type == 'in'){
								return $tag->in(trim($name));
							}else if($type == 'attr'){
								return $tag->in_attr($name);
							}
						}
						return null;
				}
			}
			if($arg instanceof self) $arg = $arg->get();
			if(is_bool($arg)) $arg = ($arg) ? 'true' : 'false';
			krsort($route,SORT_NUMERIC);
			$ltag = $rtag = $replace = null;
			$f = true;

			foreach($route as $r){
				$ltag = clone($r);
				if($f){
					switch($last){
						case 'value()':
							$replace = $arg;
							break;
						default:
							if(preg_match("/^(attr)\((.+?)\)$/",$last,$matchs)){
								list($null,$type,$name) = $matchs;
								if($type == 'attr'){
									$r->attr($name,$arg);
									$replace = $r->get();
								}else{
									return null;
								}
							}
					}
					$f = false;
				}
				$r->value(empty($rtag) ? $replace : str_replace($rtag->plain(),$replace,$r->value()));
				$replace = $r->get();
				$rtag = clone($ltag);
			}
			$this->value(str_replace($ltag->plain(),$replace,$this->value()));
			return null;
		}
		return (!empty($last) && substr($last,0,2) == 'in') ? array() : null;

	}
	/**
	 * idで検索する
	 *
	 * @param string $name 指定のID
	 * @return self
	 */
	public function id($name){
		if(preg_match("/<.+[\s]*id[\s]*=[\s]*([\"\'])".preg_quote($name)."\\1/",$this->value(),$match,PREG_OFFSET_CAPTURE)){
			if(self::set($tag,substr($this->value(),$match[0][1]))) return $tag;
		}
		return null;

	}
	/**
	 * xmlとし出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function output($encoding=null,$name=null){
		header(sprintf('Content-Type: application/xml%s',(empty($name) ? '' : sprintf('; name=%s',$name))));
		print($this->get($encoding));
		exit;
	}
	/**
	 * attachmentとして出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function attach($encoding=null,$name=null){
		header(sprintf('Content-Disposition: attachment%s',(empty($name) ? '' : sprintf('; filename=%s',$name))));
		$this->output($encoding,$name);
	}
}
class XmlIterator implements \Iterator{
	private $name = null;
	private $plain = null;
	private $tag = null;
	private $offset = 0;
	private $length = 0;
	private $count = 0;

	public function __construct($tag_name,$value,$offset,$length){
		$this->name = $tag_name;
		$this->plain = $value;
		$this->offset = $offset;
		$this->length = $length;
		$this->count = 0;
	}
	public function key(){
		$this->tag->name();
	}
	public function current(){
		$this->plain = substr($this->plain,0,$this->tag->cur()).substr($this->plain,$this->tag->cur() + strlen($this->tag->plain()));
		$this->count++;
		return $this->tag;
	}
	public function valid(){
		if($this->length > 0 && ($this->offset + $this->length) <= $this->count) return false;
		if(is_array($this->name)){
			$tags = array();
			foreach($this->name as $name){
				if(Xml::set($get_tag,$this->plain,$name)) $tags[$get_tag->cur()] = $get_tag;
			}
			if(empty($tags)) return false;
			ksort($tags,SORT_NUMERIC);
			foreach($tags as $this->tag) return true;
		}
		return Xml::set($this->tag,$this->plain,$this->name);
	}
	public function next(){}
	public function rewind(){
		for($i=0;$i<$this->offset;$i++){
			$this->valid();
			$this->current();
		}
	}
}
/**
 * ログ処理
 *
 * @author tokushima
 * @var string $level ログのレベル
 * @var timestamp $time 発生時間
 * @var string $file 発生したファイル名
 * @var integer $line 発生した行
 * @var mixed $value 内容
 * @conf string $level ログレベル (none,error,warn,info,debug)
 * @conf boolean $disp 標準出力に出すか
 */
class Log extends Object{
	static private $stdout = true;
	static private $level_strs = array('none','error','warn','info','debug');
	static private $logs = array();
	static private $id;
	static private $current_level;
	static private $disp;

	protected $level;
	protected $time;
	protected $file;
	protected $line;
	protected $value;

	static public function __import__(){
		self::$id = base_convert(date('md'),10,36).base_convert(date('G'),10,36).base_convert(mt_rand(1296,46655),10,36);
		self::$logs[] = new self(4,'--- logging start '
									.date('Y-m-d H:i:s')
									.' ( '.(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : null)).' )'
									.' { '.(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null).' }'
								.' --- ');
	}
	static public function __shutdown__(){
		if(self::cur_level() >= 4){
			if(function_exists('memory_get_usage')){
				self::$logs[] = new self(4,sprintf('--- end logger ( %s MByte) --- ',round(number_format((memory_get_usage() / 1024 / 1024),3),2)));
			}
		}
		self::flush();
	}
	static private function cur_level(){
		if(self::$current_level === null) self::$current_level = array_search(Conf::get('level','none'),self::$level_strs);
		return self::$current_level;
	}
	static private function disp(){
		if(self::$disp === null) self::$disp = (boolean)Conf::get('disp',false);
		return self::$disp;
	}
	final protected function __new__($level,$value,$file=null,$line=null,$time=null){
		$class = null;
		if($file === null){
			$debugs = debug_backtrace(false);
			if(sizeof($debugs) > 4){
				list($dumy,$dumy,$dumy,$debug,$op) = $debugs;
			}else{
				list($dumy,$debug) = $debugs;
			}
			$file = (isset($debug['file']) ? $debug['file'] : $dumy['file']);
			$line = (isset($debug['line']) ? $debug['line'] : $dumy['line']);
			$class = (isset($op['class']) ? $op['class'] : $dumy['class']);
		}
		$this->level = $level;
		$this->file = $file;
		$this->line = intval($line);
		$this->time = ($time === null) ? time() : $time;
		$this->class = $class;
		$this->value = (is_object($value)) ? 
							(($value instanceof \Exception) ? 
								(string)$value
								: clone($value)
							)
							: $value;
	}
	protected function __fm_value__(){
		if(!is_string($this->value)){
			ob_start();
				var_dump($this->value);
			return ob_get_clean();
		}
		return $this->value;
	}
	protected function __fm_level__(){
		return ($this->level() >= 0) ? self::$level_strs[$this->level()] : 'trace';
	}
	protected function __get_time__($format='Y/m/d H:i:s'){
		return (empty($format)) ? $this->time : date($format,$this->time);
	}
	protected function __str__(){
		return '['.$this->time().']'.'['.self::$id.']'.'['.$this->fm_level().']'.':['.$this->file().':'.$this->line().']'.' '.$this->fm_value();
	}
	/**
	 * 格納されたログを出力する
	 */
	final static public function flush(){
		if(!empty(self::$logs)){
			foreach(self::$logs as $log){
				if(self::cur_level() >= $log->level()){
					if(self::disp() && self::$stdout) print(((string)$log).PHP_EOL);
					switch($log->fm_level()){
						/**
						 * debugログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'debug': self::module('debug',$log,self::$id); break;
						/**
						 * infoログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'info': self::module('info',$log,self::$id); break;
						/**
						 * warnログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'warn': self::module('warn',$log,self::$id); break;
						/**
						 * errorログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'error': self::module('error',$log,self::$id); break;
						default:
						/**
						 * traceログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						self::module('trace',$log,self::$id);
					}
				}
			}
		}
		/**
		 * フラッシュ時の処理
		 * @param self[] $logs
		 * @param string $id
		 * @param boolean $stdout 標準出力に出力するか
		 */
		self::module('flush',self::$logs,self::$id,self::$stdout);
		/**
		 * フラッシュの後処理
		 * @param string $id
		 */
		self::module('after_flush',self::$id);
		self::$logs = array();
	}
	/**
	 * 一時的に無効にされた標準出力へのログ出力を有効にする
	 * ログのモードに依存する
	 */
	static public function enable_display(){
		self::debug('log stdout on');
		self::$stdout = true;
	}

	/**
	 * 標準出力へのログ出力を一時的に無効にする
	 */
	static public function disable_display(){
		self::debug('log stdout off');
		self::$stdout = false;
	}
	/**
	 * 標準出力へのログ可不可
	 * @return boolean
	 */
	static public function is_display(){
		return self::$stdout;
	}
	/**
	 * errorを生成
	 * @param mixed $value 内容
	 */
	static public function error(){
		if(self::cur_level() >= 1){
			foreach(func_get_args() as $value) self::$logs[] = new self(1,$value);
		}
	}
	/**
	 * warnを生成
	 * @param mixed $value 内容
	 */
	static public function warn($value){
		if(self::cur_level() >= 2){
			foreach(func_get_args() as $value) self::$logs[] = new self(2,$value);
		}
	}
	/**
	 * infoを生成
	 * @param mixed $value 内容
	 */
	static public function info($value){
		if(self::cur_level() >= 3){
			foreach(func_get_args() as $value) self::$logs[] = new self(3,$value);
		}
	}
	/**
	 * debugを生成
	 * @param mixed $value 内容
	 */
	static public function debug($value){
		if(self::cur_level() >= 4){
			foreach(func_get_args() as $value) self::$logs[] = new self(4,$value);
		}
	}
	/**
	 * traceを生成
	 * @param mixed $value 内容
	 */
	static public function trace($value){
		foreach(func_get_args() as $value) self::$logs[] = new self(-1,$value);
	}
	/**
	 * var_dumpで出力する
	 * @param mixed $value 内容
	 */
	static public function d($value){
		list($debug_backtrace) = debug_backtrace(false);
		$args = func_get_args();
		var_dump(array_merge(array($debug_backtrace['file'].':'.$debug_backtrace['line']),$args));
	}
}
/**
 * テンプレートを処理する
 * @author tokushima
 * @var mixed{} $vars バインドされる変数
 * @var boolean $secure https://をhttp://に置換するか
 * @var string $put_block ブロックファイル
 * @var string $template_super 継承元テンプレート
 * @var string $media_url メディアファイルへのURLの基点
 * @conf boolean $display_exception 例外が発生した場合にメッセージを表示するか
 */
class Template extends Object{
	private $file;
	private $selected_template;
	private $selected_src;

	protected $secure = false;
	protected $vars = array();
	protected $put_block;
	protected $template_super;
	protected $media_url;

	protected function __new__($media_url=null){
		if($media_url !== null) $this->media_url($media_url);
	}
	/**
	 * 配列からテンプレート変数に値をセットする
	 * @param array $array
	 */
	final public function cp($array){
		if(is_array($array) || is_object($array)){
			foreach($array as $k => $v) $this->vars[$k] = $v;
		}else{
			throw new \InvalidArgumentException('must be an of array');
		}
		return $this;
	}
	/**
	 * メディアファイルへのURLの基点を設定
	 * @param string $url
	 * @return $this
	 */
	protected function __set_media_url__($url){
		$this->media_url = str_replace("\\",'/',$url);
		if(!empty($this->media_url) && substr($this->media_url,-1) !== '/') $this->media_url = $this->media_url.'/';
	}
	/**
	 * 出力する
	 * @param string $file
	 * @param string $template_name
	 */
	final public function output($file,$template_name=null){
		print($this->read($file,$template_name));
		exit;
	}
	/**
	 * ファイルを読み込んで結果を返す
	 * @param string $file
	 * @param string $template_name
	 * @return string
	 */
	final public function read($file,$template_name=null){
		if(!is_file($file) && strpos($file,'://') === false) throw new \InvalidArgumentException($file.' not found');
		$this->file = $file;
		$cname = md5($this->template_super.$this->put_block.$this->file.$this->selected_template);
		/**
		 * キャッシュのチェック
		 * @param string $cname キャッシュ名
		 * @return boolean
		 */
		if(!static::has_module('has_template_cache') || static::module('has_template_cache',$cname) !== true){
			if(!empty($this->put_block)){
				$src = $this->read_src($this->put_block);
				if(strpos($src,'rt:extends') !== false){
					Xml::set($x,'<:>'.$src.'</:>');
					foreach($x->in('rt:extends') as $ext) $src = str_replace($ext->plain(),'',$src);
				}
				$src = sprintf('<rt:extends href="%s" />\n',$file).$src;
				$this->file = $this->put_block;
			}else{
				$src = $this->read_src($this->file);
			}
			$src = $this->replace($src,$template_name);
			/**
			 * キャッシュにセットする
			 * @param string $cname キャッシュ名
			 * @param string $src 作成されたテンプレート
			 */
			static::module('set_template_cache',$cname,$src);
		}else{
			/**
			 * キャッシュから取得する
			 * @param string $cname キャッシュ名
			 * @return string
			 */
			$src = static::module('get_template_cache',$cname);
		}
		return $this->execute($src);

	}
	private function cname(){
		return md5($this->put_block.$this->file.$this->selected_template);
	}
	/**
	 * 文字列から結果を返す
	 * @param string $src
	 * @param string $template_name
	 * @return string
	 */
	final public function get($src,$template_name=null){
		return $this->execute($this->replace($src,$template_name));
	}
	private function execute($src){
		$src = $this->exec($src);
		$src = str_replace(array('#PS#','#PE#'),array('<?','?>'),$this->html_reform($src));
		return $src;
	}
	private function replace($src,$template_name){
		$this->selected_template = $template_name;
		$src = preg_replace("/([\w])\->/","\\1__PHP_ARROW__",$src);
		$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),$src);
		$src = $this->replace_xtag($src);
		/**
		 * テンプレート作成の初期化
		 * @param phpman.String $obj
		 */
		$this->object_module('init_template',String::ref($obj,$src));
		$src = $this->rtcomment($this->rtblock($this->rttemplate((string)$obj),$this->file));
		$this->selected_src = $src;
		/**
		 * テンプレート作成の前処理
		 * @param phpman.String $obj
		 */
		$this->object_module('before_template',String::ref($obj,$src));
		$src = $this->rtif($this->rtloop($this->rtunit($this->html_form($this->html_list((string)$obj)))));
		/**
		 * テンプレート作成の後処理
		 * @param phpman.String $obj
		 */
		$this->object_module('after_template',String::ref($obj,$src));
		$src = str_replace('__PHP_ARROW__','->',(string)$obj);
		$src = $this->parse_print_variable($src);
		$php = array(' ?>',' ','->');
		$str = array('__PHP_TAG_END__','__PHP_TAG_START__','__PHP_ARROW__');
		$src = str_replace($php,$str,$src);
		$src = $this->parse_url($src,$this->media_url);
		$src = str_replace($str,$php,$src);
		$src = str_replace(array('__ESC_DQ__','__ESC_SQ__','__ESC_DESC__'),array("\\\"","\\'","\\\\"),$src);
		return $src;
	}
	private function exec($_src_){
		/**
		 * 実行前処理
		 * @param phpman.String $obj
		 */
		$this->object_module('before_exec_template',String::ref($_obj_,$_src_));
		$this->vars('_t_',new TemplateHelper());
		ob_start();
			if(is_array($this->vars) && !empty($this->vars)) extract($this->vars);
			eval('?> $_display_exception_='.((Conf::get('display_exception') === true) ? 'true' : 'false').'; ?>'.((string)$_obj_));
		$_eval_src_ = ob_get_clean();

		if(strpos($_eval_src_,'Parse error: ') !== false){
			if(preg_match("/Parse error\:(.+?) in .+eval\(\)\'d code on line (\d+)/",$_eval_src_,$match)){
				list($msg,$line) = array(trim($match[1]),((int)$match[2]));
				$lines = explode("\n",$_src_);
				$plrp = substr_count(implode("\n",array_slice($lines,0,$line))," 'PLRP'; ?>\n");
				Log::error($msg.' on line '.($line-$plrp).' [compile]: '.trim($lines[$line-1]));

				$lines = explode("\n",$this->selected_src);
				Log::error($msg.' on line '.($line-$plrp).' [plain]: '.trim($lines[$line-1-$plrp]));
				if(Conf::get('display_exception') === true) $_eval_src_ = $msg.' on line '.($line-$plrp).': '.trim($lines[$line-1-$plrp]);
			}
		}
		$_src_ = $this->selected_src = null;
		/**
		 * 実行後処理
		 * @param phpman.String $obj
		 */
		$this->object_module('after_exec_template',String::ref($_obj_,$_eval_src_));
		return (string)$_obj_;
	}
	private function error_handler($errno,$errstr,$errfile,$errline){
		throw new \ErrorException($errstr,0,$errno,$errfile,$errline);
	}
	private function replace_xtag($src){
		if(preg_match_all("/<\?(?!php[\s\n])[\w]+ .*?\?>/s",$src,$null)){
			foreach($null[0] as $value) $src = str_replace($value,'#PS#'.substr($value,2,-2).'#PE#',$src);
		}
		return $src;
	}
	private function parse_url($src,$media){
		if(!empty($media) && substr($media,-1) !== '/') $media = $media.'/';
		$secure_base = ($this->secure) ? str_replace('http://','https://',$media) : null;
		if(preg_match_all("/<([^<\n]+?[\s])(src|href|background)[\s]*=[\s]*([\"\'])([^\\3\n]+?)\\3[^>]*?>/i",$src,$match)){
			foreach($match[2] as $k => $p){
				$t = null;
				if(strtolower($p) === 'href') list($t) = (preg_split("/[\s]/",strtolower($match[1][$k])));
				$src = $this->replace_parse_url($src,(($this->secure && $t !== 'a') ? $secure_base : $media),$match[0][$k],$match[4][$k]);
			}
		}
		if(preg_match_all("/[^:]:[\040]*url\(([^\n]+?)\)/",$src,$match)){
			if($this->secure) $media = $secure_base;
			foreach($match[1] as $key => $param) $src = $this->replace_parse_url($src,$media,$match[0][$key],$match[1][$key]);
		}
		return $src;
	}
	private function replace_parse_url($src,$base,$dep,$rep){
		if(!preg_match("/(^[\w]+:\/\/)|(^__PHP_TAG_START)|(^\{\\$)|(^\w+:)|(^[#\?])/",$rep)){
			$src = str_replace($dep,str_replace($rep,$this->ab_path($base,$rep),$dep),$src);
		}
		return $src;
	}
	private function ab_path($a,$b){
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
	}
	private function read_src($filename){
		$src = file_get_contents($filename);
		return (preg_match('/^http[s]*\:\/\//',$filename)) ? $this->parse_url($src,dirname($filename)) : $src;
	}
	private function rttemplate($src){
		$values = array();
		$bool = false;
		while(Xml::set($tag,$src,'rt:template')){
			$src = str_replace($tag->plain(),'',$src);
			$values[$tag->in_attr('name')] = $tag->value();
			$src = str_replace($tag->plain(),'',$src);
			$bool = true;
		}
		if(!empty($this->selected_template)){
			if(!array_key_exists($this->selected_template,$values)) throw new \LogicException('undef rt:template '.$this->selected_template);
			return $values[$this->selected_template];
		}
		return ($bool) ? implode($values) : $src;
	}
	private function rtblock($src,$filename){
		if(strpos($src,'rt:block') !== false || strpos($src,'rt:extends') !== false){
			$base_filename = $filename;
			$blocks = $paths = array();
			while(Xml::set($e,'<:>'.$this->rtcomment($src).'</:>','rt:extends') !== false){
				$href = $this->ab_path(str_replace("\\",'/',dirname($filename)),$e->in_attr('href'));
				if(!$e->is_attr('href') || !is_file($href)) throw new \LogicException('href not found '.$filename);
				if($filename === $href) throw new \LogicException('Infinite Recursion Error'.$filename);
				Xml::set($bx,'<:>'.$this->rtcomment($src).'</:>',':');
				foreach($bx->in('rt:block') as $b){
					$n = $b->in_attr('name');
					if(!empty($n) && !array_key_exists($n,$blocks)){
						$blocks[$n] = $b->value();
						$paths[$n] = $filename;
					}
				}
				$src = $this->rttemplate($this->replace_xtag($this->read_src($filename = $href)));
				$this->selected_template = $e->in_attr('name');
			}
			/**
			 * ブロック展開の前処理
			 * @param phpman.String $obj
			 */
			$this->object_module('before_block_template',String::ref($obj,$src));
			$src = (string)$obj;
			if(empty($blocks)){
				if(Xml::set($bx,'<:>'.$src.'</:>')){
					foreach($bx->in('rt:block') as $b) $src = str_replace($b->plain(),$b->value(),$src);
				}
			}else{
				if(!empty($this->template_super)) $src = $this->read_src($this->ab_path(str_replace("\\",'/',dirname($base_filename)),$this->template_super));
				while(Xml::set($b,$src,'rt:block')){
					$n = $b->in_attr('name');
					$src = str_replace($b->plain(),(array_key_exists($n,$blocks) ? $blocks[$n] : $b->value()),$src);
				}
			}
			$this->file = $filename;
		}
		return $src;
	}
	private function rtcomment($src){
		while(Xml::set($tag,$src,'rt:comment')) $src = str_replace($tag->plain(),'',$src);
		return $src;

	}
	private function rtunit($src){
		if(strpos($src,'rt:unit') !== false){
			while(Xml::set($tag,$src,'rt:unit')){
				$tag->escape(false);
				$uniq = uniqid('');
				$param = $tag->in_attr('param');
				$var = '$'.$tag->in_attr('var','_var_'.$uniq);
				$offset = $tag->in_attr('offset',1);
				$total = $tag->in_attr('total','_total_'.$uniq);
				$cols = ($tag->is_attr('cols')) ? (ctype_digit($tag->in_attr('cols')) ? $tag->in_attr('cols') : $this->variable_string($this->parse_plain_variable($tag->in_attr('cols')))) : 1;
				$rows = ($tag->is_attr('rows')) ? (ctype_digit($tag->in_attr('rows')) ? $tag->in_attr('rows') : $this->variable_string($this->parse_plain_variable($tag->in_attr('rows')))) : 0;
				$value = $tag->value();

				$cols_count = '$_ucount_'.$uniq;
				$cols_total = '$'.$tag->in_attr('cols_total','_cols_total_'.$uniq);
				$rows_count = '$'.$tag->in_attr('counter','_counter_'.$uniq);
				$rows_total = '$'.$tag->in_attr('rows_total','_rows_total_'.$uniq);
				$ucols = '$_ucols_'.$uniq;
				$urows = '$_urows_'.$uniq;
				$ulimit = '$_ulimit_'.$uniq;
				$ufirst = '$_ufirst_'.$uniq;
				$ufirstnm = '_ufirstnm_'.$uniq;

				$ukey = '_ukey_'.$uniq;
				$uvar = '_uvar_'.$uniq;

				$src = str_replace(
							$tag->plain(),
							sprintf(' %s=%s; %s=%s; %s=%s=1; %s=null; %s=%s*%s; %s=array(); ?>'
									.'<rt:loop param="%s" var="%s" key="%s" total="%s" offset="%s" first="%s">'
										.' if(%s <= %s){ %s[$%s]=$%s; } ?>'
										.'<rt:first> %s=$%s; ?></rt:first>'
										.'<rt:last> %s=%s; ?></rt:last>'
										.' if(%s===%s){ ?>'
											.' if(isset(%s)){ $%s=""; } ?>'
											.' %s=sizeof(%s); ?>'
											.' %s=ceil($%s/%s); ?>'
											.'%s'
											.' %s=array(); %s=null; %s=1; %s++; ?>'
										.' }else{ %s++; } ?>'
									.'</rt:loop>'
									,$ucols,$cols,$urows,$rows,$cols_count,$rows_count,$ufirst,$ulimit,$ucols,$urows,$var
									,$param,$uvar,$ukey,$total,$offset,$ufirstnm
										,$cols_count,$ucols,$var,$ukey,$uvar
										,$ufirst,$ufirstnm
										,$cols_count,$ucols
										,$cols_count,$ucols
											,$ufirst,$ufirstnm
											,$cols_total,$var
											,$rows_total,$total,$ucols
											,$value
											,$var,$ufirst,$cols_count,$rows_count
										,$cols_count
							)
							.($tag->is_attr('rows') ?
								sprintf(' for(;%s<=%s;%s++){ %s=array(); ?>%s } ?>',$rows_count,$rows,$rows_count,$var,$value) : ''
							)
							,$src
						);
			}
		}
		return $src;

	}
	private function rtloop($src){
		if(strpos($src,'rt:loop') !== false){
			while(Xml::set($tag,$src,'rt:loop')){
				$tag->escape(false);
				$param = ($tag->is_attr('param')) ? $this->variable_string($this->parse_plain_variable($tag->in_attr('param'))) : null;
				$offset = ($tag->is_attr('offset')) ? (ctype_digit($tag->in_attr('offset')) ? $tag->in_attr('offset') : $this->variable_string($this->parse_plain_variable($tag->in_attr('offset')))) : 1;
				$limit = ($tag->is_attr('limit')) ? (ctype_digit($tag->in_attr('limit')) ? $tag->in_attr('limit') : $this->variable_string($this->parse_plain_variable($tag->in_attr('limit')))) : 0;
				if(empty($param) && $tag->is_attr('range')){
					list($range_start,$range_end) = explode(',',$tag->in_attr('range'),2);
					$range = ($tag->is_attr('range_step')) ? sprintf('range(%d,%d,%d)',$range_start,$range_end,$tag->in_attr('range_step')) :
																sprintf('range("%s","%s")',$range_start,$range_end);
					$param = sprintf('array_combine(%s,%s)',$range,$range);
				}
				$is_fill = false;
				$uniq = uniqid('');
				$even = $tag->in_attr('even_value','even');
				$odd = $tag->in_attr('odd_value','odd');
				$evenodd = '$'.$tag->in_attr('evenodd','loop_evenodd');

				$first_value = $tag->in_attr('first_value','first');
				$first = '$'.$tag->in_attr('first','_first_'.$uniq);
				$first_flg = '$__isfirst__'.$uniq;
				$last_value = $tag->in_attr('last_value','last');
				$last = '$'.$tag->in_attr('last','_last_'.$uniq);
				$last_flg = '$__islast__'.$uniq;
				$shortfall = '$'.$tag->in_attr('shortfall','_DEFI_'.$uniq);

				$var = '$'.$tag->in_attr('var','_var_'.$uniq);
				$key = '$'.$tag->in_attr('key','_key_'.$uniq);
				$total = '$'.$tag->in_attr('total','_total_'.$uniq);
				$vtotal = '$__vtotal__'.$uniq;
				$counter = '$'.$tag->in_attr('counter','_counter_'.$uniq);
				$loop_counter = '$'.$tag->in_attr('loop_counter','_loop_counter_'.$uniq);
				$reverse = (strtolower($tag->in_attr('reverse') === 'true'));

				$varname = '$_'.$uniq;
				$countname = '$__count__'.$uniq;
				$lcountname = '$__vcount__'.$uniq;
				$offsetname	= '$__offset__'.$uniq;
				$limitname = '$__limit__'.$uniq;

				$value = $tag->value();
				$empty_value = null;
				while(Xml::set($subtag,$value,'rt:loop')){
					$value = $this->rtloop($value);
				}
				while(Xml::set($subtag,$value,'rt:first')){
					$value = str_replace($subtag->plain(),sprintf(' if(isset(%s)%s){ ?>%s } ?>',$first
					,(($subtag->in_attr('last') === 'false') ? sprintf(' && (%s !== 1) ',$total) : '')
					,preg_replace("/<rt\:else[\s]*.*?>/i"," }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Xml::set($subtag,$value,'rt:middle')){
					$value = str_replace($subtag->plain(),sprintf(' if(!isset(%s) && !isset(%s)){ ?>%s } ?>',$first,$last
					,preg_replace("/<rt\:else[\s]*.*?>/i"," }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Xml::set($subtag,$value,'rt:last')){
					$value = str_replace($subtag->plain(),sprintf(' if(isset(%s)%s){ ?>%s } ?>',$last
					,(($subtag->in_attr('first') === 'false') ? sprintf(' && (%s !== 1) ',$vtotal) : '')
					,preg_replace("/<rt\:else[\s]*.*?>/i"," }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Xml::set($subtag,$value,'rt:fill')){
					$is_fill = true;
					$value = str_replace($subtag->plain(),sprintf(' if(%s > %s){ ?>%s } ?>',$lcountname,$total
					,preg_replace("/<rt\:else[\s]*.*?>/i"," }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				$value = $this->rtif($value);
				if(preg_match("/^(.+)<rt\:else[\s]*.*?>(.+)$/ims",$value,$match)){
					list(,$value,$empty_value) = $match;
				}
				$src = str_replace(
							$tag->plain(),
							sprintf(" try{ ?>"
									." "
										." %s=%s;"
										." if(is_array(%s)){"
											." if(%s){ krsort(%s); }"
											." %s=%s=sizeof(%s); %s=%s=1; %s=%s; %s=((%s>0) ? (%s + %s) : 0); "
											." %s=%s=false; %s=0; %s=%s=null;"
											." if(%s){ for(\$i=0;\$i<(%s+%s-%s);\$i++){ %s[] = null; } %s=sizeof(%s); }"
											." foreach(%s as %s => %s){"
												." if(%s <= %s){"
													." if(!%s){ %s=true; %s='%s'; }"
													." if((%s > 0 && (%s+1) == %s) || %s===%s){ %s=true; %s='%s'; %s=(%s-%s+1) * -1;}"
													." %s=((%s %% 2) === 0) ? '%s' : '%s';"
													." %s=%s; %s=%s;"
													." ?>%s "
													." %s=%s=null;"
													." %s++;"
												." }"
												." %s++;"
												." if(%s > 0 && %s >= %s){ break; }"
											." }"
											." if(!%s){ ?>%s } "
											." unset(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s);"
										." }"
									." ?>"
									." }catch(\\Exception \$e){ if(!isset(\$_nes_) && \$_display_exception_){print(\$e->getMessage());} } ?>"
									,$varname,$param
									,$varname
										,(($reverse) ? 'true' : 'false'),$varname
										,$vtotal,$total,$varname,$countname,$lcountname,$offsetname,$offset,$limitname,$limit,$offset,$limit
										,$first_flg,$last_flg,$shortfall,$first,$last
										,($is_fill ? 'true' : 'false'),$offsetname,$limitname,$total,$varname,$vtotal,$varname
										,$varname,$key,$var
											,$offsetname,$lcountname
												,$first_flg,$first_flg,$first,str_replace("'","\\'",$first_value)
												,$limitname,$lcountname,$limitname,$lcountname,$vtotal,$last_flg,$last,str_replace("'","\\'",$last_value),$shortfall,$lcountname,$limitname
												,$evenodd,$countname,$even,$odd
												,$counter,$countname,$loop_counter,$lcountname
												,$value
												,$first,$last
												,$countname
											,$lcountname
											,$limitname,$lcountname,$limitname
									,$first_flg,$empty_value
									,$var,$counter,$key,$countname,$lcountname,$offsetname,$limitname,$varname,$first,$first_flg,$last,$last_flg
							)
							,$src
						);
			}
		}
		return $src;

	}
	private function rtif($src){
		if(strpos($src,'rt:if') !== false){
			while(Xml::set($tag,$src,'rt:if')){
				$tag->escape(false);
				if(!$tag->is_attr('param')) throw new \LogicException('if');
				$arg1 = $this->variable_string($this->parse_plain_variable($tag->in_attr('param')));

				if($tag->is_attr('value')){
					$arg2 = $this->parse_plain_variable($tag->in_attr('value'));
					if($arg2 == 'true' || $arg2 == 'false' || ctype_digit((string)$arg2)){
						$cond = sprintf(' if(%s === %s || %s === "%s"){ ?>',$arg1,$arg2,$arg1,$arg2);
					}else{
						if($arg2 === '' || $arg2[0] != '$') $arg2 = '"'.$arg2.'"';
						$cond = sprintf(' if(%s === %s){ ?>',$arg1,$arg2);
					}
				}else{
					$uniq = uniqid('$I');
					$cond = sprintf(" try{ %s=%s; }catch(\\Exception \$e){ %s=null; } ?>",$uniq,$arg1,$uniq)
								.sprintf(' if(%s !== null && %s !== false && ( (!is_string(%s) && !is_array(%s)) || (is_string(%s) && %s !== "") || (is_array(%s) && !empty(%s)) ) ){ ?>',$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq);
				}
				$src = str_replace(
							$tag->plain()
							,' try{ ?>'.$cond
								.preg_replace("/<rt\:else[\s]*.*?>/i"," }else{ ?>",$tag->value())
							." } ?>"
							." }catch(\\Exception \$e){ if(!isset(\$_nes_) && \$_display_exception_){print(\$e->getMessage());} } ?>"
							,$src
						);
			}
		}
		return $src;

	}
	private function parse_print_variable($src){
		foreach($this->match_variable($src) as $variable){
			$name = $this->parse_plain_variable($variable);
			$value = ' try{ @print('.$name.'); ?>'
						." }catch(\\Exception \$e){ if(!isset(\$_nes_) && \$_display_exception_){print(\$e->getMessage());} } ?>";
			$src = str_replace(array($variable."\n",$variable),array($value." 'PLRP'; ?>\n\n",$value),$src);
			$src = str_replace($variable,$value,$src);
		}
		return $src;
	}
	private function match_variable($src){
		$hash = array();
		while(preg_match("/({(\\$[\$\w][^\t]*)})/s",$src,$vars,PREG_OFFSET_CAPTURE)){
			list($value,$pos) = $vars[1];
			if($value == "") break;
			if(substr_count($value,'}') > 1){
				for($i=0,$start=0,$end=0;$i<strlen($value);$i++){
					if($value[$i] == '{'){
						$start++;
					}else if($value[$i] == '}'){
						if($start == ++$end){
							$value = substr($value,0,$i+1);
							break;
						}
					}
				}
			}
			$length	= strlen($value);
			$src = substr($src,$pos + $length);
			$hash[sprintf('%03d_%s',$length,$value)] = $value;
		}
		krsort($hash,SORT_STRING);
		return $hash;
	}
	private function parse_plain_variable($src){
		while(true){
			$array = $this->match_variable($src);
			if(sizeof($array) <= 0)	break;
			foreach($array as $v){
				$tmp = $v;
				if(preg_match_all("/([\"\'])([^\\1]+?)\\1/",$v,$match)){
					foreach($match[2] as $value) $tmp = str_replace($value,str_replace('.','__PERIOD__',$value),$tmp);
				}
				$src = str_replace($v,preg_replace('/([\w\)\]])\./','\\1->',substr($tmp,1,-1)),$src);
			}
		}
		return str_replace('[]','',str_replace('__PERIOD__','.',$src));
	}
	private function variable_string($src){
		return (empty($src) || isset($src[0]) && $src[0] == '$') ? $src : '$'.$src;
	}
	private function html_reform($src){
		if(strpos($src,'rt:aref') !== false){
			Xml::set($tag,'<:>'.$src.'</:>');
			foreach($tag->in('form') as $obj){
				if($obj->is_attr('rt:aref')){
					$bool = ($obj->in_attr('rt:aref') === 'true');
					$obj->rm_attr('rt:aref');
					$obj->escape(false);
					$value = $obj->get();

					if($bool){
						foreach($obj->in(array('input','select','textarea')) as $tag){
							if(!$tag->is_attr('rt:ref') && ($tag->is_attr('name') || $tag->is_attr('id'))){
								switch(strtolower($tag->in_attr('type','text'))){
									case 'button':
									case 'submit':
									case 'file':
										break;
									default:
										$tag->attr('rt:ref','true');
										$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
								}
							}
						}
						$value = $this->exec($this->parse_print_variable($this->html_input($obj->get())));
					}
					$src = str_replace($obj->plain(),$value,$src);
				}
			}
		}
		return $src;
	}
	private function html_form($src){
		Xml::set($tag,'<:>'.$src.'</:>');
		foreach($tag->in('form') as $obj){
			if($this->is_reference($obj)){
				$obj->escape(false);
				foreach($obj->in(array('input','select','textarea')) as $tag){
					if(!$tag->is_attr('rt:ref') && ($tag->is_attr('name') || $tag->is_attr('id'))){
						switch(strtolower($tag->in_attr('type','text'))){
							case 'button':
							case 'submit':
								break;
							case 'file':
								$obj->attr('enctype','multipart/form-data');
								$obj->attr('method','post');
								break;
							default:
								$tag->attr('rt:ref','true');
								$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
						}
					}
				}
				$src = str_replace($obj->plain(),$obj->get(),$src);
			}
		}
		return $this->html_input($src);
	}
	private function no_exception_str($value){
		return ' $_nes_=1; ?>'.$value.' $_nes_=null; ?>';
	}
	private function html_input($src){
		Xml::set($tag,'<:>'.$src.'</:>');
		foreach($tag->in(array('input','textarea','select')) as $obj){
			if('' != ($originalName = $obj->in_attr('name',$obj->in_attr('id','')))){
				$obj->escape(false);
				$type = strtolower($obj->in_attr('type','text'));
				$name = $this->parse_plain_variable($this->form_variable_name($originalName));
				$lname = strtolower($obj->name());
				$change = false;
				$uid = uniqid();

				if(substr($originalName,-2) !== '[]'){
					if($type == 'checkbox'){
						if($obj->in_attr('rt:multiple','true') === 'true') $obj->attr('name',$originalName.'[]');
						$obj->rm_attr('rt:multiple');
						$change = true;
					}else if($obj->is_attr('multiple') || $obj->in_attr('multiple') === 'multiple'){
						$obj->attr('name',$originalName.'[]');
						$obj->rm_attr('multiple');
						$obj->attr('multiple','multiple');
						$change = true;
					}
				}else if($obj->in_attr('name') !== $originalName){
					$obj->attr('name',$originalName);
					$change = true;
				}
				if($obj->is_attr('rt:param') || $obj->is_attr('rt:range')){
					switch($lname){
						case 'select':
							$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" key="%s" offset="%s" limit="%s" reverse="%s" evenodd="%s" even_value="%s" odd_value="%s" range="%s" range_step="%s">'
											.'<option value="{$%s}">{$%s}</option>'
											.'</rt:loop>'
											,$obj->in_attr('rt:param'),$obj->in_attr('rt:var','loop_var'.$uid),$obj->in_attr('rt:counter','loop_counter'.$uid)
											,$obj->in_attr('rt:key','loop_key'.$uid),$obj->in_attr('rt:offset','0'),$obj->in_attr('rt:limit','0')
											,$obj->in_attr('rt:reverse','false')
											,$obj->in_attr('rt:evenodd','loop_evenodd'.$uid),$obj->in_attr('rt:even_value','even'),$obj->in_attr('rt:odd_value','odd')
											,$obj->in_attr('rt:range'),$obj->in_attr('rt:range_step',1)
											,$obj->in_attr('rt:key','loop_key'.$uid),$obj->in_attr('rt:var','loop_var'.$uid)
							);
							$obj->value($this->rtloop($value));
							if($obj->is_attr('rt:null')) $obj->value('<option value="">'.$obj->in_attr('rt:null').'</option>'.$obj->value());
					}
					$obj->rm_attr('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd'
									,'rt:range','rt:range_step','rt:even_value','rt:odd_value');
					$change = true;
				}
				if($this->is_reference($obj)){
					switch($lname){
						case 'textarea':
							$obj->value($this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',((preg_match("/^{\$(.+)}$/",$originalName,$match)) ? '{$$'.$match[1].'}' : '{$'.$originalName.'}'))));
							break;
						case 'select':
							$select = $obj->value();
							foreach($obj->in('option') as $option){
								$option->escape(false);
								$value = $this->parse_plain_variable($option->in_attr('value'));
								if(empty($value) || $value[0] != '$') $value = sprintf("'%s'",$value);
								$option->rm_attr('selected');
								$option->plain_attr($this->check_selected($name,$value,'selected'));
								$select = str_replace($option->plain(),$option->get(),$select);
							}
							$obj->value($select);
							break;
						case 'input':
							switch($type){
								case 'checkbox':
								case 'radio':
									$value = $this->parse_plain_variable($obj->in_attr('value','true'));
									$value = (substr($value,0,1) != '$') ? sprintf("'%s'",$value) : $value;
									$obj->rm_attr('checked');
									$obj->plain_attr($this->check_selected($name,$value,'checked'));
									break;
								case 'text':
								case 'hidden':
								case 'password':
								case 'search':
								case 'url':
								case 'email':
								case 'tel':
								case 'datetime':
								case 'date':
								case 'month':
								case 'week':
								case 'time':
								case 'datetime-local':
								case 'number':
								case 'range':
								case 'color':
									$obj->attr('value',$this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',
																((preg_match("/^\{\$(.+)\}$/",$originalName,$match)) ?
																	'{$$'.$match[1].'}' :
																	'{$'.$originalName.'}'))));
									break;
							}
							break;
					}
					$change = true;
				}else if($obj->is_attr('rt:ref')){
					$obj->rm_attr('rt:ref');
					$change = true;
				}
				if($change){
					switch($lname){
						case 'textarea':
						case 'select':
							$obj->close_empty(false);
					}
					$src = str_replace($obj->plain(),$obj->get(),$src);
				}
			}
		}
		return $src;

	}
	private function check_selected($name,$value,$selected){
		return sprintf(' if('
					.'isset(%s) && (%s === %s '
										.' || (!is_array(%s) && ctype_digit((string)%s) && (string)%s === (string)%s)'
										.' || ((%s === "true" || %s === "false") ? (%s === (%s == "true")) : false)'
										.' || in_array(%s,((is_array(%s)) ? %s : (is_null(%s) ? array() : array(%s))),true) '
									.') '
					.'){print(" %s=\"%s\"");} ?>'
					,$name,$name,$value
					,$name,$name,$name,$value
					,$value,$value,$name,$value
					,$value,$name,$name,$name,$name
					,$selected,$selected
				);
	}
	private function html_list($src){
		if(preg_match_all('/<(table|ul|ol)\s[^>]*rt\:/i',$src,$m,PREG_OFFSET_CAPTURE)){
			$tags = array();
			foreach($m[1] as $k => $v){
				if(Xml::set($tag,substr($src,$v[1]-1),$v[0])) $tags[] = $tag;
			}
			foreach($tags as $obj){
				$obj->escape(false);
				$name = strtolower($obj->name());
				$param = $obj->in_attr('rt:param');
				$null = strtolower($obj->in_attr('rt:null'));
				$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" '
									.'key="%s" offset="%s" limit="%s" '
									.'reverse="%s" '
									.'evenodd="%s" even_value="%s" odd_value="%s" '
									.'range="%s" range_step="%s" '
									.'shortfall="%s">'
								,$param,$obj->in_attr('rt:var','loop_var'),$obj->in_attr('rt:counter','loop_counter')
								,$obj->in_attr('rt:key','loop_key'),$obj->in_attr('rt:offset','0'),$obj->in_attr('rt:limit','0')
								,$obj->in_attr('rt:reverse','false')
								,$obj->in_attr('rt:evenodd','loop_evenodd'),$obj->in_attr('rt:even_value','even'),$obj->in_attr('rt:odd_value','odd')
								,$obj->in_attr('rt:range'),$obj->in_attr('rt:range_step',1)
								,$tag->in_attr('rt:shortfall','_DEFI_'.uniqid())
							);
				$rawvalue = $obj->value();
				if($name == 'table' && Xml::set($t,$rawvalue,'tbody')){
					$t->escape(false);
					$t->value($value.$this->table_tr_even_odd($t->value(),(($name == 'table') ? 'tr' : 'li'),$obj->in_attr('rt:evenodd','loop_evenodd')).'</rt:loop>');
					$value = str_replace($t->plain(),$t->get(),$rawvalue);
				}else{
					$value = $value.$this->table_tr_even_odd($rawvalue,(($name == 'table') ? 'tr' : 'li'),$obj->in_attr('rt:evenodd','loop_evenodd')).'</rt:loop>';
				}
				$obj->value($this->html_list($value));
				$obj->rm_attr('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd','rt:range'
								,'rt:range_step','rt:even_value','rt:odd_value','rt:shortfall');
				$src = str_replace($obj->plain(),
						($null === 'true') ? $this->rtif(sprintf('<rt:if param="%s">',$param).$obj->get().'</rt:if>') : $obj->get(),
						$src);
			}
		}
		return $src;

	}
	private function table_tr_even_odd($src,$name,$even_odd){
		Xml::set($tag,'<:>'.$src.'</:>');
		foreach($tag->in($name) as $tr){
			$tr->escape(false);
			$class = ' '.$tr->in_attr('class').' ';
			if(preg_match('/[\s](even|odd)[\s]/',$class,$match)){
				$tr->attr('class',trim(str_replace($match[0],' {$'.$even_odd.'} ',$class)));
				$src = str_replace($tr->plain(),$tr->get(),$src);
			}
		}
		return $src;
	}
	private function form_variable_name($name){
		return (strpos($name,'[') && preg_match("/^(.+)\[([^\"\']+)\]$/",$name,$match)) ?
			'{$'.$match[1].'["'.$match[2].'"]'.'}' : '{$'.$name.'}';
	}
	private function is_reference(&$tag){
		$bool = ($tag->in_attr('rt:ref') === 'true');
		$tag->rm_attr('rt:ref');
		return $bool;
	}
}
