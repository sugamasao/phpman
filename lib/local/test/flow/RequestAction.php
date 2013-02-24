<?php
namespace local\test\flow;

class RequestAction{
	public function index(){
		$req = new \phpman\Request();
		$req->vars('get_file',$req->in_files('file'));
		$req->vars('is_file',$_FILES);
		return $req->ar_vars();
	}
}