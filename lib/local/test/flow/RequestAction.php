<?php
namespace local\test\flow;

class RequestAction{
	public function index(){
		$req = new \phpman\Request();
		$req->vars('get_file',$req->in_files('file'));
		$req->vars('set_cookie',$req->in_vars('set_cookie') + 1);
		$req->write_cookie('set_cookie');
		return $req->ar_vars();
	}
	public function redirect(){
		header('Location: http://localhost/phpman/test_index/request');
		exit;
	}
}