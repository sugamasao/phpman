<?php
namespace local\test\db;
/**
 * @var serial $id
 * @var integer $u1 @['unique_together'=>'u2']
 * @var integer $u2
 */
class UniqueVerify extends \phpman\Dao{
	protected $id;
	protected $u1;
	protected $u2;
}