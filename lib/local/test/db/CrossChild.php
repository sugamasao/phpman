<?php
namespace local\test\db;
/**
 * @var serial $id
 * @var integer $parent_id
 * @var CrossParent $parent @['cond'=>'parent_id()id']
 */
class CrossChild extends \phpman\Dao{
	protected $id;
	protected $parent_id;
	protected $parent;
}
