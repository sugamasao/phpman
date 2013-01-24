<?php
namespace local\test\db;
/**
 *  RefFindテーブルが先に必要
 * @class @['table'=>'ref_find']
 * @var serial $id
 * @var integer $parent_id
 * @var Find $parent @['cond'=>'parent_id()id']
 */
class HasFind extends \phpman\Dao{
	protected $id;
	protected $parent_id;
	protected $parent;
}
