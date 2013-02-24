<?php
namespace phpman;
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
 */
class Log{
	use \phpman\StaticModule;
	
	static private $level_strs = array('none','error','warn','info','debug');
	static private $logs = array();
	static private $id;
	static private $current_level;
	static private $disp;

	private $level;
	private $time;
	private $file;
	private $line;
	private $value;
	
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
	public function __construct($level,$value,$file=null,$line=null,$time=null){
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
	public function fm_value(){
		if(!is_string($this->value)){
			ob_start();
				var_dump($this->value);
			return ob_get_clean();
		}
		return $this->value;
	}
	public function fm_level(){
		return ($this->level >= 0) ? self::$level_strs[$this->level] : 'trace';
	}
	public function level(){
		return $this->level;
	}
	public function time($format='Y/m/d H:i:s'){
		return (empty($format)) ? $this->time : date($format,$this->time);
	}
	public function file(){
		return $this->file;
	}
	public function line(){
		return $this->line;
	}
	public function value(){
		return $this->value;
	}
	public function __toString(){
		return '['.$this->time.']'.'['.self::$id.']'.'['.$this->fm_level().']'.':['.$this->file.':'.$this->line.']'.' '.$this->fm_value();
	}
	/**
	 * 格納されたログを出力する
	 */
	final static public function flush(){
		if(!empty(self::$logs)){
			foreach(self::$logs as $log){
				if(self::cur_level() >= $log->level()){
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
		 */
		self::module('flush',self::$logs,self::$id);
		/**
		 * フラッシュの後処理
		 * @param string $id
		 */
		self::module('after_flush',self::$id);
		self::$logs = array();
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
}