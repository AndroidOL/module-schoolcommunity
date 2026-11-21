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
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
$categories = schoolCommunityCategoryOptions($pdo, $roleCategory);
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$targetTypes = [
    'free'        => schoolCommunityT('Free Selection'),
    'formGroup'   => schoolCommunityT('Administrative Class'),
    'courseClass' => schoolCommunityT('Course Class'),
    'existing'    => schoolCommunityT('Existing Group'),
];
$targetType = is_scalar($_GET['targetType'] ?? null) ? (string) $_GET['targetType'] : 'free';
$targetType = array_key_exists($targetType, $targetTypes) ? $targetType : 'free';
$requestedGroupMode = ($_GET['groupMessage'] ?? 'N') === 'Y';
$targetFields = [
    'formGroup'   => 'groupTargetFormGroup',
    'courseClass' => 'groupTargetCourseClass',
    'existing'    => 'groupTargetExisting',
];

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

$category = null;
$groupMessagesEnabled = false;
if ($categoryID !== '') {
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
}
$groupMode = $groupMessagesEnabled && $requestedGroupMode;

$groupTarget = '';
if ($groupMode && $targetType !== 'free') {
    $targetField = $targetFields[$targetType] ?? '';
    $groupTarget = $targetField !== '' && is_scalar($_GET[$targetField] ?? null)
        ? trim((string) $_GET[$targetField])
        : '';
}

// Step 1: choose the category, message mode and target on one page.
if ($categoryID === '' || ($groupMode && $targetType !== 'free' && $groupTarget === '')) {
    if ($categoryID !== '' && $groupMode && $targetType !== 'free' && $groupTarget === '') {
        $page->addError(schoolCommunityT('Select the target for this message type.'));
    }

    $targets = schoolCommunitySendTargetChoices($pdo, $gibbonPersonID, $roleCategory, $gibbonSchoolYearID);

    $chooseForm = Form::create(
        'schoolCommunityMessageChoose',
        $session->get('absoluteURL').'/index.php',
        'get',
        'standardForm disable-warnings'
    );
    $chooseForm->addHiddenValue('q', '/modules/School Community/message_sending.php');
    $chooseForm->setTitle(schoolCommunityT('Send Message'));
    $chooseForm->setDescription(schoolCommunityT('Choose a category. Recipients are always limited by the category audience.'));

    $row = $chooseForm->addRow();
        $row->addLabel('categoryID', schoolCommunityT('Category'));
        $row->addSelect('categoryID')->fromArray($categories)->required()->placeholder()->selected($categoryID);

    $row = $chooseForm->addRow();
        $row->addLabel('groupMessage', schoolCommunityT('Group Message'))
            ->description(schoolCommunityT('Use Direct Message for one recipient. Use Group Message for a one-time selection, class or existing group.'));
        $row->addToggle('groupMessage')->setYesNo()->selected($groupMode ? 'Y' : 'N');

    $groupFieldClass = 'schoolCommunityGroupTargetField'.($groupMode ? '' : ' hidden');
    $row = $chooseForm->addRow()->addClass($groupFieldClass);
        $row->addLabel('targetType', schoolCommunityT('Target Type'));
        $targetTypeSelect = $row->addSelect('targetType')->fromArray($targetTypes)->required()->selected($targetType);
        if (!$groupMode) {
            $targetTypeSelect->setDisabled(true);
        }

    $showFree = $groupMode && $targetType === 'free';
    $row = $chooseForm->addRow()->addClass('schoolCommunityGroupTargetField schoolCommunityFreeNotice'.($showFree ? '' : ' hidden'));
        $row->addAlert(
            schoolCommunityT('Free Selection sends one message to at most 5 chosen people. Create a group for larger audiences.'),
            'message'
        );
    $row = $chooseForm->addRow()->addClass('schoolCommunityGroupTargetField schoolCommunityFreeCreateGroup'.($showFree ? '' : ' hidden'));
        $row->addLabel('createGroup', schoolCommunityT('Larger Audience'));
        $row->addWebLink(schoolCommunityT('Create Group'))
            ->setURL($session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupAdding.php');

    foreach (['formGroup', 'courseClass', 'existing'] as $type) {
        $isActiveType = $groupMode && $targetType === $type;
        $row = $chooseForm->addRow()
            ->addClass('schoolCommunityGroupTargetField schoolCommunityGroupTargetPicker schoolCommunityGroupTargetPicker-'.$type.($isActiveType ? '' : ' hidden'));
        $row->addLabel('groupTarget'.ucfirst($type), $targetTypes[$type]);
        $targetField = $row->addSearchSelect('groupTarget'.ucfirst($type))
            ->fromArray($targets[$type])
            ->required()
            ->placeholder()
            ->setAttribute('autocomplete', 'off');
        if (!$isActiveType || empty($targets[$type])) {
            $targetField->setDisabled(true);
        }
    }

    $row = $chooseForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Continue'));

    $page->write($chooseForm->getOutput());
    return;
}

// Step 2a: direct message to one recipient.
if (!$groupMode) {
    if ($requestedGroupMode && !$groupMessagesEnabled) {
        $page->addError(schoolCommunityT('Group messaging is disabled for this category.'));
    }

    $page->navigator->addHeaderAction('change', schoolCommunityT('Change Selection'))
        ->setURL('/modules/School Community/message_sending.php')
        ->setIcon('back')
        ->displayLabel();

    $people = schoolCommunityPersonOptions($pdo, $gibbonPersonID, (int) $category['accessControl'], true);
    if (empty($people)) {
        $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
        return;
    }

    $form = Form::create(
        'schoolCommunityDirectMessage',
        $session->get('absoluteURL').'/modules/School Community/message_sendingProcess.php',
        'post',
        'standardForm disable-warnings'
    );
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('categoryID', $categoryID);
    $form->setTitle(schoolCommunityT('Send Message'));
    $form->setDescription(schoolCommunityT('Recipients are limited by the selected category. You may also send a private note to yourself.'));

    $row = $form->addRow();
        $row->addLabel('categoryName', schoolCommunityT('Category'));
        $row->addTextField('categoryName')->readonly()->setValue($category['categoryName']);

    $row = $form->addRow();
        $row->addLabel('title', schoolCommunityT('Subject'));
        $row->addTextField('title')->required()->maxLength(100);

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

    $page->write($form->getOutput());
    return;
}

// Step 2b: group message — continue straight to the review-and-send page.
$composeURL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupCompose.php'
    .'&categoryID='.urlencode($categoryID);
if ($targetType === 'free') {
    header('Location: '.$composeURL.'&targetType=free');
    exit;
}
header('Location: '.$composeURL.'&target='.urlencode($groupTarget));
exit;
