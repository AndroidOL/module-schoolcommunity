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

$page->return->addReturns([
    'error0' => schoolCommunityT('You do not have permission to perform this action.'),
    'error1' => schoolCommunityT('The submitted data is invalid.'),
    'error2' => schoolCommunityT('Unable to save the message.'),
    'error9' => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

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

if ($categoryID === '') {
    $chooseForm = Form::create('schoolCommunityMessageChoose', $session->get('absoluteURL').'/index.php', 'get');
    $chooseForm->addHiddenValue('q', '/modules/School Community/message_sending.php');
    $chooseForm->setTitle(schoolCommunityT('Send Message'));
    $chooseForm->setDescription(schoolCommunityT('Choose a category. Recipients are always limited by the category audience.'));

    $row = $chooseForm->addRow();
        $row->addLabel('categoryID', schoolCommunityT('Category'));
        $row->addSelect('categoryID')->fromArray($categories)->required()->placeholder();

    $row = $chooseForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Continue'));

    $page->write($chooseForm->getOutput());
    return;
}

$category = $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl, groupMessages
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
);

if (empty($category) || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$groupMessagesEnabled = schoolCommunityGroupMessagesAllowed($category);
$requestedGroupMode = ($_GET['groupMessage'] ?? 'N') === 'Y';
$groupMode = $groupMessagesEnabled && $requestedGroupMode;
if ($requestedGroupMode && !$groupMessagesEnabled) {
    $page->addError(schoolCommunityT('Group messaging is disabled for this category.'));
}

$targetTypes = [
    'free'        => schoolCommunityT('Free Selection'),
    'formGroup'   => schoolCommunityT('Administrative Class'),
    'courseClass' => schoolCommunityT('Course Class'),
    'existing'    => schoolCommunityT('Existing Group'),
];
$targetType = is_scalar($_GET['targetType'] ?? null) ? (string) $_GET['targetType'] : 'free';
$targetType = array_key_exists($targetType, $targetTypes) ? $targetType : 'free';

$targets = ['formGroup' => [], 'courseClass' => [], 'existing' => []];
if ($groupMessagesEnabled) {
    $targets = schoolCommunityGroupSendTargetOptions(
        $pdo,
        $gibbonPersonID,
        $roleCategory,
        $categoryID,
        (string) $session->get('gibbonSchoolYearID')
    );

    $page->navigator->addHeaderAction('createGroup', schoolCommunityT('Create Group'))
        ->setURL('/modules/School Community/message_groupAdding.php')
        ->addParam('categoryID', $categoryID)
        ->setIcon('plus')
        ->displayLabel();
}

$people = schoolCommunityPersonOptions($pdo, $gibbonPersonID, (int) $category['accessControl'], true);
if (!$groupMode && empty($people)) {
    $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
}

$directAction = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_sendingProcess.php';
$groupAction = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupTargetProcess.php';

$form = Form::create('schoolCommunitySendMessage', $directAction, 'post');
$form->addHiddenValue('categoryID', $categoryID);
$form->addHiddenValue('address', $session->get('address'));
$form->setTitle(schoolCommunityT('Send Message'));
$form->setDescription(schoolCommunityT('Choose the message mode and recipients on this page. Group targets continue to a recipient preview before sending.'));

$row = $form->addRow();
    $row->addLabel('categoryName', schoolCommunityT('Category'));
    $row->addTextField('categoryName')->readonly()->setValue($category['categoryName']);

if ($groupMessagesEnabled) {
    $row = $form->addRow();
        $row->addLabel('groupMessage', schoolCommunityT('Group Message'))
            ->description(schoolCommunityT('Use Direct Message for one recipient. Use Group Message for a one-time selection, class or existing group.'));
        $row->addToggle('groupMessage')->setYesNo()->selected($groupMode ? 'Y' : 'N');
} else {
    $form->addHiddenValue('groupMessage', 'N');
}

$directDisabled = $groupMode;
$row = $form->addRow()->addClass('schoolCommunityDirectField');
    $row->addLabel('title', schoolCommunityT('Subject'));
    $titleField = $row->addTextField('title')->required()->maxLength(100);
    if ($directDisabled) {
        $titleField->setDisabled(true);
    }

$row = $form->addRow()->addClass('schoolCommunityDirectField');
    $row->addLabel('priority', schoolCommunityT('Priority'));
    $priorityField = $row->addSelect('priority')->fromArray(schoolCommunityPriorityOptions())->required()->selected('m');
    if ($directDisabled) {
        $priorityField->setDisabled(true);
    }

$row = $form->addRow()->addClass('schoolCommunityDirectField');
    $row->addLabel('recipientID', schoolCommunityT('Recipient'));
    $recipientField = $row->addSelectPerson('recipientID')->fromArray($people)->required()->placeholder()
        ->setAttribute('autocomplete', 'off');
    if ($directDisabled || empty($people)) {
        $recipientField->setDisabled(true);
    }

$row = $form->addRow()->addClass('schoolCommunityDirectField');
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $bodyField = $column->addEditor('body', $guid)->setRows(12)->showMedia()->required();
    if ($directDisabled) {
        $bodyField->setDisabled(true);
    }

if ($groupMessagesEnabled) {
    $groupDisabled = !$groupMode;
    $row = $form->addRow()->addClass('schoolCommunityGroupTargetField');
        $row->addLabel('targetType', schoolCommunityT('Target Type'));
        $targetTypeField = $row->addSelect('targetType')->fromArray($targetTypes)->required()->selected($targetType);
        if ($groupDisabled) {
            $targetTypeField->setDisabled(true);
        }

    $row = $form->addRow()->addClass('schoolCommunityGroupTargetField');
        $row->addAlert(
            schoolCommunityT('Free Selection sends one message to at most 5 chosen people. Create a group for larger audiences.'),
            'message'
        );

    foreach (['formGroup', 'courseClass', 'existing'] as $type) {
        $isActiveType = $groupMode && $targetType === $type;
        $row = $form->addRow()
            ->addClass('schoolCommunityGroupTargetField schoolCommunityGroupTargetPicker schoolCommunityGroupTargetPicker-'.$type.($isActiveType ? '' : ' hidden'));
        $row->addLabel('groupTarget'.ucfirst($type), $targetTypes[$type]);
        $targetField = $row->addSearchSelect('groupTarget'.ucfirst($type))
            ->fromArray($targets[$type])
            ->required()
            ->placeholder()
            ->setAttribute('autocomplete', 'off');
        if (!$isActiveType || $groupDisabled || empty($targets[$type])) {
            $targetField->setDisabled(true);
        }
    }
}

$row = $form->addRow();
$row->addFooter();
$directSubmit = $row->addSubmit(schoolCommunityT('Send Message'))
    ->addClass('schoolCommunityDirectSubmit'.($groupMode ? ' hidden' : ''));
if ($groupMode) {
    $directSubmit->setDisabled(true);
}
if ($groupMessagesEnabled) {
    $groupSubmit = $row->addSubmit(schoolCommunityT('Next'))
        ->addClass('schoolCommunityGroupSubmit'.(!$groupMode ? ' hidden' : ''))
        ->setAttribute('formaction', $groupAction);
    if (!$groupMode) {
        $groupSubmit->setDisabled(true);
    }
}

$page->write($form->getOutput());
