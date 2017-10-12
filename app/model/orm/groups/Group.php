<?php

namespace App\Model\Orm;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;

//use Nextras\Orm\Collection\ICollection;
//use Nextras\Orm\Relationships\ManyHasMany;
//use Nextras\Orm\Relationships\OneHasMany;


/**
 * Group
 *
 * @property int                        $id          {primary}
 * @property string                     $title
 * @property DateTimeImmutable          $createdAt   {default now}
 * @property DateTimeImmutable|NULL		$deletedAt
 */
class Group extends Entity
{

}
