<?php
namespace phpman;

class Http{
	private $resource;
	private $agent;
	private $timeout = 30;
	private $status_redirect = true;
	
	private $request_header = array();
	private $request_vars = array();
	private $request_file_vars = array();
	private $head;
	private $body;
	private $url;
	
	public function __construct($agent=null,$timeout=30,$status_redirect=true){
		$this->agent = $agent;
		$this->timeout = (int)$timeout;
		$this->status_redirect = (boolean)$status_redirect;
		
		$this->resource = curl_init();	
	}
	public function __toString(){
		return $this->body;
	}
	public function header($key,$value=null){
		$this->request_header[$key] = $value;
	}
	public function vars($key,$value=null){
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
		return $this->body;
	}
	public function url(){
		return $this->url;
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
		$fp = fopen($download_path,'w');
			curl_setopt($this->resource,CURLOPT_FILE,$fp);
			$this->request('GET',$url);
		fclose($fp);
		return $this;
	}
	public function do_post_download($url,$download_path){
		$fp = fopen($download_path,'w');
		curl_setopt($this->resource,CURLOPT_FILE,$fp);
		$this->request('POST',$url);
		fclose($fp);
		return $this;
	}
	public function status(){
		return curl_getinfo($this->resource,CURLINFO_HTTP_CODE);
	}
	private function request($method,$url){
		$url_info = parse_url($url);
		if(isset($url_info['query'])){
			parse_str($url_info['query'],$vars);
			foreach($vars as $k => $v){
				if(!isset($this->request_vars[$k])) $this->request_vars[$k] = $v;
			}
			list($url) = explode('?',$url);
		}
		$this->url = $url;
		if(!isset($this->request_header['Expect'])){
			$this->request_header['Expect'] = null;
		}
		if($this->status_redirect){
			curl_setopt($this->resource,CURLOPT_FOLLOWLOCATION,true);
			curl_setopt($this->resource,CURLOPT_AUTOREFERER,true);
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
						list($k,$v) = explode('=',$q,2);
						if(substr($k,-3) == '%5D') $k = str_replace(array('%5B','%5D'),array('[',']'),$k);
						$vars[$k] = $v;
					}
				}
				if(!empty($this->request_file_vars)){
					foreach(explode('&',http_build_query($this->request_file_vars)) as $q){
						list($k,$v) = explode('=',$q,2);
						if(substr($k,-3) == '%5D') $k = str_replace(array('%5B','%5D'),array('[',']'),$k);
						$vars[$k] = '@'.urldecode($v);
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
		curl_setopt($this->resource,CURLOPT_RETURNTRANSFER,true);		
		curl_setopt($this->resource,CURLOPT_HEADER,true);
		curl_setopt($this->resource,CURLOPT_FORBID_REUSE,true);
		curl_setopt($this->resource,CURLOPT_HTTPHEADER,
			array_map(function($k,$v){
				return $k.': '.$v;
			}
			,array_keys($this->request_header),$this->request_header)
		);
		curl_setopt($this->resource,CURLOPT_USERAGENT,
			(empty($this->agent) ? 
					(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) : 
					$this->agent
			)
		);
		curl_setopt($this->resource,CURLOPT_TIMEOUT,$this->timeout);
		//curl_setopt($this->resource,CURLOPT_SSL_VERIFYPEER,false); // サーバー証明書の検証をしない
		
		$rtn = curl_exec($this->resource);
		if($rtn === false) throw new \RuntimeException();
		list($this->head,$this->body) = explode("\r\n\r\n",$rtn);

		$this->request_header = array();
		$this->request_vars = array();

		return $this;
	}
	public function __destruct(){
		curl_close($this->resource);
	}
	private function info(){
		return curl_getinfo($this->resource);
	}
}