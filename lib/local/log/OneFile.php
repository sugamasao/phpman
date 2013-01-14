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
	/**
	 * @module org.rhaco.Log
	 * @param \org\org.rhaco.Log\Log $log
	 * @param string $id
	 */
	public function debug(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	/**
	 * @module org.rhaco.Log
	 * @param \org\org.rhaco.Log\Log $log
	 * @param string $id
	 */
	public function info(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	/**
	 * @module org.rhaco.Log
	 * @param \org\org.rhaco.Log\Log $log
	 * @param string $id
	 */
	public function warn(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	/**
	 * @module org.rhaco.Log
	 * @param \org\org.rhaco.Log\Log $log
	 * @param string $id
	 */
	public function error(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
	/**
	 * @module org.rhaco.Log
	 * @param \org\org.rhaco.Log\Log $log
	 * @param string $id
	 */
	public function trace(\phpman\Log $log,$id){
		file_put_contents($this->path,((string)$log).PHP_EOL,FILE_APPEND);
	}
}
