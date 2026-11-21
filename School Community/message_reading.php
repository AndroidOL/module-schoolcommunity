<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs->add(schoolCommunityT('Messages'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$gibbonPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$search = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$mailbox = is_scalar($_GET['mailbox'] ?? null) ? (string) $_GET['mailbox'] : 'all';
$mailbox = in_array($mailbox, ['all', 'received', 'sent', 'unread'], true) ? $mailbox : 'all';

$form = Form::create('schoolCommunitySearch', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(schoolCommunityT('Search Messages'));
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/School Community/message_reading.php');

$row = $form->addRow();
    $row->addLabel('search', schoolCommunityT('Search'))->description(schoolCommunityT('Title or message text'));
    $row->addTextField('search')->setValue($search)->maxLength(100);

$row = $form->addRow();
    $row->addLabel('categoryID', schoolCommunityT('Category'));
    $row->addSelect('categoryID')
        ->fromArray(schoolCommunityCategoryOptions($pdo, $roleCategory))
        ->selected($categoryID)
        ->placeholder();

$row = $form->addRow();
    $row->addLabel('mailbox', schoolCommunityT('Mailbox'));
    $row->addSelect('mailbox')->fromArray([
        'all'      => schoolCommunityT('All'),
        'received' => schoolCommunityT('Received'),
        'sent'     => schoolCommunityT('Sent'),
        'unread'   => schoolCommunityT('Unread'),
    ])->selected($mailbox);

$row = $form->addRow();
    $row->addSearchSubmit($session, schoolCommunityT('Clear search'));

$page->write($form->getOutput());

$where = ["(message_totals.senderID=:currentPersonID OR message_totals.receiverID=:currentPersonID)"];
$params = ['currentPersonID' => $gibbonPersonID];

if ($search !== '') {
    $where[] = '(message_totals.title LIKE :search OR message_totals.body LIKE :search)';
    $params['search'] = '%'.$search.'%';
}
if ($categoryID !== '') {
    $where[] = 'message_totals.categoryID=:categoryID';
    $params['categoryID'] = $categoryID;
}
if ($mailbox === 'received') {
    $where[] = 'message_totals.receiverID=:currentPersonID';
} elseif ($mailbox === 'sent') {
    $where[] = 'message_totals.senderID=:currentPersonID';
} elseif ($mailbox === 'unread') {
    $where[] = 'message_totals.receiverID=:currentPersonID AND receipt.receiptID IS NULL';
}

$messages = $pdo->select(
    "SELECT message_totals.*, message_category.categoryName,
            sender.title AS senderTitle, sender.preferredName AS senderPreferredName, sender.surname AS senderSurname,
            receiver.title AS receiverTitle, receiver.preferredName AS receiverPreferredName, receiver.surname AS receiverSurname,
            receipt.readTimestamp
     FROM message_totals
     JOIN message_category ON message_category.categoryID=message_totals.categoryID
     JOIN gibbonPerson AS sender ON sender.gibbonPersonID=message_totals.senderID
     JOIN gibbonPerson AS receiver ON receiver.gibbonPersonID=message_totals.receiverID
     LEFT JOIN message_read_receipt AS receipt
        ON receipt.messageID=message_totals.messageID
       AND receipt.gibbonPersonID=:currentPersonID
     WHERE ".implode(' AND ', $where)."
     ORDER BY CASE WHEN message_totals.priority=1 THEN 0 WHEN message_totals.priority IS NULL THEN 1 ELSE 2 END,
              message_totals.sendTimestamp DESC
     LIMIT 200",
    $params
)->fetchAll(PDO::FETCH_ASSOC);

$table = DataTable::create('schoolCommunityMessages');
$table->setTitle(schoolCommunityT('Messages'));
$table->setDescription(schoolCommunityT('Messages you sent or received. Unread messages are marked in the title.'));
$table->addMetaData('allowHTML', ['title']);

$table->addColumn('title', schoolCommunityT('Subject'))->format(function ($message) {
    $prefix = !empty($message['isUnread']) ? '<strong>' : '';
    $suffix = !empty($message['isUnread']) ? '</strong>' : '';

    return $prefix.Format::link($message['url'], $message['title']).$suffix;
});
$table->addColumn('categoryName', schoolCommunityT('Category'));
$table->addColumn('otherPerson', schoolCommunityT('From / To'));
$table->addColumn('direction', schoolCommunityT('Direction'));
$table->addColumn('priorityLabel', schoolCommunityT('Priority'));
$table->addColumn('sendTimestamp', schoolCommunityT('Sent'))->format(function ($message) {
    return Format::dateTime($message['sendTimestamp']);
});

if (isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php')) {
    $table->addHeaderAction('send', schoolCommunityT('Send Message'))
        ->setURL('/modules/School Community/message_sending.php')
        ->setIcon('messenger')
        ->displayLabel();
}
if (isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $table->addHeaderAction('categories', schoolCommunityT('Manage Categories'))
        ->setURL('/modules/School Community/message_categorySetting.php')
        ->displayLabel();
}

$page->write($table->render(schoolCommunityMessageTable($messages, $gibbonPersonID)));
