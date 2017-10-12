<?php

namespace App\Presenters;

use Nette;
use App\Model\Orm\Orm;
use Nette\Application\UI\Form;

use App\Model\Orm\Group;

class GroupPresenter extends BasePresenter
{
    /** @var Orm @inject */
    public $orm;
    
    /** @var Group */
    private $group;
    
    public function actionEdit($groupId)
    {
        /*
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
        */
        $group = $this->orm->groups->getById($groupId);
        if (!$group) {
            $this->error('Skupina nebyla nalezena');
        }
        
        $this['groupForm']->setDefaults($group->toArray());
    }

    protected function createComponentGroupForm()
    {
        $form = new Form;
        $form->addText('title', 'Titulek:')
            ->setRequired();
    
        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = [$this, 'groupFormSucceeded'];
    
        return $form;
    }

    public function groupFormSucceeded($form, $values)
    {

        $groupId = $this->getParameter('groupId');

        if ($groupId) {
            $group = $this->orm->groups->getById($groupId);
            $group->title = $values->title;
            $this->flashMessage('Skupina byla úspěšně upravena.', 'success');
        } else {
            $group = new Group();
            $group->title = $values->title;
            $this->flashMessage('Skupina byla úspěšně založena.', 'success');
        }

        $this->orm->groups->persistAndFlush($group);

        $this->redirect('Homepage:');
    }

}