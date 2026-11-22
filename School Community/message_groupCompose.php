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
    'error4' => schoolCommunityT('Choose at most 5 recipients; create a group for a larger audience.'),
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
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$targetType = is_scalar($_GET['targetType'] ?? null) ? (string) $_GET['targetType'] : '';
$target = is_scalar($_GET['target'] ?? null) ? trim((string) $_GET['target']) : '';

$page->navigator->addHeaderAction('back', schoolCommunityT('Change Selection'))
    ->setURL('/modules/School Community/message_sending.php')
    ->setIcon('back')
    ->displayLabel();

if ($categoryID === '' || ($targetType !== 'free' && $target === '')) {
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

// Free selection: pick up to 5 recipients in a modal, then send individual direct messages.
if ($targetType === 'free') {
    $people = schoolCommunityPersonOptions($pdo, $currentPersonID, (int) $category['accessControl'], true);
    if (empty($people)) {
        $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
        return;
    }

    $selectedRaw = is_scalar($_GET['recipientIDs'] ?? null) ? (string) $_GET['recipientIDs'] : '';
    $selectedIDs = [];
    foreach (explode(',', $selectedRaw) as $recipientID) {
        $recipientID = trim($recipientID);
        if ($recipientID !== '' && isset($people[$recipientID]) && !in_array($recipientID, $selectedIDs, true)) {
            $selectedIDs[] = $recipientID;
        }
    }
    $selectedIDs = array_slice($selectedIDs, 0, 5);

    $pickerAction = (new Action('selectRecipients', schoolCommunityT('Select Recipients')))
        ->setURL('/modules/School Community/message_freeRecipients.php')
        ->addParam('categoryID', $categoryID)
        ->addParam('recipientIDs', implode(',', $selectedIDs))
        ->setIcon('users')
        ->modalWindow(700, 520)
        ->displayLabel();

    $form = Form::create(
        'schoolCommunityFreeCompose',
        $session->get('absoluteURL').'/modules/School Community/message_sendingProcess.php',
        'post',
        'standardForm disable-warnings'
    );
    $form->addHiddenValue('categoryID', $categoryID);
    $form->addHiddenValue('recipientMode', 'multi');
    foreach ($selectedIDs as $recipientID) {
        $form->addHiddenValue('recipientIDs[]', $recipientID);
    }
    $form->setTitle(schoolCommunityT('Send Message'));
    $form->setDescription(schoolCommunityT('Free Selection sends one direct message to each chosen person. Choose at most 5 recipients; create a group for a larger audience.'));

    $row = $form->addRow();
        $row->addLabel('categoryName', schoolCommunityT('Category'));
        $row->addTextField('categoryName')->readonly()->setValue($category['categoryName']);

    $row = $form->addRow();
        $row->addLabel('recipientIDs', schoolCommunityT('Recipients'))
            ->description(schoolCommunityT('Maximum 5 recipients.'));
        $column = $row->addColumn();
        $column->addContent($pickerAction->getOutput());
        if (empty($selectedIDs)) {
            $column->addAlert(schoolCommunityT('No recipients selected yet. Use the button above to choose up to 5 people.'), 'warning');
        } else {
            $selectedNames = [];
            foreach ($selectedIDs as $recipientID) {
                $selectedNames[] = htmlPrep((string) $people[$recipientID]);
            }
            $column->addContent('<div class="text-sm leading-6 text-gray-800">'.implode('<br>', $selectedNames).'</div>');
        }

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
    return;
}

// Class, course or existing group target: collapsed recipient preview before sending.
if (str_starts_with($target, 'group:')) {
    $group = schoolCommunityAccessibleGroup($pdo, substr($target, 6), $currentPersonID, $roleCategory);
    if (empty($group) || (string) $group['categoryID'] !== $categoryID) {
        $page->addError(schoolCommunityT('The specified record does not exist.'));
        return;
    }
    $targetLabel = (string) $group['groupName'];
    $members = $pdo->select(
        "SELECT member.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
         FROM message_group_member AS member
         JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=member.gibbonPersonID
         JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
         WHERE member.groupID=:groupID
         ORDER BY gibbonPerson.surname, gibbonPerson.preferredName",
        ['groupID' => $group['groupID']]
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sources = schoolCommunityLinkedSourceOptions($pdo, $currentPersonID, $gibbonSchoolYearID);
    if (!array_key_exists($target, $sources)) {
        $page->addError(schoolCommunityT('The specified record does not exist.'));
        return;
    }
    $targetLabel = (string) $sources[$target];
    [$sourceType, $sourceID] = array_pad(explode(':', $target, 2), 2, '');
    $members = schoolCommunityLinkedSourceMembers($pdo, $sourceType, $sourceID);
}

$audience = schoolCommunityGroupAudience($members);
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

$recipientNames = [];
foreach ($audience['eligible'] as $member) {
    $recipientNames[] = Format::name(
        $member['title'] ?? '',
        $member['preferredName'] ?? '',
        $member['surname'] ?? '',
        $member['roleCategory'] ?? 'Staff',
        true
    );
}

$form = Form::create(
    'schoolCommunityGroupCompose',
    $session->get('absoluteURL').'/modules/School Community/message_groupMessageProcess.php',
    'post',
    'standardForm disable-warnings'
);
$form->addHiddenValue('categoryID', $categoryID);
$form->addHiddenValue('target', $target);
$form->addHiddenValue('expectedRecipientSignature', $audienceSignature);
$form->addHiddenValue('expectedRecipientCount', count($audience['eligible']));
$form->setTitle(schoolCommunityT('Send Group Message'));
$form->setDescription(schoolCommunityT('The recipient list is collapsed by default. Preview it before sending. The message is sent only to the displayed snapshot.'));

$row = $form->addRow();
    $row->addLabel('selectedTarget', schoolCommunityT('Selected Recipients'));
    $row->addContent(htmlPrep($targetLabel).' · '.sprintf(schoolCommunityT('%d recipients'), count($audience['eligible'])));

$recipientDetails = $form->addRow()->addDetails();
$recipientDetails->summary(sprintf(schoolCommunityT('Preview recipients (%d)'), count($recipientNames)));
$recipientDetails->addContent(
    '<div class="text-sm leading-6 text-gray-800">'
    .implode(', ', array_map('htmlPrep', $recipientNames))
    .'</div>'
);

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
