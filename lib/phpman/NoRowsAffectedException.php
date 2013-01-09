<?php
namespace phpman;
/**
 * @author tokushima
 */
class NoRowsAffectedException extends Exception{
	protected $message = 'no rows affected';
}
