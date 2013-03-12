<?php
namespace testman{
	class Util{
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
		}
		static public function parse_args(){
			$params = array();
			$value = null;
			if(isset($_SERVER['REQUEST_METHOD'])){
				$params = isset($_GET) ? $_GET : array();
			}else{
				$argv = array_slice($_SERVER['argv'],1);
				$value = (empty($argv)) ? null : array_shift($argv);
				$params = array();
	
				if(substr($value,0,1) == '-'){
					array_unshift($argv,$value);
					$value = null;
				}
				for($i=0;$i<sizeof($argv);$i++){
					if($argv[$i][0] == '-'){
						$k = substr($argv[$i],1);
						$v = (isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : '';
						if(isset($params[$k]) && !is_array($params[$k])) $params[$k] = array($params[$k]);
						$params[$k] = (isset($params[$k])) ? array_merge($params[$k],array($v)) : $v;
					}
				}
			}
			return array($value,$params);
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
			return \testman\Xml::set($this->tag,$this->plain,$this->name);
		}
		public function next(){
		}
		public function rewind(){
			for($i=0;$i<$this->offset;$i++){
				$this->valid();
				$this->current();
			}
		}
	}
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
			return new \testman\XmlIterator($name,$this->value(),$offset,$length);
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
	}
	class Http{
		private $resource;
		private $agent;
		private $timeout = 30;
		private $redirect_max = 20;
		private $redirect_count = 1;
	
		private $request_header = array();
		private $request_vars = array();
		private $request_file_vars = array();
		private $head;
		private $body;
		private $cookie = array();
		private $url;
		private $status;
	
		public function __construct($agent=null,$timeout=30,$redirect_max=20){
			$this->agent = $agent;
			$this->timeout = (int)$timeout;
			$this->redirect_max = (int)$redirect_max;
			$this->resource = curl_init();
		}
		public function redirect_max($redirect_max){
			$this->redirect_max = (integer)$redirect_max;
		}
		public function timeout($timeout){
			$this->timeout = (int)$timeout;
		}
		public function agent($agent){
			$this->agent = $agent;
		}
		public function __toString(){
			return $this->body();
		}
		public function header($key,$value=null){
			$this->request_header[$key] = $value;
		}
		public function vars($key,$value=null){
			if(is_bool($value)) $value = ($value) ? 'true' : 'false';
			$this->request_vars[$key] = $value;
			if(isset($this->request_file_vars[$key])) unset($this->request_file_vars[$key]);
		}
		public function file_vars($key,$value){
			$this->request_file_vars[$key] = $value;
			if(isset($this->request_vars[$key])) unset($this->request_vars[$key]);
		}
		public function setopt($key,$value){
			curl_setopt($this->resource,$key,$value);
		}
		public function head(){
			return $this->head;
		}
		public function body(){
			return ($this->body === null || is_bool($this->body)) ? '' : $this->body;
		}
		public function url(){
			return $this->url;
		}
		public function status(){
			return $this->status;
		}
		public function do_head($url){
			return $this->request('HEAD',$url);
		}
		public function do_put($url){
			return $this->request('PUT',$url);
		}
		public function do_delete(){
			return $this->request('DELETE',$url);
		}
		public function do_get($url){
			return $this->request('GET',$url);
		}
		public function do_post($url){
			return $this->request('POST',$url);
		}
		public function do_download($url,$download_path){
			return $this->request('GET',$url,$download_path);
		}
		public function do_post_download($url,$download_path){
			return $this->request('POST',$url,$download_path);
		}
		private function request($method,$url,$download_path=null){
			$url_info = parse_url($url);
			$cookie_base_domain = $url_info['host'].(isset($url_info['path']) ? $url_info['path'] : '');
			if(isset($url_info['query'])){
				parse_str($url_info['query'],$vars);
				foreach($vars as $k => $v){
					if(!isset($this->request_vars[$k])) $this->request_vars[$k] = $v;
				}
				list($url) = explode('?',$url,2);
			}
			switch($method){
				case 'POST': curl_setopt($this->resource,CURLOPT_POST,true); break;
				case 'GET': curl_setopt($this->resource,CURLOPT_HTTPGET,true); break;
				case 'HEAD': curl_setopt($this->resource,CURLOPT_NOBODY,true); break;
				case 'PUT': curl_setopt($this->resource,CURLOPT_PUT,true); break;
				case 'DELETE': curl_setopt($this->resource,CURLOPT_CUSTOMREQUEST,'DELETE'); break;
			}
			switch($method){
				case 'POST':
					$vars = array();
					if(!empty($this->request_vars)){
						foreach(explode('&',http_build_query($this->request_vars)) as $q){
							$s = explode('=',$q,2);
							$vars[urldecode($s[0])] = isset($s[1]) ? urldecode($s[1]) : null;
						}
					}
					if(!empty($this->request_file_vars)){
						foreach(explode('&',http_build_query($this->request_file_vars)) as $q){
							$s = explode('=',$q,2);
							$vars[urldecode($s[0])] = isset($s[1]) ? '@'.urldecode($s[1]) : null;
						}
					}
					curl_setopt($this->resource,CURLOPT_POSTFIELDS,$vars);
					break;
				case 'GET':
				case 'HEAD':
				case 'PUT':
				case 'DELETE':
					$url = $url.(!empty($this->request_vars) ? '?'.http_build_query($this->request_vars) : '');
			}
			curl_setopt($this->resource,CURLOPT_URL,$url);
			curl_setopt($this->resource,CURLOPT_FOLLOWLOCATION,false);
			curl_setopt($this->resource,CURLOPT_HEADER,false);
			curl_setopt($this->resource,CURLOPT_RETURNTRANSFER,false);
			curl_setopt($this->resource,CURLOPT_FORBID_REUSE,true);
			curl_setopt($this->resource,CURLOPT_FAILONERROR,false);
			curl_setopt($this->resource,CURLOPT_TIMEOUT,$this->timeout);
	
			if(!isset($this->request_header['Expect'])){
				$this->request_header['Expect'] = null;
			}
			if(!isset($this->request_header['Cookie'])){
				$cookies = '';
				foreach($this->cookie as $domain => $cookie_value){
					if(strpos($cookie_base_domain,$domain) === 0 || strpos($cookie_base_domain,(($domain[0] == '.') ? $domain : '.'.$domain)) !== false){
						foreach($cookie_value as $k => $v){
							if(!$v['secure'] || ($v['secure'] && substr($url,0,8) == 'https://')) $cookies .= sprintf('%s=%s; ',$k,$v['value']);
						}
					}
				}
				curl_setopt($this->resource,CURLOPT_COOKIE,$cookies);
			}
			if(!isset($this->request_header['User-Agent'])){
				curl_setopt($this->resource,CURLOPT_USERAGENT,
						(empty($this->agent) ?
								(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) :
								$this->agent
						)
				);
			}
			if(!isset($this->request_header['Accept']) && isset($_SERVER['HTTP_ACCEPT'])){
				$this->request_header['Accept'] = $_SERVER['HTTP_ACCEPT'];
			}
			if(!isset($this->request_header['Accept-Language']) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
				$this->request_header['Accept-Language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}
			if(!isset($this->request_header['Accept-Charset']) && isset($_SERVER['HTTP_ACCEPT_CHARSET'])){
				$this->request_header['Accept-Charset'] = $_SERVER['HTTP_ACCEPT_CHARSET'];
			}
	
			curl_setopt($this->resource,CURLOPT_HTTPHEADER,
					array_map(function($k,$v){
				return $k.': '.$v;
			}
			,array_keys($this->request_header)
			,$this->request_header
			)
			);
			curl_setopt($this->resource,CURLOPT_HEADERFUNCTION,function($c,$data){
				$this->head .= $data;
				return strlen($data);
			});
			if(empty($download_path)){
				curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,function($c,$data){
					$this->body .= $data;
					return strlen($data);
				});
			}else{
				if(!is_dir(dirname($download_path))) mkdir(dirname($download_path),0777,true);
				$fp = fopen($download_path,'wb');
	
				curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,function($c,$data) use(&$fp){
					if($fp) fwrite($fp,$data);
					return strlen($data);
				});
			}
			$this->request_header = $this->request_vars = array();
			$this->head = $this->body = '';
			curl_exec($this->resource);
			if(!empty($download_path) && $fp){
				fclose($fp);
			}
	
			$this->url = trim(curl_getinfo($this->resource,CURLINFO_EFFECTIVE_URL));
			$this->status = curl_getinfo($this->resource,CURLINFO_HTTP_CODE);
	
			if($err_code = curl_errno($this->resource) > 0){
				if($err_code == 47) return $this;
				throw new \RuntimeException($err_code.': '.curl_error($this->resource));
			}
			if(preg_match_all('/Set-Cookie:[\s]*(.+)/i',$this->head,$match)){
				$unsetcookie = $setcookie = array();
				foreach($match[1] as $cookies){
					$cookie_name = $cookie_value = $cookie_domain = $cookie_path = $cookie_expires = null;
					$cookie_domain = $cookie_base_domain;
					$cookie_path = '/';
					$secure = false;
	
					foreach(explode(';',$cookies) as $cookie){
						$cookie = trim($cookie);
						if(strpos($cookie,'=') !== false){
							list($k,$v) = explode('=',$cookie,2);
							$k = trim($k);
							$v = trim($v);
							switch(strtolower($k)){
								case 'expires': $cookie_expires = ctype_digit($v) ? (int)$v : strtotime($v); break;
								case 'domain': $cookie_domain = preg_replace('/^[\w]+:\/\/(.+)$/','\\1',$v); break;
								case 'path': $cookie_path = $v; break;
								default:
									$cookie_name = $k;
									$cookie_value = $v;
							}
						}else if(strtolower($cookie) == 'secure'){
							$secure = true;
						}
					}
					$cookie_domain = substr(\testman\Util::path_absolute('http://'.$cookie_domain,$cookie_path),7);
					if($cookie_expires !== null && $cookie_expires < time()){
						if(isset($this->cookie[$cookie_domain][$cookie_name])) unset($this->cookie[$cookie_domain][$cookie_name]);
					}else{
						$this->cookie[$cookie_domain][$cookie_name] = array('value'=>$cookie_value,'expires'=>$cookie_expires,'secure'=>$secure);
					}
				}
			}
			if($this->redirect_count++ < $this->redirect_max){
				switch($this->status){
					case 300:
					case 301:
					case 302:
					case 303:
					case 307:
						if(preg_match('/Location:[\040](.*)/i',$this->head,$redirect_url)){
							return $this->request('GET',\testman\Util::path_absolute($url,$redirect_url[1]),$download_path);
						}
				}
			}
			$this->redirect_count = 1;
			return $this;
		}
		public function __destruct(){
			curl_close($this->resource);
		}
		private function info(){
			return curl_getinfo($this->resource);
		}
	}
	class Coverage{
		static private $base_dir;
		static private $target_dir;
		static private $start = false;
		static private $savedb;
	
		static public function has_started(&$vars){
			if(self::$start){
				$vars = array();
				$vars['savedb'] = self::$savedb;
				$vars['base_dir'] = self::$base_dir;
				$vars['target_dir'] = self::$target_dir;
				$vars['current_name'] = \testman\TestRunner::current_name();
					
				return true;
			}
			return false;
		}
		static public function start($savedb,$base_dir,$target_dir){
			if(extension_loaded('xdebug') && self::$start === false){
				xdebug_start_code_coverage();
				self::$start = true;
				self::$savedb = $savedb;
				$exist = (is_file(self::$savedb));
	
				if(!empty($target_dir) && !is_array($target_dir)) $target_dir = array($target_dir);
				self::$target_dir = $target_dir;
				self::$base_dir = str_replace('\\','/',$base_dir);
				if(substr(self::$base_dir,-1) != '/') self::$base_dir = self::$base_dir.'/';
					
				if($db = new \PDO('sqlite:'.self::$savedb)){
					if(!$exist){
						$sql = 'create table coverage('.
								'id integer not null primary key,'.
								'parent_path text,'.
								'src text,'.
								'file_path text not null,'.
								'covered_line text not null,'.
								'ignore_line text,'.
								'covered_len integer,'.
								'active_len integer,'.
								'file_len integer,'.
								'percent integer'.
								')';
						if(false === $db->query($sql)) throw new \RuntimeException('failure create coverage table');
							
						$sql = 'create table coverage_info('.
								'id integer not null primary key,'.
								'create_date text,'.
								'test_path text,'.
								'result text'.
								')';
						if(false === $db->query($sql)) throw new \RuntimeException('failure create coverage_info table');
	
						$sql = 'create table coverage_covered('.
								'id integer not null primary key,'.
								'test_path text,'.
								'covered_line text,'.
								'file_path text'.
								')';
						if(false === $db->query($sql)) throw new \RuntimeException('failure create coverage_covered table');
							
						$sql = 'create table coverage_tree('.
								'id integer not null primary key,'.
								'parent_path text not null,'.
								'path text not null'.
								')';
						if(false === $db->query($sql)) throw new \RuntimeException('failure create coverage_tree table');
							
						$sql = 'create table coverage_tree_root('.
								'path text not null'.
								')';
						if(false === $db->query($sql)) throw new \RuntimeException('failure create coverage_tree_root table');
							
							
						$sql = 'insert into coverage_info(create_date) values(?)';
						$ps = $db->prepare($sql);
						$ps->execute(array(time()));
							
						$sql = 'insert into coverage_tree_root(path) values(?)';
						$ps = $db->prepare($sql);
						foreach(self::$target_dir as $path){
							$path = str_replace('\\','/',$path);
							if(substr($path,-1) == '/') $path = substr($path,0,-1);
							$ps->execute(array(basename($path)));
						}
					}
					register_shutdown_function(array(__CLASS__,'stop'));
				}
			}
		}
		static public function save($restart=true){
			if(self::$start){
				if($db = new \PDO('sqlite:'.self::$savedb)){
					$db->beginTransaction();
	
					$get_prepare = function($db,$sql){
						$ps = $db->prepare($sql);
						if($ps === false) throw new \LogicException($sql);
						return $ps;
					};
	
					$insert_ps = $get_prepare($db,'insert into coverage(file_path,covered_line,file_len,covered_len,src) values(?,?,?,?,?)');
					$getid_ps = $get_prepare($db,'select id,covered_line from coverage where file_path=?');
					$update_ps = $get_prepare($db,'update coverage set covered_line=?,covered_len=? where id=?');
					$insert_exe_ps = $get_prepare($db,'insert into coverage_covered(file_path,covered_line,test_path) values(?,?,?)');
	
					foreach(xdebug_get_code_coverage() as $file_path => $lines){
						if(
								strpos($file_path,'phar://') !== 0 &&
								strpos($file_path,'/_') === false &&
								is_file($file_path)
						){
							$bool = false;
	
							if(empty(self::$target_dir)){
								$bool = true;
							}else{
								foreach(self::$target_dir as $dir){
									if(strpos($file_path,$dir) === 0){
										$bool = true;
										break;
									}
								}
							}
							if($bool){
								$p = str_replace(self::$base_dir,'',$file_path);
	
								$pre_id = $pre_line = null;
								$getid_ps->execute(array($p));
								while($resultset = $getid_ps->fetch(PDO::FETCH_ASSOC)){
									$pre_id = $resultset['id'];
									$pre_line = $resultset['covered_line'];
								}
								if(!isset($pre_id)){
									$insert_ps->execute(array(
											$p,
											json_encode(array_keys($lines)),
											sizeof(file($file_path)),
											sizeof($lines),
											file_get_contents($file_path)
									));
								}else{
									$line_array = array_flip(json_decode($pre_line,true));
									foreach($lines as $k => $v) $line_array[$k] = $k;
									$covered_line = array_keys($line_array);
	
									$update_ps->execute(array(
											json_encode($covered_line),
											sizeof($covered_line),
											$pre_id
									));
								}
								$insert_exe_ps->execute(array(
										$p,
										implode(',',array_keys($lines)),
										\testman\Testrunner::current_name()
								));
							}
						}
					}
					$db->commit();
	
					xdebug_stop_code_coverage();
					self::$start = false;
	
					if($restart){
						xdebug_start_code_coverage();
						self::$start = true;
					}
				}
			}
		}
		/**
		 * @param string $src
		 * @return array($active_count,$ignore_line,$src,count)
		 */
		static public function parse_line($src){
			if(empty($src)) return array(0,array(),0);
			$ignore_line = array();
	
			$ignore_line_func = function($c0,$c1,$src){
				$s = substr_count(substr($src,0,$c1),PHP_EOL);
				$e = substr_count($c0,PHP_EOL);
				return range($s+1,$s+1+$e);
			};
			$parse = function($src,&$ignore_line,$preg_pattern) use($ignore_line_func){
				if(preg_match_all($preg_pattern,$src,$m,PREG_OFFSET_CAPTURE)){
					foreach($m[1] as $c){
						$ignore_line = array_merge($ignore_line,$ignore_line_func($c[0],$c[1],$src));
					}
				}
			};
			$parse($src,$ignore_line,"/(\/\*.*?\*\/)/ms");
			$parse($src,$ignore_line,"/^((namespace|use|class)[\040\t].+)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*(final|static|protected|private|public|const)[\040\t].+)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*\/\/.+)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*#.+)$/m");
			$parse($src,$ignore_line,"/^([\s]*<\?php[\040\t]*)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*\?>[\040\t]*)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*try[\040\t]*\{[\040\t]*)$/m");
			$parse($src,$ignore_line,"/^([\040\t\}]*catch[\040\t]*\(.+\).+)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*switch[\040\t]*\(.+\).+)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*\}[\040\t]*else[\040\t]*\{[\040\t]*)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*\{[\040\t]*)$/m");
			$parse($src,$ignore_line,"/^([\040\t]*\}[\040\t]*)$/m");
			$parse($src,$ignore_line,"/^([\040\t\(\)]+)$/m");
			$parse($src,$ignore_line,"/^([\s]*)$/ms");
			$parse($src,$ignore_line,"/(\n)$/s");
	
			$ignore_line = array_unique($ignore_line);
			sort($ignore_line);
			$src_count = substr_count($src,PHP_EOL) + 1;
			return array(($src_count-sizeof($ignore_line)),$ignore_line,$src_count);
		}
		static public function stop(){
			self::save(false);
			$dirlist = array();
	
			if(is_file(self::$savedb) && ($db = new \PDO('sqlite:'.self::$savedb))){
				$sql = 'select file_path,id,src,active_len,covered_line,covered_len from coverage order by file_path';
				$ps = $db->query($sql);
					
				$update_sql = 'update coverage set parent_path=?,active_len=?,ignore_line=?,covered_line=?,covered_len=?,percent=? where id=?';
				$update_ps = $db->prepare($update_sql);
				if($update_ps === false) throw new \LogicException($update_sql);
					
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$percent = 0;
					$dir = dirname($resultset['file_path']);
					list($active_len,$ignore_line,$src_count) = self::parse_line($resultset['src']);
					$covered_lines = array_unique(json_decode($resultset['covered_line'],true));
					foreach($covered_lines as $k => $v){
						if($v === 0 || $v > $src_count) unset($covered_lines[$k]);
					}
					sort($covered_lines);
	
					$covered_dup = sizeof(array_intersect($covered_lines,$ignore_line));
					$covered_len = sizeof($covered_lines) - $covered_dup;
					$percent = ($active_len === 0) ? 100 : (($covered_len === 0) ? 0 : (floor($covered_len / $active_len * 100)));
	
					$update_ps->execute(array($dir,$active_len,json_encode($ignore_line),json_encode($covered_lines),$covered_len,(int)$percent,$resultset['id']));
	
					while(strpos($dir,'/') !== false){
						$dirlist[$dir] = dirname($dir);
						$dir = dirname($dir);
					}
				}
				$cnt_sql = 'select count(path) as cnt from coverage_tree where parent_path=? and path=?';
				$cnt_ps = $db->prepare($cnt_sql);
				if($cnt_ps === false) throw new \LogicException($cnt_sql);
	
				$insert_sql = 'insert into coverage_tree(parent_path,path) values(?,?)';
				$insert_ps = $db->prepare($insert_sql);
				if($insert_ps === false) throw new \LogicException($insert_sql);
	
				foreach($dirlist as $dir => $parent_dir){
					$cnt_ps->execute(array($parent_dir,$dir));
					$resultset = $cnt_ps->fetch(PDO::FETCH_ASSOC);
					if((int)$resultset['cnt'] === 0){
						$insert_ps->execute(array($parent_dir,$dir));
					}
				}
				$sql = 'update coverage_info set result=?, test_path=?';
				$ps = $db->prepare($sql);
				$ps->execute(array(
						json_encode(\testman\TestRunner::get()),
						json_encode(\testman\TestRunner::search_path())
				));
			}
		}
		static public function test_result($savedb){
			if(is_file($savedb) && ($db = new \PDO('sqlite:'.$savedb))){
				$sql = 'select result,test_path from coverage_info';
				$ps = $db->prepare($sql);
				$ps->execute();
	
				$success = $fail = $none = 0;
				$failure = array();
					
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$result = json_decode($resultset['result'],true);
					$test_path = json_decode($resultset['test_path'],true);
					if(!is_array($result)) $result = array();
					if(!is_array($test_path)) $test_path = array();
					rsort($test_path);
	
					foreach($result as $file => $f){
						foreach($f as $class => $c){
							foreach($c as $method => $m){
								foreach($m as $line => $r){
									foreach($r as $l){
										$info = array_shift($l);
										foreach($test_path as $p) $file = str_replace(dirname($p),'',$file);
										$name_var = array('class'=>$class,'file'=>$file,'method'=>$method,'line'=>$line);
											
										switch(sizeof($l)){
											case 0: // success
												$success++;
												break;
											case 1: // none
												$none++;
												break;
											case 2: // fail
												$fail++;
												$result_a = $result_b = null;
													
												ob_start();
												var_dump($l[0]);
												$result_a .= ob_get_contents();
												ob_end_clean();
	
												ob_start();
												var_dump($l[1]);
												$result_b .= ob_get_contents();
												ob_end_clean();
													
												$failure[] = array('location'=>$name_var,'expected'=>$result_a,'actual'=>$result_b);
												break;
											case 4: // exception
												$fail++;
												$failure[] = array('location'=>$name_var,'expected'=>$l[0],'actual'=>$l[2].':'.$l[3]);
												break;
										}
									}
								}
							}
						}
					}
					return array($success,$fail,$none,$failure);
				}
			}
			return array(0,0,0,array());
		}
		static public function dir_list($savedb,$dir=null){
			$result_dir = $result_file = $avg = array();
			$avg = array('avg'=>0,'uncovered'=>100,'covered'=>0);
			$parent_path = null;
	
			if(is_file($savedb) && ($db = new \PDO('sqlite:'.$savedb))){
				if(empty($dir)){
					$sql = 'select path from coverage_tree_root order by path';
					$ps = $db->prepare($sql);
					$ps->execute();
	
					while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
						$result_dir[] = $resultset['path'];
					}
	
					$avg_sql = 'select avg(percent) as percent_avg from coverage';
					$avg_ps = $db->prepare($avg_sql);
					$avg_ps->execute();
						
					if($resultset = $avg_ps->fetch(PDO::FETCH_ASSOC)){
						$avg['avg'] = floor($resultset['percent_avg']);
						$avg['uncovered'] = 100 - $resultset['percent_avg'];
						$avg['covered'] = 100 - $avg['uncovered'];
					}
				}else{
					$sql = 'select parent_path from coverage_tree where path=?';
					$ps = $db->prepare($sql);
					$ps->execute(array($dir));
					while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
						$parent_path = $resultset['parent_path'];
					}
	
					$sql = 'select path from coverage_tree where parent_path=? order by path';
					$ps = $db->prepare($sql);
					$ps->execute(array($dir));
					while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
						$result_dir[] = $resultset['path'];
					}
	
					$sql = 'select file_path,file_len,covered_len,active_len,percent from coverage where parent_path=? order by file_path';
					$ps = $db->prepare($sql);
					$ps->execute(array($dir));
	
					while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
						$resultset['uncovered'] = 100 - $resultset['percent'];
						$resultset['covered'] = 100 - $resultset['uncovered'];
						$result_file[] = $resultset;
					}
	
					$avg_sql = 'select avg(percent) as percent_avg from coverage where file_path like(?)';
					$avg_ps = $db->prepare($avg_sql);
					$avg_ps->execute(array($dir.'/%'));
						
					if($resultset = $avg_ps->fetch(PDO::FETCH_ASSOC)){
						$avg['avg'] = floor($resultset['percent_avg']);
						$avg['uncovered'] = 100 - $resultset['percent_avg'];
						$avg['covered'] = 100 - $avg['uncovered'];
					}
				}
			}
			return array($result_dir,$result_file,$parent_path,$avg);
		}
	
		static public function all_file_list($savedb){
			$result_file = array();
			$avg = array('avg'=>0,'uncovered'=>100,'covered'=>0);
	
			if(is_file($savedb) && ($db = new \PDO('sqlite:'.$savedb))){
				$sql = 'select file_path,file_len,covered_len,active_len,percent from coverage order by file_path';
				$ps = $db->prepare($sql);
				$ps->execute();
	
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$resultset['uncovered'] = 100 - $resultset['percent'];
					$resultset['covered'] = 100 - $resultset['uncovered'];
					$result_file[] = $resultset;
				}
					
				$sql = 'select avg(percent) as percent_avg from coverage';
				$ps = $db->prepare($sql);
				$ps->execute();
					
				if($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$avg['avg'] = floor($resultset['percent_avg']);
					$avg['uncovered'] = 100 - $resultset['percent_avg'];
					$avg['covered'] = 100 - $avg['uncovered'];
				}
			}
			return array($result_file,$avg);
		}
	
		static public function file($savedb,$file_path){
			$result = array();
	
			if(is_file($savedb) && ($db = new \PDO('sqlite:'.$savedb))){
				$covered_line = array();
				$sql = 'select test_path,covered_line from coverage_covered where file_path=?';
				$ps = $db->prepare($sql);
				if($ps === false) throw new \LogicException($sql);
				$ps->execute(array($file_path));
					
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					foreach(explode(',',$resultset['covered_line']) as $line){
						if(!isset($covered_line[$line])) $covered_line[$line] = array();
						$covered_line[$line][$resultset['test_path']] = $resultset['test_path'];
					}
				}
	
				$sql = 'select file_path,covered_line,file_len,covered_len,ignore_line,src from coverage where file_path=?';
				$ps = $db->prepare($sql);
				if($ps === false) throw new \LogicException($sql);
				$ps->execute(array($file_path));
					
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$src_lines = explode(PHP_EOL,$resultset['src']);
					$covered_lines = array_flip(json_decode($resultset['covered_line'],true));
					$ignore_lines = array_flip(json_decode($resultset['ignore_line'],true));
					$view_src_lines = array();
	
					foreach($src_lines as $k => $v){
						$line_num = $k + 1;
						$class = isset($ignore_lines[$line_num]) ? 'ignore' : (isset($covered_lines[$line_num]) ? 'covered' : 'uncovered');
						$test_path = ($class == 'ignore') ? array() : (isset($covered_line[$line_num]) ? $covered_line[$line_num] : array());
						$view_src_lines[$k] = array('value'=>$v,'class'=>$class,'test_path'=>$test_path);
					}
					$resultset['view'] = $view_src_lines;
					return $resultset;
				}
			}
			throw new \InvalidArgumentException($file_path.' not found');
		}
	}
	class TestRunner{
		static private $result = array();
		static private $current_entry;
		static private $current_class;
		static private $current_method;
		static private $current_file;
		static private $current_block_name;
		static private $current_block_label;
		static private $current_block_start_time;
		static private $start_time;
		static private $urls;
	
		static private $entry_dir;
		static private $test_dir;
		static private $lib_dir;
		static private $func_dir;
	
		static private $exec_file = array();
		static private $exec_file_exception = array();
	
		static private $ini_error_log;
		static private $ini_error_log_start_size;
	
		static public function output($type='stdout',$path=null){
			$error_report = null;
			switch($type){
				case 'xml':
					$source = self::result_xml('test_'.date('YmdHis'),self::error_report())->get('UTF-8');
					break;
				default:
					$source = self::result_str();
					$error_report = self::error_report();
			}
			if(!empty($path)){
				if(!empty($path)){
					$path = \testman\Util::path_absolute(getcwd(),$path);
					if(!is_dir(dirname($path))) mkdir(dirname($path),0777,true);
					if(is_file($path)) unlink($path);
				}
				file_put_contents($path,$source);
			}else{
				print($source);
				if(!empty($error_report)) self::error_print($error_report);
			}
		}
		/**
		 * 結果を取得する
		 * @return string{}
		 */
		static public function get(){
			return self::$result;
		}
		/**
		 * テスト結果をXMLで取得する
		 */
		static public function result_xml($name=null,$system_err=null){
			$xml = new \testman\Xml('testsuites');
			if(!empty($name)) $xml->attr('name',$name);
	
			$count = $success = $fail = $none = $exception = 0;
			foreach(self::get() as $file => $f){
				$case = new \testman\Xml('testsuite');
				$case->close_empty(false);
				$case->attr('name',substr(basename($file),0,-4));
				$case->attr('file',$file);
	
				foreach($f as $class => $c){
					foreach($c as $method => $m){
						foreach($m as $line => $r){
							foreach($r as $l){
								$info = array_shift($l);
								$name = (($method != '@' && $method != $file) ? $method : '');
								$name .= (empty($name) ? '' : '_').((!empty($info[1]) && $info[1] != $file) ? $info[1] : ((!empty($info[0]) && $info[0] != $file) ? $info[0] : ''));
								$count++;
								$x = new \testman\Xml('testcase');
								$x->attr('name',$line.(empty($name) ? '' : '_').str_replace('\\','',$name));
								$x->attr('class',$class);
								$x->attr('file',$file);
								$x->attr('line',$line);
								$x->attr('time',$info[2]);
	
								switch(sizeof($l)){
									case 0:
										$success++;
										$case->add($x);
										break;
									case 1:
										$none++;
										break;
									case 2:
										$fail++;
										$failure = new \testman\Xml('failure');
										$failure->attr('line',$line);
										ob_start();
										var_dump($l[1]);
										$failure->value('Line. '.$line.' '.$method.': '."\n".ob_get_clean());
										$x->add($failure);
										$case->add($x);
										break;
									case 4:
										$exception++;
										$error = new \testman\Xml('error');
										$error->attr('line',$line);
										$error->value(
												'Line. '.$line.' '.$method.': '.$l[0]."\n".
												$l[1]."\n\n".$l[2].':'.$l[3]
										);
										$x->add($error);
										$case->add($x);
										break;
								}
							}
						}
					}
				}
				$xml->add($case);
			}
			$xml->attr('failures',$fail);
			$xml->attr('tests',$count);
			$xml->attr('errors',$exception);
			$xml->attr('skipped',$none);
			$xml->attr('time',round((microtime(true) - (float)self::$start_time),4));
			$xml->add(new \testman\Xml('system-out'));
			$xml->add(new \testman\Xml('system-err',$system_err));
			return $xml;
		}
		static public function result_str(){
			$result = '';
			$tab = '  ';
			$success = $fail = $none = 0;
	
			foreach(self::$result as $file => $f){
				foreach($f as $class => $c){
					$print_head = false;
	
					foreach($c as $method => $m){
						foreach($m as $line => $r){
							foreach($r as $l){
								$info = array_shift($l);
								switch(sizeof($l)){
									case 0:
										$success++;
										break;
									case 1:
										$none++;
										break;
									case 2:
										$fail++;
										if(!$print_head){
											$result .= "\n";
											$result .= (empty($class) ? "*****" : str_replace("\\",'.',(substr($class,0,1) == "\\") ? substr($class,1) : $class))." [ ".$file." ]\n";
											$result .= str_repeat("-",80)."\n";
											$print_head = true;
										}
										$result .= "[".$line."]".$method.": ".self::fcolor("fail","1;31")."\n";
										$result .= $tab.str_repeat("=",70)."\n";
										ob_start();
										var_dump($l[0]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"33");
										ob_end_clean();
										$result .= "\n".$tab.str_repeat("=",70)."\n";
	
										ob_start();
										var_dump($l[1]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"31");
										ob_end_clean();
										$result .= "\n".$tab.str_repeat("=",70)."\n";
										break;
									case 4:
										$fail++;
										if(!$print_head){
											$result .= "\n";
											$result .= (empty($class) ? "*****" : str_replace("\\",'.',(substr($class,0,1) == "\\") ? substr($class,1) : $class))." [ ".$file." ]\n";
											$result .= str_repeat("-",80)."\n";
											$print_head = true;
										}
										$color = ($l[0] == 'exception' || $l[0] == 'fail') ? 31 : 34;
										$result .= "[".$line."]".$method.": ".self::fcolor($l[0],"1;".$color)."\n";
										$result .= $tab.str_repeat("=",70)."\n";
										$result .= self::fcolor($tab.$l[1]."\n\n".$tab.$l[2].":".$l[3],$color);
										$result .= "\n".$tab.str_repeat("=",70)."\n";
										break;
								}
							}
						}
					}
				}
			}
			$result .= "\n";
			$result .= self::fcolor(" success: ".$success." ","7;32")." ".self::fcolor(" fail: ".$fail." ","7;31")." ".self::fcolor(" none: ".$none." ","7;35")
			.sprintf(' ( %s sec / %s MByte) ',round((microtime(true) - (float)self::$start_time),4),round(number_format((memory_get_usage() / 1024 / 1024),3),2));
			$result .= "\n";
			return $result;
		}
		public function __toString(){
			return self::stdout();
		}
	
		/**
		 * @return integer
		 */
		static public function init($entry_dir=null,$test_dir=null,$lib_dir=null,$func_dir=null){
			$path_format = function($path,$op=''){
				$path = empty($path) ? (str_replace('\\','/',getcwd()).'/'.$op) : $path;
				$path = str_replace('\\','/',$path);
				if(substr($path,-1) !== '/') $path = $path.'/';
				return $path;
			};
			self::$start_time = microtime(true);
			self::$ini_error_log = ini_get('error_log');
			self::$ini_error_log_start_size = (empty($ini_error_log) || !is_file($ini_error_log)) ? 0 : filesize($ini_error_log);
	
			self::$entry_dir = $path_format((empty($entry_dir) ? getcwd() : $entry_dir));
			self::$test_dir = empty($test_dir) ? self::$entry_dir.'test/' : $path_format($test_dir);
			self::$lib_dir = empty($lib_dir) ? self::$entry_dir.'lib/' : $path_format($lib_dir);
			self::$func_dir = empty($func_dir) ? self::$entry_dir.'func/' : $path_format($func_dir);
	
			set_include_path(get_include_path().PATH_SEPARATOR.self::$lib_dir);
			if(is_dir(self::$func_dir)){
				foreach(new \RecursiveDirectoryIterator(
						self::$func_dir,
						\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
				) as $f){
					if(substr($f->getFilename(),-4) == '.php' &&
							strpos($f->getPathname(),'/.') === false &&
							strpos($f->getPathname(),'/_') === false
					){
						try{
							include_once($f->getPathname());
						}catch(Exception $e){
						}
					}
				}
			}
		}
		static public function info(){
			print(str_repeat(' ',2).'ENTRY_PATH:'.self::$entry_dir.PHP_EOL);
			print(str_repeat(' ',2).'LIB_PATH:'.self::$lib_dir.PHP_EOL);
			print(str_repeat(' ',2).'TEST_PATH:'.self::$test_dir.PHP_EOL);
			print(str_repeat(' ',2).'FUNC_PATH:'.self::$func_dir.PHP_EOL);
			print(str_repeat('-',80).PHP_EOL);
			print(str_repeat(' ',2).'INCLUDE_PATH:'.PHP_EOL);
			foreach(explode(PATH_SEPARATOR,get_include_path()) as $inc){
				print(str_repeat(' ',4).$inc.PHP_EOL);
			}
			print(str_repeat('-',80).PHP_EOL);
		}
		static public function error_report(){
			if(!empty($exceptions)){
				foreach($exceptions as $k => $e) self::error_print($k.': '.$e);
			}
			$ini_error_log_end_size = (empty($ini_error_log) || !is_file($ini_error_log)) ? 0 : filesize($ini_error_log);
			return ($ini_error_log_end_size != self::$ini_error_log_start_size) ? file_get_contents($ini_error_log,false,null,self::$ini_error_log_start_size) : null;
		}
		/**
		 * 現在実行中のエントリ
		 * @return string
		 */
		static public function current_entry(){
			return self::$current_entry;
		}
		/**
		 * 実行中のテスト名
		 */
		static public function current_name(){
			$dir = array(self::$entry_dir,self::$test_dir,self::$lib_dir);
			rsort($dir);
			$name = self::$current_file;
			foreach($dir as $f) $name = str_replace($f,'',$name);
			if(!empty(self::$current_class)) $name = $name.'@'.(self::$current_class);
			if(!empty(self::$current_method) && self::$current_method != '@') $name = $name.'#'.(self::$current_method);
			return $name;
		}
		static private function current_block_info(){
			return array(self::$current_block_name,self::$current_block_label,round((microtime(true) - (float)self::$current_block_start_time),4));
		}
		static private function expvar($var){
			if(is_numeric($var)) return strval($var);
			if(is_object($var)) $var = get_object_vars($var);
			if(is_array($var)){
				foreach($var as $key => $v){
					$var[$key] = self::expvar($v);
				}
			}
			return $var;
		}
		/**
		 * 判定を行う
		 * @param mixed $arg1 期待値
		 * @param mixed $arg2 実行結果
		 * @param boolean 真偽どちらで判定するか
		 * @param int $line 行番号
		 * @param string $file ファイル名
		 * @return boolean
		 */
		static public function equals($arg1,$arg2,$eq,$line,$file=null){
			$result = ($eq) ? (self::expvar($arg1) === self::expvar($arg2)) : (self::expvar($arg1) !== self::expvar($arg2));
			self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = ($result) ? array(self::current_block_info()) : array(self::current_block_info(),var_export($arg1,true),var_export($arg2,true));
			return $result;
		}
		/**
		 * メッセージを登録
		 * @param string $msg メッセージ
		 * @param int $line 行番号
		 * @param string $file ファイル名
		 */
		static public function notice($msg,$line,$file=null){
			self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = array(self::current_block_info(),'notice',$msg,$file,$line);
		}
		/**
		 * 失敗を登録
		 * @param string $msg メッセージ
		 * @param int $line 行番号
		 * @param string $file ファイル名
		 */
		static public function fail($line,$file=null){
			self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = array(self::current_block_info(),'fail','failure',$file,$line);
		}
		static private function dir_run($tests_path,$path,$print_progress,$include_tests){
			if(is_dir($f=Util::path_absolute($tests_path,str_replace('.','/',$path)))){
				foreach(new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator(
								$f,
								\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
						),
						\RecursiveIteratorIterator::SELF_FIRST
				) as $e){
					if($e->isFile() && substr($e->getFilename(),-4) == '.php' && strpos($e->getPathname(),'/.') === false && strpos($e->getPathname(),'/_') === false){
						self::run($e->getPathname(),null,null,$print_progress,$include_tests);
					}
				}
				return true;
			}
			return false;
		}
		/**
		 * テストを実行する
		 * @param string $class_name クラス名
		 * @param string $method メソッド名
		 * @param string $block_name ブロック名
		 * @param boolean $print_progress 実行中のブロック名を出力するか
		 * @param boolean $include_tests testsディレクトリも参照するか
		 */
		static private function run($class_name,$method_name=null,$block_name=null,$print_progress=false,$include_tests=false){
			list($entry_path,$tests_path) = array(self::$entry_dir,self::$test_dir);
			if($class_name == __FILE__) return new self();
			
			if(is_file($class_name)){
				$doctest = (strpos($class_name,$tests_path) === false) ? self::get_entry_doctest($class_name) : self::get_unittest($class_name);
			}else if(is_file($f=Util::path_absolute($entry_path,$class_name.'.php'))){
				$doctest = self::get_entry_doctest($f);
			}else if(is_file($f=Util::path_absolute($tests_path,str_replace('.','/',$class_name).'.php'))){
				$doctest = self::get_unittest($f);
			}else if(is_file($f=Util::path_absolute($tests_path,$class_name))){
				$doctest = self::get_unittest($f);
			}else if(class_exists($f=((substr($class_name,0,1) != "\\") ? "\\" : '').str_replace('.',"\\",$class_name),true)
					|| interface_exists($f,true)
					|| (function_exists('trait_exists') && trait_exists($f,true))
			){
				if(empty($method_name)) self::dir_run($tests_path,$class_name,$print_progress,$include_tests);
				$doctest = self::get_doctest($f);
			}else if(function_exists($f)){
				self::dir_run($tests_path,$class_name,$print_progress,$include_tests);
				$doctest = self::get_func_doctest($f);
			}else if(self::dir_run($tests_path,$class_name,$print_progress,$include_tests)){
				return new self();
			}else{
				throw new \ErrorException($class_name.' test not found');
			}
			self::$current_file = $doctest['filename'];
			self::$current_class = ($doctest['type'] == 1) ? $doctest['name'] : null;
			self::$current_entry = ($doctest['type'] == 2 || $doctest['type'] == 3) ? $doctest['name'] : null;
			self::$current_method = null;
	
			foreach($doctest['tests'] as $test_method_name => $tests){
				if($method_name === null || $method_name === $test_method_name){
					self::$current_method = $test_method_name;
	
					if(empty($tests['blocks'])){
						self::$result[self::$current_file][self::$current_class][self::$current_method][$tests['line']][] = array(self::current_block_info(),'none');
					}else{
						foreach($tests['blocks'] as $test_block){
							list($name,$label,$block) = $test_block;
							$exec_block_name = ' #'.(($class_name == $name) ? '' : $name);
							self::$current_block_name = $name;
							self::$current_block_label = $label;
							self::$current_block_start_time = microtime(true);
	
							if($block_name === null || $block_name === $name){
								if($print_progress && substr(PHP_OS,0,3) != 'WIN') self::stdout($exec_block_name);
								try{
									ob_start();
									if($doctest['type'] == 3){
										self::include_setup_teardown($doctest['filename'],'__setup__.php');
										include($doctest['filename']);
										self::include_setup_teardown($doctest['filename'],'__teardown__.php');
									}else{
										if(isset($doctest['tests']['@']['__setup__'])) eval($doctest['tests']['@']['__setup__'][2]);
										eval($block);
										if(isset($doctest['tests']['@']['__teardown__'])) eval($doctest['tests']['@']['__teardown__'][2]);
									}
									$result = ob_get_clean();
									if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)){
										$err = (preg_match('/syntax error.+code on line\s*(\d+)/',$result,$line) ?
												'Parse error: syntax error '.$doctest['filename'].' code on line '.$line[1]
												: $match[0]);
										throw new \ErrorException($err);
									}
								}catch(Exception $e){
									if(ob_get_level() > 0) $result = ob_get_clean();
									list($message,$file,$line) = array($e->getMessage(),$e->getFile(),$e->getLine());
									$trace = $e->getTrace();
									$eval = false;
	
									foreach($trace as $k => $t){
										if(isset($t['class']) && isset($t['function']) && ($t['class'].'::'.$t['function']) == __METHOD__ && isset($trace[$k-2])
												&& isset($trace[$k-1]['file']) && $trace[$k-1]['file'] == __FILE__ && isset($trace[$k-1]['function']) && $trace[$k-1]['function'] == 'eval'
										){
											$file = self::$current_file;
											$line = $trace[$k-2]['line'];
											$eval = true;
											break;
										}
									}
									if(!$eval && isset($trace[0]['file']) && self::$current_file == $trace[0]['file']){
										$file = $trace[0]['file'];
										$line = $trace[0]['line'];
									}
									self::$result[self::$current_file][self::$current_class][self::$current_method][$line][] = array(self::current_block_info(),'exception',$message,$file,$line);
								}
								if($print_progress && substr(PHP_OS,0,3) != 'WIN') self::stdout("\033[".strlen($exec_block_name).'D'."\033[0K");
							}
							self::$current_block_name = self::$current_block_label = null;
						}
					}
				}
			}
			if($include_tests && ($doctest['type'] == 1 || $doctest['type'] == 2)){
				$test_name = ($doctest['type'] == 1) ? str_replace("\\",'/',substr($doctest['name'],1)) : $doctest['name'];
				if(!empty($test_name) && is_dir($d=($tests_path.str_replace(array('.'),'/',$test_name)))){
					foreach(new \RecursiveDirectoryIterator($d,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS) as $e){
						if(substr($e->getFilename(),-4) == '.php' && strpos($e->getPathname(),'/.') === false && strpos($e->getPathname(),'/_') === false
								&& ($block_name === null || $block_name === substr($e->getFilename(),0,-4) || $block_name === $e->getFilename())
						){
							self::run($e->getPathname(),null,null,$print_progress,$include_tests);
						}
					}
				}
			}
			return new self();
		}
		static private function include_setup_teardown($test_file,$include_file){
			if(strpos($test_file,self::$test_dir) === 0){
				if(is_file(self::$test_dir.'__funcs__.php')) include_once(self::$test_dir.'__funcs__.php');
				$inc = array();
				$dir = dirname($test_file);
				while($dir.'/' != self::$test_dir){
					if(is_file($f=($dir.'/'.$include_file))) array_unshift($inc,$f);
					$dir = dirname($dir);
				}
				if(is_file($f=(self::$test_dir.$include_file))) array_unshift($inc,$f);
				foreach($inc as $i) include($i);
			}else if(is_file($f=(dirname($test_file).'/__setup__.php'))){
				include($f);
			}
		}
		static private function get_unittest($filename){
			$result = array();
			$result['@']['line'] = 0;
			$result['@']['blocks'][] = array($filename,null,$filename,0);
			$name = (preg_match("/^".preg_quote(self::$test_dir,'/')."(.+)\/[^\/]+\.php$/",$filename,$match)) ? $match[1] : null;
			return array('filename'=>$filename,'type'=>3,'name'=>$name,'tests'=>$result);
		}
		static private function get_entry_doctest($filename){
			$result = array();
			$entry = basename($filename,'.php');
			$src = file_get_contents($filename);
			if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$src,$doctests,PREG_OFFSET_CAPTURE)){
				foreach($doctests[0] as $doctest){
					if(isset($doctest[0][5]) && $doctest[0][5] != '*'){
						$test_start_line = sizeof(explode("\n",substr($src,0,$doctest[1]))) - 1;
						$test_block = str_repeat("\n",$test_start_line).preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array("/"."***","*"."/"),"",$doctest[0]));
						$test_block_name = preg_match("/^[\s]*#([^#].*)/",trim($test_block),$match) ? trim($match[1]) : null;
						$test_block_label = preg_match("/^[\s]*##(.+)/m",trim($test_block),$match) ? trim($match[1]) : null;
						if(trim($test_block) == '') $test_block = null;
						$result['@']['line'] = $test_start_line;
						$result['@']['blocks'][] = array($test_block_name,$test_block_label,$test_block,$test_start_line);
					}
				}
				self::merge_setup_teardown($result);
			}
			return array('filename'=>$filename,'type'=>2,'name'=>$entry,'tests'=>$result);
		}
		static private function get_func_doctest($func_name){
			$result = array();
			$r = new \ReflectionFunction($func_name);
			$filename = ($r->getFileName() === false) ? $func_name : $r->getFileName();
	
			if(is_string($r->getFileName())){
				$src_lines = file($filename);
				$func_src = implode('',array_slice($src_lines,$r->getStartLine()-1,$r->getEndLine()-$r->getStartLine(),true));
	
				if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$func_src,$doctests,PREG_OFFSET_CAPTURE)){
					foreach($doctests[0] as $doctest){
						if(isset($doctest[0][5]) && $doctest[0][5] != "*"){
							$test_start_line = $r->getStartLine() + substr_count(substr($func_src,0,$doctest[1]),"\n") - 1;
							$test_block = str_repeat("\n",$test_start_line).preg_replace("/([^\w_])self\(/ms","\\1".$func_name.'(',preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array("/"."***","*"."/"),"",$doctest[0])));
							$test_block_name = preg_match("/^[\s]*#([^#].*)/",trim($test_block),$match) ? trim($match[1]) : null;
							$test_block_label = preg_match("/^[\s]*##(.+)/m",trim($test_block),$match) ? trim($match[1]) : null;
							if(trim($test_block) == '') $test_block = null;
							$result[$func_name]['line'] = $r->getStartLine();
							$result[$func_name]['blocks'][] = array($test_block_name,$test_block_label,$test_block,$test_start_line);
						}
					}
				}else if($func_name[0] != '_'){
					$result[$func_name]['line'] = $r->getStartLine();
					$result[$func_name]['blocks'] = array();
				}
			}
			return array('filename'=>$filename,'type'=>4,'name'=>null,'tests'=>$result);
		}
		static private function get_doctest($class_name){
			$result = array();
			$rc = new \ReflectionClass($class_name);
			$filename = $rc->getFileName();
			$class_src_lines = file($filename);
			$class_src = implode('',$class_src_lines);
	
			foreach($rc->getMethods() as $method){
				if($method->getDeclaringClass()->getName() == $rc->getName()){
					$method_src = implode('',array_slice($class_src_lines,$method->getStartLine()-1,$method->getEndLine()-$method->getStartLine(),true));
					$result = array_merge($result,self::get_method_doctest($rc->getName(),$method->getName(),$method->getStartLine(),$method->isPublic(),$method_src));
					$class_src = str_replace($method_src,str_repeat("\n",sizeof(explode("\n",$method_src)) - 1),$class_src);
				}
			}
			$result = array_merge($result,self::get_method_doctest($rc->getName(),'@',1,false,$class_src));
			self::merge_setup_teardown($result);
			return array('filename'=>$filename,'type'=>1,'name'=>$rc->getName(),'tests'=>$result);
		}
		static private function merge_setup_teardown(&$result){
			if(isset($result['@']['blocks'])){
				foreach($result['@']['blocks'] as $k => $block){
					if($block[0] == '__setup__' || $block[0] == '__teardown__'){
						$result['@'][$block[0]] = array($result['@']['blocks'][$k][3],null,$result['@']['blocks'][$k][2]);
						unset($result['@']['blocks'][$k]);
					}
				}
			}
		}
		static private function get_method_doctest($class_name,$method_name,$method_start_line,$is_public,$method_src){
			$result = array();
			if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$method_src,$doctests,PREG_OFFSET_CAPTURE)){
				foreach($doctests[0] as $doctest){
					if(isset($doctest[0][5]) && $doctest[0][5] != "*"){
						$test_start_line = $method_start_line + substr_count(substr($method_src,0,$doctest[1]),"\n") - 1;
						$test_block = str_repeat("\n",$test_start_line).str_replace(array('self::','new self(','extends self{'),array($class_name.'::','new '.$class_name.'(','extends '.$class_name.'{'),preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."***","*"."/"),"",$doctest[0])));
						$test_block_name = preg_match("/^[\s]*#([^#].*)/",trim($test_block),$match) ? trim($match[1]) : null;
						$test_block_label = preg_match("/^[\s]*##(.+)/m",trim($test_block),$match) ? trim($match[1]) : null;
						if(trim($test_block) == '') $test_block = null;
						$result[$method_name]['line'] = $method_start_line;
						$result[$method_name]['blocks'][] = array($test_block_name,$test_block_label,$test_block,$test_start_line);
					}
				}
			}else if($is_public && $method_name[0] != '_'){
				$result[$method_name]['line'] = $method_start_line;
				$result[$method_name]['blocks'] = array();
			}
			return $result;
		}
		/**
		 * URL情報
		 * @return array
		 */
		static public function urls(){
			return (isset(self::$urls)) ? self::$urls : array();
		}
		/**
		 * URL情報を定義する
		 * @param array $urls
		 */
		static public function set_urls(array $urls){
			if(!isset(self::$urls)) self::$urls = $urls;
		}
	
	
		static private function fcolor($msg,$color='30'){
			return (php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg;
		}
	
		static public function verify_format($class_name,$m=null,$b=null,$include_tests=false){
			$f = ' '.$class_name.(isset($m) ? '::'.$m : '');
			self::stdout($f);
			$throw = null;
			$starttime = microtime(true);
			try{
				self::run($class_name,$m,$b,true,$include_tests);
			}catch(Exception $e){
				$throw = $e;
			}
			self::stdout('('.round((microtime(true) - (float)$starttime),4).' sec)'.PHP_EOL);
			if(isset($throw)) throw $throw;
			\testman\Coverage::save(true);
		}
		static public function error_print($msg,$color='1;31'){
			self::stdout(((php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg).PHP_EOL);
		}
		static public function run_lib($on_disp){
			if(is_dir(self::$lib_dir)){
				foreach(new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator(
								self::$lib_dir,
								\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
						),
						\RecursiveIteratorIterator::SELF_FIRST
				) as $f){
					if(
							ctype_upper(substr($f->getFilename(),0,1))
							&& substr($f->getFilename(),-4) == '.php'
							&& strpos($f->getPathname(),'/.') === false
							&& strpos($f->getPathname(),'/_') === false
							&& !in_array($f->getPathname(),self::$exec_file)
					){
						$class_file = str_replace(self::$lib_dir,'',substr($f->getPathname(),0,-4));
						if(preg_match("/^(.*)\/(\w+)\/(\w+)\.php$/",$f->getPathname(),$m) && $m[2] == $m[3]) $class_file = dirname($class_file);
						if(!preg_match('/[A-Z]/',dirname($class_file))){
							$class_name = "\\".str_replace('/',"\\",$class_file);
	
							try{
								self::verify_format($class_name,null,null,false,$on_disp);
								self::$exec_file[] = $f->getPathname();
							}catch(Exception $e){
								self::$exec_file_exception[$class_name] = $e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString();
							}
						}
					}
				}
			}
		}
		static public function run_entry($on_disp=false){
			if(is_dir(self::$entry_dir)){
				$pre = getcwd();
				chdir(self::$entry_dir);
				foreach(new \RecursiveDirectoryIterator(
						self::$entry_dir,
						\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
				) as $f){
					if(substr($f->getFilename(),-4) == '.php' &&
							strpos($f->getPathname(),'/.') === false &&
							strpos($f->getPathname(),'/_') === false
					){
						$src = file_get_contents($f->getFilename());
						try{
							self::verify_format($f->getPathname(),null,null,false,$on_disp);
							self::$exec_file[] = $f->getPathname();
						}catch(Exception $e){
							self::$exec_file_exception[$f->getFilename()] = $e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString();
						}
					}
				}
				chdir($pre);
			}
		}
		static public function run_test($on_disp){
			if(is_dir(self::$test_dir)){
				foreach(new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator(
								self::$test_dir,
								\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
						),
						\RecursiveIteratorIterator::SELF_FIRST
				) as $f){
					if($f->isFile() &&
							substr($f->getFilename(),-4) == '.php' &&
							strpos($f->getPathname(),'/.') === false &&
							strpos($f->getPathname(),'/_') === false
					){
						try{
							self::verify_format($f->getPathname(),null,null,false,$on_disp);
							self::$exec_file[] = $f->getPathname();
						}catch(Exception $e){
							$exceptions[$f->getFilename()] = implode('',$e->getTrace());
						}
					}
				}
			}
		}
		static public function run_func($on_disp){
			$funcs = get_defined_functions();
			foreach($funcs['user'] as $func_name){
				$r = new \ReflectionFunction($func_name);
				if(dirname($r->getFileName()) != __DIR__){
					self::verify_format($func_name,null,null,false,$on_disp);
				}
			}
		}
		static public function run_all($on_disp){
			self::run_func($on_disp);
			self::run_lib($on_disp);
			self::run_test($on_disp);
			self::run_entry($on_disp);
		}
		static public function stdout($v){
			print($v);
		}
	}	
}




// TODO global
namespace{
	ini_set('display_errors','On');
	ini_set('html_errors','Off');
	ini_set('xdebug.var_display_max_children',-1);
	ini_set('xdebug.var_display_max_data',-1);
	ini_set('xdebug.var_display_max_depth',-1);
	ini_set('error_reporting',E_ALL);
	
	if(ini_get('date.timezone') == ''){
		date_default_timezone_set('Asia/Tokyo');
	}
	if(extension_loaded('mbstring')){
		if('neutral' == mb_language()) mb_language('Japanese');
		mb_internal_encoding('UTF-8');
	}
	set_error_handler(function($n,$s,$f,$l){
		throw new \ErrorException($s,0,$n,$f,$l);
	});
	
	if(!function_exists('eq')){
		function r($obj){
			return $obj;
		}
		/**
		 *　等しい
		 * @param mixed $expectation 期待値
		 * @param mixed $result 実行結果
		 * @return boolean 期待通りか
		 */
		function eq($expectation,$result){
			list($debug) = debug_backtrace(false);
			return \testman\TestRunner::equals($expectation,$result,true,$debug["line"],$debug["file"]);
		}
		/**
		 * 等しくない
		 * @param mixed $expectation 期待値
		 * @param mixed $result 実行結果
		 * @return boolean 期待通りか
		 */
		function neq($expectation,$result){
			list($debug) = debug_backtrace(false);
			return \testman\TestRunner::equals($expectation,$result,false,$debug["line"],$debug["file"]);
		}
		/**
		 *　文字列中に指定した文字列がすべて存在していれば成功
		 * @param string $keyword スペース区切りで複数可能
		 * @param string $src
		 * @return boolean
		 */
		function meq($keyword,$src){
			list($debug) = debug_backtrace(false);
			foreach(explode(' ',$keyword) as $q){
				if(mb_strpos($src,$q) === false) return \testman\TestRunner::equals(true,false,true,$debug['line'],$debug['file']);
			}
			return \testman\TestRunner::equals(true,true,true,$debug['line'],$debug['file']);
		}
		/**
		 *　文字列中に指定した文字列がすべて存在していなければ成功
		 * @param string $keyword スペース区切りで複数可能
		 * @param string $src
		 * @return boolean
		 */
		function nmeq($keyword,$src){
			list($debug) = debug_backtrace(false);
			foreach(explode(' ',$keyword) as $q){
				if(mb_strpos($src,$q) !== false) return \testman\TestRunner::equals(true,false,true,$debug['line'],$debug['file']);
			}
			return \testman\TestRunner::equals(true,true,true,$debug['line'],$debug['file']);
		}
		/**
		 * 成功
		 */
		function success(){
			list($debug) = debug_backtrace(false);
			\testman\TestRunner::equals(true,true,true,$debug['line'],$debug['file']);
		}
		/**
		 * 失敗
		 */
		function fail($msg=null){
			list($debug) = debug_backtrace(false);
			\testman\TestRunner::fail($debug['line'],$debug['file']);
		}
		/**
		 * メッセージ
		 */
		function notice($msg=null){
			list($debug) = debug_backtrace(false);
			if(is_array($msg)){
				ob_start();
				var_dump($msg);
				$msg = ob_get_clean();
			}
			\testman\TestRunner::notice((($msg instanceof Exception) ? $msg->getMessage()."\n\n".$msg->getTraceAsString() : (string)$msg),$debug['line'],$debug['file']);
		}
		/**
		 * ユニークな名前でクラスを生成しインスタンスを返す
		 * @param string $class クラスのソース
		 * @return object
		 */
		function newclass($class){
			$class_name = '_';
			foreach(debug_backtrace() as $d) $class_name .= (empty($d['file'])) ? '' : '__'.basename($d['file']).'_'.$d['line'];
			$class_name = substr(preg_replace("/[^\w]/","",str_replace('.php','',$class_name)),0,100);
		
			for($i=0,$c=$class_name;;$i++,$c=$class_name.'_'.$i){
				if(!class_exists($c)){
					$args = func_get_args();
					array_shift($args);
					$doc = null;
					if(strpos($class,'-----') !== false){
						list($doc,$class) = preg_split("/----[-]+/",$class,2);
						$doc = "/**\n".trim($doc)."\n*/\n";
					}
					call_user_func(create_function('',$doc.vsprintf(preg_replace("/\*(\s+class\s)/","*/\\1",preg_replace("/class\s\*/",'class '.$c,trim($class))),$args)));
					return new $c;
				}
			}
		}
		/**
		 * ヒアドキュメントのようなテキストを生成する
		 * １行目のインデントに合わせてインデントが消去される
		 * @param string $text 対象の文字列
		 * @return string
		 */
		function pre($text){
			if(!empty($text)){
				$lines = explode("\n",$text);
				if(sizeof($lines) > 2){
					if(trim($lines[0]) == '') array_shift($lines);
					if(trim($lines[sizeof($lines)-1]) == '') array_pop($lines);
					return preg_match("/^([\040\t]+)/",$lines[0],$match) ? preg_replace("/^".$match[1]."/m","",implode("\n",$lines)) : implode("\n",$lines);
				}
			}
			return $text;
		}
		/**
		 * mapに定義されたurlをフォーマットして返す
		 * @param string $name
		 * @return string
		 */
		function test_map_url($map_name){
			$urls = \testman\TestRunner::urls();
			$args = func_get_args();
			array_shift($args);
		
			if(empty($urls)){
				if(strpos($map_name,'::') !== false) throw new \RuntimeException($map_name.' not found');
				return 'http://localhost/'.basename(getcwd()).'/'.$map_name.'.php';
			}else{
				$map_name = (strpos($map_name,'::') === false) ? (preg_replace('/^([^\/]+)\/.+$/','\\1',\testman\TestRunner::current_entry()).'::'.$map_name) : $map_name;
				if(isset($urls[$map_name]) && substr_count($urls[$map_name],'%s') == sizeof($args)) return vsprintf($urls[$map_name],$args);
				throw new \RuntimeException($map_name.(isset($urls[$map_name]) ? '['.sizeof($args).']' : '').' not found');
			}
		}	
		/**
		 * Httpリクエスト
		 * @return org.rhaco.net.Http
		 */
		function b($agent=null,$timeout=30,$redirect_max=20){
			$b = new \testman\Http($agent,$timeout,$redirect_max);
			return $b;
		}
		/**
		 * XMLで取得する
		 * @param $xml 取得したXmlオブジェクトを格納する変数
		 * @param $src 対象の文字列
		 * @param $name ノード名
		 * @return boolean
		 */
		function xml(&$xml,$src,$name=null){
			return \testman\Xml::set($xml,$src,$name);
		}
	}
	
	
	if(is_file($f=getcwd().'/bootstrap.php') || is_file($f=getcwd().'/vendor/autoload.php')){
		ob_start();
			include_once($f);
		ob_end_clean();		
	}
	
	spl_autoload_register(function($c){
		$cp = str_replace('\\','/',(($c[0] == '\\') ? substr($c,1) : $c));
		foreach(explode(PATH_SEPARATOR,get_include_path()) as $p){
			if(!empty($p) && ($r = realpath($p)) !== false && $p !== '.'){
	
				if(is_file($f=($r.'/'.$cp.'.php')) || is_file($f=($r.'/'.$cp.'/'.basename($cp).'.php'))){
					require_once($f);
					if(class_exists($c,false) || interface_exists($c,false)) return true;
				}
			}
		}
		return false;
	}
	,true,false);
	
	$argv = array_slice($_SERVER['argv'],1);
	$value = (empty($argv)) ? null : array_shift($argv);
	$params = array();
	
	if(substr($value,0,1) == '-'){
		array_unshift($argv,$value);
		$value = null;
	}
	for($i=0;$i<sizeof($argv);$i++){
		if($argv[$i][0] == '-'){
			$k = substr($argv[$i],1);
			$v = (isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : '';
			if(isset($params[$k]) && !is_array($params[$k])) $params[$k] = array($params[$k]);
			$params[$k] = (isset($params[$k])) ? array_merge($params[$k],array($v)) : $v;
		}
	}
	if(class_exists('Testman')){
		$i = new \ReflectionMethod('Testman','urls');
		if($i->isStatic()){
			$urls = call_user_func(array('Testman','urls'));
		}else{
			$ref = new ReflectionClass('Testman');
			$obj = $ref->newInstance();
			$urls = call_user_func(array($obj,'urls'));
		}
	}
	
	$entry_dir = $test_dir = $lib_dir = $func_dir = null;
	if(isset($params['entry_dir'])) $entry_dir = realpath($entry_dir);
	if(isset($params['test_dir'])) $test_dir = realpath($test_dir);
	if(isset($params['lib_dir'])) $lib_dir = realpath($lib_dir);
	if(isset($params['func_dir'])) $func_dir = realpath($func_dir);
	if(!isset($entry_dir)) $entry_dir = __DIR__;
	
	if(isset($params['report'])){
		if(!extension_loaded('xdebug')) die('xdebug extension not loaded');
		$db = $params['report'];
	
		if(empty($db)){
			$db = date('Ymd_His');
			if(!empty($value)) $db = $db.'-'.str_replace(array('\\','/'),'_',$value);
			if(isset($params['m'])) $db = $db.'-'.$params['m'];
			if(isset($params['b'])) $db = $db.'-'.$params['b'];
		}
		$db = \testman\Util::path_absolute($report_dir,$db.'.report');
		if(is_file($db)){
			if($has('f')){
				unlink($db);
			}else{
				die($db.': File exists'.PHP_EOL);
			}
		}
		if(!is_dir(dirname($db))) mkdir(dirname($db),0777,true);
		\testman\Coverage::start($db,$entry_path,$lib_path);
	}
	\testman\TestRunner::init($entry_dir,$test_dir,$lib_dir,$func_dir);
	\testman\TestRunner::set_urls($urls);
	\testman\TestRunner::info();
	\testman\TestRunner::run_all(true);
	\testman\TestRunner::output();
	

}


