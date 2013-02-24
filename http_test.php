<?php
include('bootstrap.php');

$http = new \phpman\Http();

$http->file_vars('file',__FILE__);
$http->do_post('http://localhost/phpman/test_index/request?aaa=11&bb=2&z[1]=a&z[2]=b');
var_dump($http->status());
var_dump($http->url());
var_dump($http->body());



