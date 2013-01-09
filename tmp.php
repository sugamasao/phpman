<?php
include('bootstrap.php');


$db = new \phpman\Db();


$db->query('create table abc(aaa TEXT)');
$db->commit();

$db->query("insert into abc(aaa) values('hoge')");

$db->query("select * from abc");

foreach($db->next_result() as $result){
	var_dump($result);
}

