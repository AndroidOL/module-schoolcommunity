<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\Action;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Send Message'), 'message_sending.php')
    ->add(schoolCommunityT('Review and Send'));

$page->return->addReturns([
    'error0' => schoolCommunityT('You do not have permission to perform this action.'),
    'error1' => schoolCommunityT('The submitted data is invalid.'),
    'error2' => schoolCommunityT('Unable to save the message.'),
    'error3' => schoolCommunityT('The recipient list changed. Review the updated list and send again.'),
    'error4' => schoolCommunityT('Select at most 5 recipients, or create a group for a larger audience.'),
    'error9' => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

if (!schoolCommunityMessagingAccessAllowed(
    isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
    isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
)) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$groupID = is_scalar($_GET['groupID'] ?? null) ? trim((string) $_GET['groupID']) : '';
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$targetType = is_scalar($_GET['targetType'] ?? null) ? (string) $_GET['targetType'] : '';

$page->navigator->addHeaderAction('back', schoolCommunityT('Change Recipients'))
    ->setURL('/modules/School Community/message_sending.php')
    ->setIcon('back')
    ->displayLabel();

if ($groupID !== '') {
    $group = schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory);
    if (empty($group) || !schoolCommunityGroupMessagesAllowed($group)) {
        $page->addError(schoolCommunityT('The specified record does not exist.'));
        return;
    }

    $members = $pdo->select(
        "SELECT member.gibbonPersonID, gibbonPerson.status, gibbonRole.category AS roleCategory
         FROM message_group_member AS member
         JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=member.gibbonPersonID
         JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
         WHERE member.groupID=:groupID",
        ['groupID' => $groupID]
    )->fetchAll(PDO::FETCH_ASSOC);
    $audience = schoolCommunityGroupAudience($members, $group['accessControl']);
    $recipientIDs = array_map('strval', array_column($audience['eligible'], 'gibbonPersonID'));
    sort($recipientIDs, SORT_STRING);
    $audienceSignature = hash('sha256', implode(',', $recipientIDs));

    if (empty($audience['eligible'])) {
        $page->addError(schoolCommunityT('The actual recipient list is empty. No message can be sent.'));
        return;
    }
    if (!in_array($currentPersonID, $recipientIDs, true)
        && !schoolCommunityCanSelectAllLinkedSources($currentPersonID)
    ) {
        $page->addError(schoolCommunityT('You do not have access to this page.'));
        return;
    }

    $recipientPreview = (new Action('preview', schoolCommunityT('Preview Recipients')))
        ->setURL('/modules/School Community/message_groupRecipients.php')
        ->addParam('groupID', $groupID)
        ->setIcon('users')
        ->modalWindow(700, 520)
        ->displayLabel();

    $form = Form::create(
        'schoolCommunityGroupCompose',
        $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupMessageProcess.php',
        'post',
        'standardForm disable-warnings'
    );
    $form->addHiddenValue('groupID', $groupID);
    $form->addHiddenValue('expectedRecipientSignature', $audienceSignature);
    $form->addHiddenValue('expectedRecipientCount', count($audience['eligible']));
    $form->setTitle(schoolCommunityT('Send Group Message'));
    $form->setDescription(schoolCommunityT('The recipient list is collapsed by default. Preview it before sending. The message is sent only to the displayed snapshot.'));

    $row = $form->addRow();
        $row->addLabel('selectedTarget', schoolCommunityT('Selected Recipients'));
        $row->addContent(htmlPrep($group['groupName']).' · '.sprintf(schoolCommunityT('%d recipients'), count($audience['eligible'])));
    $row = $form->addRow();
        $row->addLabel('preview', schoolCommunityT('Recipient Preview'));
        $row->addContent($recipientPreview->getOutput($group));
    if (!empty($audience['excluded'])) {
        $row = $form->addRow();
            $row->addAlert(sprintf(
                schoolCommunityT('%d group members will be excluded because their account is inactive or their role is not allowed by this category.'),
                count($audience['excluded'])
            ), 'warning');
    }

    $row = $form->addRow();
        $row->addLabel('title', schoolCommunityT('Subject'));
        $row->addTextField('title')->required()->maxLength(100);
    $row = $form->addRow();
        $column = $row->addColumn();
        $column->addLabel('body', schoolCommunityT('Message'));
        $column->addEditor('body', $guid)->setRows(10)->showMedia()->required();
    $row = $form->addRow();
        $row->addLabel('confirmAudience', schoolCommunityT('I have previewed the recipient list.'));
        $row->addCheckbox('confirmAudience')->setValue('Y')->required();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Send Message'));

    $page->write($form->getOutput());
    return;
}

if ($categoryID === '' || $targetType !== 'free') {
    $page->addError(schoolCommunityT('The submitted data is invalid.'));
    return;
}

$category = $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl, groupMessages
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
);
if (empty($category)
    || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)
    || !schoolCommunityGroupMessagesAllowed($category)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$people = schoolCommunityPersonOptions($pdo, $currentPersonID, (int) $category['accessControl'], true);
if (empty($people)) {
    $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
    return;
}

$form = Form::create(
    'schoolCommunityFreeCompose',
    $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_sendingProcess.php',
    'post',
    'standardForm disable-warnings'
);
$form->addHiddenValue('categoryID', $categoryID);
$form->addHiddenValue('recipientMode', 'multi');
$form->setTitle(schoolCommunityT('Send Message'));
$form->setDescription(schoolCommunityT('Free Selection sends one direct message to each chosen person. Choose at most 5 recipients; create a group for a larger audience.'));

$row = $form->addRow();
    $row->addLabel('categoryName', schoolCommunityT('Category'));
    $row->addTextField('categoryName')->readonly()->setValue($category['categoryName']);
$row = $form->addRow();
    $row->addLabel('selectedTarget', schoolCommunityT('Selected Recipients'));
    $row->addContent(schoolCommunityT('Free Selection'));
$row = $form->addRow();
    $row->addLabel('recipientIDs', schoolCommunityT('Recipients'))
        ->description(schoolCommunityT('Use Control, Command and/or Shift to select multiple. Maximum 5 recipients.'));
    $row->addSelectPerson('recipientIDs[]')
        ->fromArray($people)
        ->required()
        ->placeholder()
        ->selectMultiple()
        ->setAttribute('size', '10');
$row = $form->addRow();
    $row->addLabel('title', schoolCommunityT('Subject'));
    $row->addTextField('title')->required()->maxLength(100);
$row = $form->addRow();
    $row->addLabel('priority', schoolCommunityT('Priority'));
    $row->addSelect('priority')->fromArray(schoolCommunityPriorityOptions())->required()->selected('m');
$row = $form->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $column->addEditor('body', $guid)->setRows(10)->showMedia()->required();
$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Send Message'));

$page->write($form->getOutput());
