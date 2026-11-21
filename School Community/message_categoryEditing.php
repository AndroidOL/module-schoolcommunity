<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Categories'), 'message_categorySetting.php')
    ->add(schoolCommunityT('Edit Category'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$categoryID = is_scalar($_GET['CategoryID'] ?? null) ? trim((string) $_GET['CategoryID']) : '';
$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl, groupMessages
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;

if (empty($category)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$category['staff'] = ((int) $category['accessControl'] & 8) ? 'Y' : 'N';
$category['student'] = ((int) $category['accessControl'] & 4) ? 'Y' : 'N';
$category['parent'] = ((int) $category['accessControl'] & 2) ? 'Y' : 'N';
$category['other'] = ((int) $category['accessControl'] & 1) ? 'Y' : 'N';

$form = Form::create('schoolCommunityCategoryEdit', $session->get('absoluteURL').'/modules/School Community/message_categoryProcess.php', 'post');
$form->addHiddenValue('action', 'edit');
$form->addHiddenValue('categoryID', $categoryID);
$form->setTitle(schoolCommunityT('Edit Category'));

$row = $form->addRow();
    $row->addLabel('categoryName', schoolCommunityT('Name'));
    $row->addTextField('categoryName')->required()->maxLength(100)->setValue($category['categoryName']);

$row = $form->addRow();
    $row->addLabel('groupMessages', schoolCommunityT('Group Messages'))
        ->description(schoolCommunityT('Allow group messaging in this category.'));
    $row->addYesNo('groupMessages')->required()->selected($category['groupMessages'] === 'Y' ? 'Y' : 'N');

foreach (['staff' => 'Staff', 'student' => 'Student', 'parent' => 'Parent', 'other' => 'Other'] as $key => $label) {
    $row = $form->addRow();
        $row->addLabel($key, schoolCommunityT($label).' '.schoolCommunityT('Visible'));
        $row->addYesNo($key)->required()->selected($category[$key]);
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Save Changes'));

echo $form->getOutput();
