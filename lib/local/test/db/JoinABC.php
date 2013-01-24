<?php
namespace local\test\db;
/**
 * JoinA, JoinB, JoinCテーブルが先に必要
 * @class @['table'=>'join_a']
 * @var serial $id
 * @var string $name @['column'=>'name','cond'=>'id(join_c.a_id.b_id,join_b.id)']
 */
class JoinABC extends \phpman\Dao{
	protected $id;
	protected $name;
}
