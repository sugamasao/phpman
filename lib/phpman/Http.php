<?php
namespace phpman;

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
				$cookie_domain = substr(\phpman\Util::path_absolute('http://'.$cookie_domain,$cookie_path),7);
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
						return $this->request('GET',\phpman\Util::path_absolute($url,$redirect_url[1]),$download_path);
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
