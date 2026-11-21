<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Tables\Action;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Messages'), 'message_reading.php')
    ->add(schoolCommunityT('Send Message'));

if (!schoolCommunityMessagingAccessAllowed(
    isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
    isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
)) {
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
            ->setIcon('config')
            ->displayLabel();
        $page->write($actionForm->getOutput());
    }
    return;
}
$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl, groupMessages
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

$groupMessagesEnabled = schoolCommunityGroupMessagesAllowed($category);
$requestedGroupMode = ($_GET['groupMessage'] ?? 'N') === 'Y';
$groupMode = $groupMessagesEnabled && $requestedGroupMode;
if ($requestedGroupMode && !$groupMessagesEnabled) {
    $page->addError(schoolCommunityT('Group messaging is disabled for this category.'));
}

if ($groupMessagesEnabled) {
    $modeForm = Form::create('schoolCommunityMessageMode', $session->get('absoluteURL').'/index.php', 'get');
    $modeForm->addHiddenValue('q', '/modules/School Community/message_sending.php');
    $modeForm->addHiddenValue('categoryID', $categoryID);
    $modeForm->setTitle(schoolCommunityT('Message Mode'));
    $row = $modeForm->addRow();
        $row->addLabel('groupMessage', schoolCommunityT('Group Message'));
        $row->addYesNo('groupMessage')->required()->selected($groupMode ? 'Y' : 'N');
    $row = $modeForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Continue'));
    $page->write($modeForm->getOutput());
}

if ($groupMode) {
    $targets = schoolCommunityGroupSendTargetOptions(
        $pdo,
        $gibbonPersonID,
        $roleCategory,
        $categoryID,
        (string) $session->get('gibbonSchoolYearID')
    );
    if (empty($targets)) {
        $page->addError(schoolCommunityT('No eligible groups or classes are available for this category.'));
        return;
    }

    $groupForm = Form::create(
        'schoolCommunityGroupTarget',
        $session->get('absoluteURL').'/modules/School Community/message_groupTargetProcess.php',
        'post'
    );
    $groupForm->addHiddenValue('categoryID', $categoryID);
    $groupForm->setTitle(schoolCommunityT('Choose Group Recipient'));
    $row = $groupForm->addRow();
        $row->addLabel('groupTarget', schoolCommunityT('Administrative Class, Course Class or Group'));
        $row->addSearchSelect('groupTarget')->fromArray($targets)->required()->placeholder()
            ->setAttribute('autocomplete', 'off');
    $row = $groupForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Continue'));
    $page->write($groupForm->getOutput());
    return;
}

$people = schoolCommunityPersonOptions($pdo, $gibbonPersonID, (int) $category['accessControl'], true);
if (empty($people)) {
    $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
    return;
}

$form = Form::create('schoolCommunityMessage', $session->get('absoluteURL').'/modules/School Community/message_sendingProcess.php', 'post');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('categoryID', $categoryID);
$form->addHiddenValue('recipientID', '');
$form->setTitle(schoolCommunityT('Send Message'));
$form->setDescription(schoolCommunityT('Recipients are limited by the selected category. You may also send a private note to yourself.'));

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
    $row->addTextField('recipientName')
        ->readonly()
        ->placeholder(schoolCommunityT('No recipient selected'));

$recipientChooser = (new Action('chooseRecipient', schoolCommunityT('Choose Recipient')))
    ->setURL('/modules/School Community/message_recipientChooser.php')
    ->addParam('categoryID', $categoryID)
    ->setIcon('users')
    ->modalWindow(700, 620)
    ->displayLabel();
$row = $form->addRow();
    $row->addLabel('recipientChooser', schoolCommunityT('Recipient Search'))
        ->description(schoolCommunityT('Open a searchable recipient list filtered by this category.'));
    $row->addContent($recipientChooser->getOutput());

$row = $form->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $column->addEditor('body', $guid)->setRows(12)->showMedia()->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Send Message'));

echo $form->getOutput();
