<?php
namespace local\test\db;
/**
 * Findが先に必要
 * @var serial $id
 * @var integer $parent_id
 * @var string $value @['cond'=>'parent_id(find.id)','column'=>'value1']
 */
class RefFind extends \phpman\Dao{
	protected $id;
	protected $parent_id;
	protected $value;
}
