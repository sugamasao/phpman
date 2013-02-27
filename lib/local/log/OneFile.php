<?php
namespace local\log;
/**
 * ファイルにログを出力するLogモジュール
 * @author tokushima
 * @conf string $path ログファイルを保存するファイルパス
 */
class OneFile{
	private $path;

	public function __construct($path=null){
		$this->path = (empty($path)) ? \phpman\Conf::get('path') : $path;
		if(!empty($this->path)){
			$dir = dirname($this->path);
			\phpman\Util::mkdir($dir,0777);
		}
	}
	public function debug(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	public function info(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	public function warn(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	public function error(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	public function trace(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
}
