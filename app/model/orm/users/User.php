<?php

namespace App\Model\Orm;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;

//use Nextras\Orm\Collection\ICollection;
//use Nextras\Orm\Relationships\ManyHasMany;
//use Nextras\Orm\Relationships\OneHasMany;


/**
 * User
 *
 * @property int                        $id          {primary}
 * @property string                     $email
 * @property string                     $password
 * @property string                     $firstName
 * @property string|NULL                $lastName
 * @property string|NULL                $bankAccount
 * @property DateTimeImmutable          $createdAt   {default now}
 * @property DateTimeImmutable|NULL     $deletedAt
 */
class User extends Entity
{

}
