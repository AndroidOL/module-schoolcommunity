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

$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$currentPersonID = (string) $session->get('gibbonPersonID');
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
$selectedCategoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$categories = schoolCommunityCategoryOptions($pdo, $roleCategory, false, true);
if (empty($categories)) {
    $page->addError(schoolCommunityT('No group-enabled message categories are available for your role. Contact an administrator.'));
    return;
}

$sourceOptions = ['custom' => schoolCommunityT('Custom Group')]
    + schoolCommunityLinkedSourceOptions($pdo, $currentPersonID, $gibbonSchoolYearID);

$form = Form::create(
    'schoolCommunityGroupAdd',
    $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php',
    'post',
    'standardForm disable-warnings'
);
$form->addHiddenValue('action', 'create');
$form->setTitle(schoolCommunityT('Create Group'));
$form->setDescription(schoolCommunityT('Choose a category carefully. Administrative and course classes automatically use the current school roster for future messages.'));

$row = $form->addRow();
    $row->addLabel('linkedSource', schoolCommunityT('Group Source'))->description(schoolCommunityT('Teachers can select only their own administrative and course classes.'));
    $row->addSelect('linkedSource')->fromArray($sourceOptions)->required()->selected('custom');

$row = $form->addRow();
    $row->addLabel('groupName', schoolCommunityT('Group Name'))->description(schoolCommunityT('Required for custom groups; class groups use the class name when left blank.'));
    $row->addTextField('groupName')->maxLength(100);

$row = $form->addRow();
    $row->addLabel('categoryID', schoolCommunityT('Category'));
    $row->addSelect('categoryID')->fromArray($categories)->required()->placeholder()->selected($selectedCategoryID);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Create Group'));

$page->write($form->getOutput());
