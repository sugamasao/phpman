<?php
namespace local\test\flow;

class RequestAction{
	public function index(){
//		var_dump($_POST);
		$req = new \phpman\Request();
		return $req->ar_vars();
	}
}