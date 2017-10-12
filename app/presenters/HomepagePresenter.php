<?php

namespace App\Presenters;

use Nette;
use App\Model\Orm\Orm;

class HomepagePresenter extends BasePresenter
{
    /** @var Orm @inject */
    public $orm;
    
    /** @var Group */
    private $group;

    /** @var User */
    private $user;
    
    public function renderDefault()
    {
        $this->template->groups = $this->orm->groups->findGroups();
        $this->template->users = $this->orm->users->findUsers();


/*
dump($this->template->groups);
exit;
*/
    }

    public function handleDeleteGroup($groupId)
	{
		$group = $this->orm->groups->getById($groupId);
		if (!$group) {
			$this->error();
		}

		$group->deletedAt = 'now';
        $this->orm->groups->persistAndFlush($group);
        
        $this->flashMessage('Skupina byla úspěšně smazána.', 'success');
        $this->redirect('this');
	}
}