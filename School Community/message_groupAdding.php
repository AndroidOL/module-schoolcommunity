<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Groups'), 'message_groups.php')
    ->add(schoolCommunityT('Create Group'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php')
    || !isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$categories = schoolCommunityCategoryOptions($pdo, $roleCategory);
if (empty($categories)) {
    $page->addError(schoolCommunityT('No message categories are available for your role. Contact an administrator.'));
    return;
}

$form = Form::create('schoolCommunityGroupAdd', $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php', 'post');
$form->addHiddenValue('action', 'create');
$form->setTitle(schoolCommunityT('Create Group'));
$form->setDescription(schoolCommunityT('Choose a category carefully: every member and every group message must match its audience permissions.'));

$row = $form->addRow();
    $row->addLabel('groupName', schoolCommunityT('Group Name'));
    $row->addTextField('groupName')->required()->maxLength(100);

$row = $form->addRow();
    $row->addLabel('categoryID', schoolCommunityT('Category'));
    $row->addSelect('categoryID')->fromArray($categories)->required()->placeholder();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Create Group'));

echo $form->getOutput();
