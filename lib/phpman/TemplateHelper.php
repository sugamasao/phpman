<?php
namespace phpman;

class TemplateHelper{
	final public function htmlencode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,'UTF-8',mb_detect_encoding($value));
			return htmlentities($value,ENT_QUOTES,'UTF-8');
		}
		return $value;
	}
}
