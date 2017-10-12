<?php

namespace App\Model\Orm;

use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Repository\Repository;


/**
 * @method User|NULL getById($id)
 */
class UsersRepository extends Repository
{
	public function findUsers()
	{
		return $this->findBy(['deletedAt' => NULL])->orderBy('firstName', ICollection::ASC);
	}

	static function getEntityClassNames(): array
	{
		return [User::class];
	}
}
