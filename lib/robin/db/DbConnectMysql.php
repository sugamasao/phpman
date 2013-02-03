<?php
namespace robin\db;
/**
 * Mysqlモジュール
 * @author tokushima
 */
class DbConnectMysql extends \phpman\DbConnect{
	protected $order_random_str = 'rand()';
	
	/**
	 * @param string $name
	 * @param string $host
	 * @param number $port
	 * @param string $user
	 * @param string $password
	 * @param string $sock
	 */
	public function connect($name,$host,$port,$user,$password,$sock){
		if(!extension_loaded('pdo_mysql')) throw new \phpman\RuntimeException('pdo_mysql not supported');
		$con = null;
		if(empty($host)) $host = 'localhost';
		if(empty($name)) throw new \phpman\InvalidArgumentException('undef connection name');
		$dsn = empty($sock) ?
					sprintf('mysql:dbname=%s;host=%s;port=%d',$name,$host,((empty($port) ? 3306 : $port))) :
					sprintf('mysql:dbname=%s;unix_socket=%s',$name,$sock);
		try{
			$con = new \PDO($dsn,$user,$password);
			if(!empty($this->encode)) $this->prepare_execute($con,'set names '.$this->encode);
			$this->prepare_execute($con,'set autocommit=0');
			$this->prepare_execute($con,'set session transaction isolation level read committed');
		}catch(\PDOException $e){
			throw new \phpman\ConnectionException((strpos($e->getMessage(),'SQLSTATE[HY000]') === false) ? $e->getMessage() : 'not supported '.__CLASS__);
		}
		return $con;
	}
	private function prepare_execute($con,$sql){
		$st = $con->prepare($sql);
		$st->execute();
		$error = $st->errorInfo();
		if((int)$error[0] !== 0) throw new \phpman\InvalidArgumentException($error[2]);
	}
	public function last_insert_id_sql(){
		return Daq::get('select last_insert_id() as last_insert_id;');
	}
	/**
	 * create table
	 */
	public function create_table_sql(\phpman\Dao $dao){
		$quote = function($name){
			return '`'.$name.'`';
		};
		$to_column_type = function($dao,$type,$name) use($quote){
			switch($type){
				case '':
				case 'mixed':
				case 'string':
					return $quote($name).' varchar('.$dao->prop_anon($name,'max',255).')';
				case 'alnum':
				case 'text':
					return $quote($name).(($dao->prop_anon($name,'max') !== null) ? ' varchar('.$dao->prop_anon($name,'max').')' : ' text');
				case 'number':
					return $quote($name).' '.(($dao->prop_anon($name,'decimal_places') !== null) ? sprintf('numeric(%d,%d)',26-$dao->prop_anon($name,'decimal_places'),$dao->prop_anon($name,'decimal_places')) : 'double');
				case 'serial': return $quote($name).' int auto_increment';
				case 'boolean': return $quote($name).' int(1)';
				case 'timestamp': return $quote($name).' timestamp';
				case 'date': return $quote($name).' date';
				case 'time': return $quote($name).' int';
				case 'intdate': 
				case 'integer': return $quote($name).' int';
				case 'email': return $quote($name).' varchar(255)';
				case 'choice': return $quote($name).' varchar(255)';
				default: throw new exception\InvalidArgumentException('undefined type `'.$type.'`');
			}
		};
		$columndef = $primary = array();
		$sql = 'create table '.$quote($dao->table()).'('.PHP_EOL;
		foreach($dao->props() as $prop_name => $v){
			if($this->create_table_prop_cond($dao,$prop_name)){
				$column_str = '  '.$to_column_type($dao,$dao->prop_anon($prop_name,'type'),$prop_name);
				$column_str .= (($dao->prop_anon($prop_name,'require') === true) ? ' not' : '').' null ';
				
				$columndef[] = $column_str;
				if($dao->prop_anon($prop_name,'primary') === true || $dao->prop_anon($prop_name,'type') == 'serial') $primary[] = $quote($prop_name);
			}
		}
		$sql .= implode(','.PHP_EOL,$columndef).PHP_EOL;
		if(!empty($primary)) $sql .= ' ,primary key ( '.implode(',',$primary).' ) '.PHP_EOL;
		$sql .= ' ) engine = InnoDB character set utf8 collate utf8_general_ci;'.PHP_EOL;
		return $sql;
	}
	public function exists_table_sql(\phpman\Dao $dao){
		return sprintf('select count(*) from information_schema.tables where table_name = \'%s\'',$dai->table());
	}
}