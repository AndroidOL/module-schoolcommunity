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

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_groupManage.php')
    && !schoolCommunityMessagingAccessAllowed(
        isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
        isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
    )
) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$categories = schoolCommunityCategoryOptions($pdo, $roleCategory, false, true);
if (empty($categories)) {
    $page->addError(schoolCommunityT('No group-enabled message categories are available for your role. Contact an administrator.'));
    return;
}

$people = schoolCommunityPersonOptions($pdo, $currentPersonID, null, false);
if (empty($people)) {
    $page->addError(schoolCommunityT('No eligible people are available to add.'));
    return;
}

$form = Form::create(
    'schoolCommunityGroupAdd',
    $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php',
    'post',
    'standardForm disable-warnings'
);
$form->addHiddenValue('action', 'create');
$form->setTitle(schoolCommunityT('Create Group'));
$form->setDescription(schoolCommunityT('Give the group a name and choose the people who belong to it. Members can be edited afterwards.'));

$row = $form->addRow();
    $row->addLabel('groupName', schoolCommunityT('Group Name'));
    $row->addTextField('groupName')->required()->maxLength(100);

$row = $form->addRow();
    $row->addLabel('memberSearch', schoolCommunityT('Search'));
    $row->addTextField('memberSearch')
        ->setID('scGroupMemberSearch')
        ->setAttribute('autocomplete', 'off')
        ->setAttribute('placeholder', schoolCommunityT('Search by name'));

$row = $form->addRow();
    $row->addLabel('memberIDs', schoolCommunityT('Members'))->description(schoolCommunityT('Select the people who should be in this group.'));
    $row->addCheckbox('memberIDs')
        ->fromArray($people)
        ->alignLeft()
        ->required()
        ->setLabelClass('text-gray-800 py-2 px-1 rounded cursor-pointer select-none hover:bg-gray-100')
        ->setOuterClass('w-full max-h-80 overflow-y-auto rounded-md border border-gray-200 bg-white p-2');

$row = $form->addRow();
    $row->addContent(
        '<div class="text-sm text-gray-700">'
        .'<span class="font-semibold">'.schoolCommunityT('Selected').': <span id="scGroupSelectedCount">0</span></span>'
        .'<div id="scGroupSelectedNames" class="mt-1 text-xs text-gray-600"></div>'
        .'</div>'
    );

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Create Group'));

$page->write($form->getOutput());
