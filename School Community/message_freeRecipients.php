<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$selectedRaw = is_scalar($_GET['recipientIDs'] ?? null) ? (string) $_GET['recipientIDs'] : '';

$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl, groupMessages
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;
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

$selected = [];
foreach (explode(',', $selectedRaw) as $recipientID) {
    $recipientID = trim($recipientID);
    if ($recipientID !== '' && isset($people[$recipientID]) && !in_array($recipientID, $selected, true)) {
        $selected[] = $recipientID;
    }
}
$selected = array_slice($selected, 0, 5);

$form = Form::create(
    'schoolCommunityFreeRecipients',
    $session->get('absoluteURL').'/modules/School Community/message_freeRecipientsProcess.php',
    'post'
);
$form->addHiddenValue('categoryID', $categoryID);
$form->setTitle(schoolCommunityT('Select Recipients'));
$form->setDescription(schoolCommunityT('Choose at most 5 recipients. Create a group for a larger audience.'));

$row = $form->addRow();
    $row->addLabel('recipientSearch', schoolCommunityT('Search'));
    $row->addTextField('recipientSearch')
        ->setAttribute('autocomplete', 'off')
        ->setAttribute('placeholder', schoolCommunityT('Search by name'));

$row = $form->addRow();
    $row->addLabel('recipientIDs', schoolCommunityT('Recipients'))
        ->description(schoolCommunityT('Maximum 5 recipients.'));
    $row->addCheckbox('recipientIDs')
        ->fromArray($people)
        ->checked($selected)
        ->alignLeft()
        ->required()
        ->setOuterClass('w-full max-h-80 overflow-y-auto rounded-md border border-gray-200 p-2');

$row = $form->addRow();
    $row->addContent(
        '<span class="text-sm text-gray-600">'.schoolCommunityT('Selected').': '
        .'<span id="schoolCommunityFreeRecipientCount" class="font-semibold">'.count($selected).'</span> / 5</span>'
    );

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Use Selected Recipients'));

$page->write($form->getOutput());