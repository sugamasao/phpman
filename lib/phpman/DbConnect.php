<?php
namespace phpman;

class DbConnect{
	static public function type(){
		return get_called_class();
	}
	public function connect($dbname,$host,$port,$user,$password,$sock){
		if(!extension_loaded('pdo_sqlite')) throw new \RuntimeException('pdo_sqlite not supported');
		if(empty($host) && empty($dbname)) throw new \InvalidArgumentException('undef connection name');
		$con = null;

		if(empty($host)) $host = getcwd();
		if($host != ':memory:'){
			$host = str_replace('\\','/',$host);
			if(substr($host,-1) != '/') $host = $host.'/';
		}
		try{
			$con = new \PDO(sprintf('sqlite:%s',($host == ':memory:') ? ':memory:' : $host.$dbname));
		}catch(\PDOException $e){
			throw new ConnectionException($e->getMessage());
		}
		return $con;
	}
}

