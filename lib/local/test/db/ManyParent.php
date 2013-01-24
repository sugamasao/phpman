<?php
namespace local\test\db;
/**
 * @var serial $id
 * @var string $value
 * @var ManyChild[] $children @['cond'=>'id()parent_id']
 */
class ManyParent extends \phpman\Dao{
	protected $id;
	protected $value;
	protected $children;
}
