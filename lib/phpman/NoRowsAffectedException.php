<?php
namespace phpman;
/**
 * @author tokushima
 */
class NoRowsAffectedException extends \phpman\Exception{
	protected $message = 'no rows affected';
}
