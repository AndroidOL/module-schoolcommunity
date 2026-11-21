<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Categories'), 'message_categorySetting.php')
    ->add(schoolCommunityT('Add Category'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$form = Form::create('schoolCommunityCategoryAdd', $session->get('absoluteURL').'/modules/School Community/message_categoryProcess.php', 'post');
$form->addHiddenValue('action', 'add');
$form->setTitle(schoolCommunityT('Add Category'));

$row = $form->addRow();
    $row->addLabel('categoryName', schoolCommunityT('Name'));
    $row->addTextField('categoryName')->required()->maxLength(100);

$row = $form->addRow();
    $row->addLabel('groupMessages', schoolCommunityT('Group Messages'))
        ->description(schoolCommunityT('Allow group messaging in this category.'));
    $row->addYesNo('groupMessages')->required()->selected('Y');

foreach (['staff' => 'Staff', 'student' => 'Student', 'parent' => 'Parent', 'other' => 'Other'] as $key => $label) {
    $row = $form->addRow();
        $row->addLabel($key, schoolCommunityT($label).' '.schoolCommunityT('Visible'));
        $row->addYesNo($key)->required()->selected('N');
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Add Category'));

$page->write($form->getOutput());
