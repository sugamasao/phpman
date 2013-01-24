<?php
namespace local\test\db;
use \phpman\Q;

class AbcFind extends Find{
	protected function __find_conds__(){
		return Q::b(Q::eq('value1','abc'));
	}
}
