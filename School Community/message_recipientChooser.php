<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs->add(schoolCommunityT('Choose Recipient'));

if (!schoolCommunityMessagingAccessAllowed(
    isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
    isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
)) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName, accessControl
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;

if (empty($category) || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$people = schoolCommunityPersonOptions($pdo, $currentPersonID, (int) $category['accessControl'], true);
if (empty($people)) {
    $page->addError(schoolCommunityT('No eligible recipients are available for this category.'));
    return;
}

$search = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
$form = Form::create('schoolCommunityRecipientSearch', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(schoolCommunityT('Search Recipients'));
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/School Community/message_recipientChooser.php');
$form->addHiddenValue('categoryID', $categoryID);
$row = $form->addRow();
    $row->addLabel('search', schoolCommunityT('Search'));
    $row->addTextField('search')->setValue($search)->maxLength(100)
        ->setAttribute('autocomplete', 'off');
$row = $form->addRow();
    $row->addSearchSubmit($session, schoolCommunityT('Clear search'));
$page->write($form->getOutput());

$recipients = [];
foreach ($people as $personID => $label) {
    if ($search !== '' && mb_stripos($label, $search) === false) {
        continue;
    }
    $recipients[] = [
        'gibbonPersonID' => (string) $personID,
        'recipientName'  => $label,
    ];
}

$table = DataTable::create('schoolCommunityRecipientChooser');
$table->setTitle(sprintf(schoolCommunityT('Recipients in %s'), htmlPrep($category['categoryName'])));
$table->setDescription(schoolCommunityT('Select one recipient. The list is limited to active users allowed by this category.'));
$table->addMetaData('blankSlate', schoolCommunityT('No recipients match this search.'));
$table->addColumn('recipientName', schoolCommunityT('Recipient'))->context('primary');
$table->addActionColumn()->format(function ($recipient, $actions) {
    $arguments = json_encode(
        [$recipient['gibbonPersonID'], $recipient['recipientName']],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
    $actions->addAction('chooseRecipient', schoolCommunityT('Select'))
        ->setURL('#')
        ->setIcon('accept')
        ->displayLabel()
        ->setAttribute('onclick', 'return schoolCommunityChooseRecipient.apply(null, '.$arguments.');');
});

$page->write($table->render(new DataSet($recipients)));
