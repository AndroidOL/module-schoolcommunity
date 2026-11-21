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
$targetTypes = [
    'free'        => schoolCommunityT('Free Selection'),
    'formGroup'   => schoolCommunityT('Administrative Class'),
    'courseClass' => schoolCommunityT('Course Class'),
    'existing'    => schoolCommunityT('Existing Group'),
];
$targetType = is_scalar($_GET['targetType'] ?? null) ? (string) $_GET['targetType'] : 'free';
$targetType = array_key_exists($targetType, $targetTypes) ? $targetType : 'free';
if ($requestedGroupMode && !$groupMessagesEnabled) {
    $page->addError(schoolCommunityT('Group messaging is disabled for this category.'));
}

if ($groupMessagesEnabled) {
    $modeForm = Form::create('schoolCommunityMessageMode', '', 'get', 'standardForm disable-warnings');
    $modeForm->addHiddenValue('categoryID', $categoryID);
    $modeForm->setTitle(schoolCommunityT('Message Mode'));
    $modeForm->setDescription(schoolCommunityT('Choose whether to send one direct message or open a group conversation.'));
    $row = $modeForm->addRow();
        $row->addLabel('groupMessage', schoolCommunityT('Group Message'));
        $row->addYesNo('groupMessage')->required()->selected($groupMode ? 'Y' : 'N')
            ->setAttribute('onchange', 'schoolCommunitySetMessageMode(this.value)');
    $row = $modeForm->addRow()
        ->addClass('schoolCommunityTargetTypeRow'.($groupMode ? '' : ' hidden'));
        $row->addLabel('targetType', schoolCommunityT('Target Type'));
        $row->addSelect('targetType')->fromArray($targetTypes)->required()->selected($targetType)
            ->setAttribute('onchange', 'schoolCommunitySetGroupTargetType(this.value)');
    $page->write($modeForm->getOutput());
}

$targets = ['formGroup' => [], 'courseClass' => [], 'existing' => []];
if ($groupMessagesEnabled) {
    $targets = schoolCommunityGroupSendTargetOptions(
        $pdo,
        $gibbonPersonID,
        $roleCategory,
        $categoryID,
        (string) $session->get('gibbonSchoolYearID')
    );

    $freeForm = Form::create(
        'schoolCommunityFreeGroup',
        $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php',
        'post',
        'standardForm'.(!$groupMode || $targetType !== 'free' ? ' hidden' : '')
    );
    $freeForm->addHiddenValue('action', 'create');
    $freeForm->addHiddenValue('linkedSource', 'custom');
    $freeForm->addHiddenValue('categoryID', $categoryID);
    $freeForm->setTitle(schoolCommunityT('Free Selection'));
    $freeForm->setDescription(schoolCommunityT('Create a named group, then choose its members from the group page.'));
    $row = $freeForm->addRow();
        $row->addLabel('groupName', schoolCommunityT('Group Name'));
        $row->addTextField('groupName')->required()->maxLength(100);
    $row = $freeForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Create Group'));
    $page->write($freeForm->getOutput());

    $groupForm = Form::create(
        'schoolCommunityGroupTarget',
        $session->get('absoluteURL').'/modules/School Community/message_groupTargetProcess.php',
        'post',
        'standardForm'.(!$groupMode || $targetType === 'free' ? ' hidden' : '')
    );
    $groupForm->addHiddenValue('categoryID', $categoryID);
    $groupForm->addHiddenValue('targetType', $targetType);
    $groupForm->setTitle(schoolCommunityT('Choose Group Recipient'));
    foreach (['formGroup', 'courseClass', 'existing'] as $type) {
        $isActiveType = $groupMode && $targetType === $type;
        $row = $groupForm->addRow()
            ->addClass('schoolCommunityGroupTargetPicker schoolCommunityGroupTargetPicker-'.$type.($isActiveType ? '' : ' hidden'));
        $row->addLabel('groupTarget'.ucfirst($type), $targetTypes[$type]);
        $targetField = $row->addSearchSelect('groupTarget'.ucfirst($type))
            ->fromArray($targets[$type])
            ->required()
            ->placeholder()
            ->setAttribute('autocomplete', 'off');
        if (!$isActiveType) {
            $targetField->setDisabled(true);
        }
    }
    $row = $groupForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Open Group Message'));
    $page->write($groupForm->getOutput());
}

$people = schoolCommunityPersonOptions($pdo, $gibbonPersonID, (int) $category['accessControl'], true);
if (empty($people)) {
    $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
}

$formClass = 'standardForm'.($groupMode ? ' hidden' : '');
$form = Form::create(
    'schoolCommunityDirectMessage',
    $session->get('absoluteURL').'/modules/School Community/message_sendingProcess.php',
    'post',
    $formClass
);
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('categoryID', $categoryID);
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
    $row->addSelectPerson('recipientID')->fromArray($people)->required()->placeholder()
        ->setAttribute('autocomplete', 'off');

$row = $form->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $column->addEditor('body', $guid)->setRows(12)->showMedia()->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Send Message'));

echo $form->getOutput();
