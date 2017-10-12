<?php

namespace App\Model\Orm;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @method Group|NULL getById($id)
 */
class GroupsRepository extends Repository
{
	public function findGroups()
	{
		return $this->findBy(['deletedAt' => NULL])->orderBy('title', ICollection::ASC);
	}


	static function getEntityClassNames(): array
	{
		return [Group::class];
	}
}
