<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Messages'), 'message_reading.php')
    ->add(schoolCommunityT('Send Message'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$gibbonPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$categories = schoolCommunityCategoryOptions($pdo, $roleCategory);
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';

if (empty($categories)) {
    $page->addError(schoolCommunityT('No message categories are available for your role. Contact an administrator.'));
    if (isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
        $actionForm = Form::createBlank('schoolCommunityNoCategories');
        $actionForm->addHeaderAction('categories', schoolCommunityT('Manage Message Categories'))
            ->setURL('/modules/School Community/message_categorySetting.php')
            ->setIcon('settings')
            ->displayLabel();
        $page->write($actionForm->getOutput());
    }
    return;
}
$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;

if (empty($category) || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
    $form = Form::create('schoolCommunityCategoryChoose', $session->get('absoluteURL').'/index.php', 'get');
    $form->addHiddenValue('q', '/modules/School Community/message_sending.php');
    $form->setTitle(schoolCommunityT('Choose Category'));
    $form->setDescription(schoolCommunityT('Select a category first so only eligible recipients are shown.'));

    $row = $form->addRow();
        $row->addLabel('categoryID', schoolCommunityT('Category'));
        $row->addSelect('categoryID')->fromArray($categories)->required()->placeholder();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Continue'));

    echo $form->getOutput();
    return;
}

$people = schoolCommunityPersonOptions($pdo, $gibbonPersonID, (int) $category['accessControl']);
if (empty($people)) {
    $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
    return;
}

$form = Form::create('schoolCommunityMessage', $session->get('absoluteURL').'/modules/School Community/message_sendingProcess.php', 'post');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('categoryID', $categoryID);
$form->setTitle(schoolCommunityT('Send Message'));

$row = $form->addRow();
    $row->addLabel('title', schoolCommunityT('Subject'));
    $row->addTextField('title')->required()->maxLength(100);

$row = $form->addRow();
    $row->addLabel('categoryID', schoolCommunityT('Category'));
    $row->addTextField('categoryName')->readonly()->setValue($category['categoryName']);

$row = $form->addRow();
    $row->addLabel('priority', schoolCommunityT('Priority'));
    $row->addSelect('priority')->fromArray(schoolCommunityPriorityOptions())->required()->selected('m');

$row = $form->addRow();
    $row->addLabel('recipientID', schoolCommunityT('Recipient'));
    $row->addSelectPerson('recipientID')->fromArray($people)->required()->placeholder();

$row = $form->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $column->addEditor('body', $guid)->setRows(12)->showMedia()->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Send Message'));

echo $form->getOutput();
