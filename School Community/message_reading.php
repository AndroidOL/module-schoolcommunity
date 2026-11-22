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

$page->return->addReturns([
    'success6' => schoolCommunityT('Message marked unread.'),
    'success7' => schoolCommunityT('Message moved to Trash.'),
    'success8' => schoolCommunityT('Message restored from Trash.'),
    'success10' => schoolCommunityT('Messages sent.'),
]);

$gibbonPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$search = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$mailbox = is_scalar($_GET['mailbox'] ?? null) ? (string) $_GET['mailbox'] : 'all';
$mailbox = in_array($mailbox, ['all', 'received', 'sent', 'unread', 'archived', 'trash'], true) ? $mailbox : 'all';

$form = Form::create('schoolCommunitySearch', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(schoolCommunityT('Search Messages'));
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/School Community/message_reading.php');
$form->addHiddenValue('mailbox', $mailbox);

$row = $form->addRow();
    $row->addLabel('search', schoolCommunityT('Search'))->description(schoolCommunityT('Subject, message or reply text'));
    $row->addTextField('search')->setValue($search)->maxLength(100);

$row = $form->addRow();
    $row->addLabel('categoryID', schoolCommunityT('Category'));
    $row->addSelect('categoryID')
        ->fromArray(schoolCommunityCategoryOptions($pdo, $roleCategory))
        ->selected($categoryID)
        ->placeholder();

$row = $form->addRow();
    $row->addSearchSubmit($session, schoolCommunityT('Clear search'));

$page->write($form->getOutput());

$mailboxTabs = [
    'received' => schoolCommunityT('Received Messages'),
    'sent'     => schoolCommunityT('Sent Messages'),
    'unread'   => schoolCommunityT('Unread'),
    'trash'    => schoolCommunityT('Archive / Trash'),
    'all'      => schoolCommunityT('All'),
];
$tabParams = array_filter(['search' => $search, 'categoryID' => $categoryID], function ($value) {
    return $value !== '';
});
$tabsHTML = '<div class="relative w-full max-w-full">'
    .'<div class="overflow-y-hidden w-full"><div class="flex flex-wrap justify-start items-end" role="tablist" aria-label="'.htmlPrep(schoolCommunityT('Mailbox')).'">';
foreach ($mailboxTabs as $tabKey => $tabLabel) {
    $tabURL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_reading.php&mailbox='.$tabKey;
    if (!empty($tabParams)) {
        $tabURL .= '&'.http_build_query($tabParams);
    }
    $active = $mailbox === $tabKey;
    $tabClass = $active
        ? 'text-gray-900 border border-gray-400 border-b-white z-10 bg-white shadow'
        : 'text-gray-800 hover:bg-gray-200 border border-transparent hover:border-b-gray-400 bg-transparent';
    $tabsHTML .= '<a href="'.htmlPrep($tabURL).'" role="tab" class="inline-flex items-center px-4 sm:px-5 py-2 -mr-1 sm:-mr-2 font-normal rounded-t-md no-underline '.$tabClass.'">'
        .'<span class="block sm:inline text-xxs sm:text-sm whitespace-nowrap">'.htmlPrep($tabLabel).'</span></a>';
}
$tabsHTML .= '</div></div></div>';

$where = ["(message_totals.senderID=:currentPersonID OR message_totals.receiverID=:currentPersonID)"];
$params = ['currentPersonID' => $gibbonPersonID];

if ($search !== '') {
    $where[] = "(message_totals.title LIKE :search OR message_totals.body LIKE :search
                 OR EXISTS (
                     SELECT 1 FROM message_reply AS searchedReply
                     WHERE searchedReply.messageID=message_totals.messageID
                       AND searchedReply.body LIKE :search
                 ))";
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
if ($mailbox === 'trash') {
    $where[] = '(threadState.deletedAt IS NOT NULL OR threadState.archivedAt IS NOT NULL)';
} else {
    $where[] = 'threadState.deletedAt IS NULL AND threadState.archivedAt IS NULL';
}

$messages = $pdo->select(
    "SELECT message_totals.*, message_category.categoryName,
            sender.title AS senderTitle, sender.preferredName AS senderPreferredName, sender.surname AS senderSurname,
            receiver.title AS receiverTitle, receiver.preferredName AS receiverPreferredName, receiver.surname AS receiverSurname,
            receipt.readTimestamp, threadState.archivedAt, threadState.deletedAt
     FROM message_totals
     JOIN message_category ON message_category.categoryID=message_totals.categoryID
     JOIN gibbonPerson AS sender ON sender.gibbonPersonID=message_totals.senderID
     JOIN gibbonPerson AS receiver ON receiver.gibbonPersonID=message_totals.receiverID
     LEFT JOIN message_read_receipt AS receipt
        ON receipt.messageID=message_totals.messageID
       AND receipt.gibbonPersonID=:currentPersonID
     LEFT JOIN message_thread_state AS threadState
        ON threadState.threadType='direct'
       AND threadState.threadID=message_totals.messageID
       AND threadState.gibbonPersonID=:currentPersonID
     WHERE ".implode(' AND ', $where)."
     ORDER BY CASE WHEN message_totals.priority=1 THEN 0 WHEN message_totals.priority IS NULL THEN 1 ELSE 2 END,
              message_totals.sendTimestamp DESC
     LIMIT 200",
    $params
)->fetchAll(PDO::FETCH_ASSOC);

$table = DataTable::create('schoolCommunityMessages');
$table->setTitle(schoolCommunityT('Messages'));
$table->setDescription(schoolCommunityT('Messages you sent or received. Preview message content and use the action menu to open, archive or delete a message.'));
$table->addMetaData('allowHTML', ['title']);

$table->addColumn('title', schoolCommunityT('Subject'))->context('primary')->format(function ($message) {
    $prefix = !empty($message['isUnread']) ? '<strong>' : '';
    $suffix = !empty($message['isUnread']) ? '</strong>' : '';
    $preview = !empty($message['preview'])
        ? '<div class="mt-1 text-sm font-normal text-gray-600">'.htmlPrep($message['preview']).'</div>'
        : '';

    return $prefix.'<a href="'.htmlPrep($message['url']).'">'.htmlPrep($message['title']).'</a>'.$suffix.$preview;
});
$table->addColumn('categoryName', schoolCommunityT('Category'));
$table->addColumn('direction', schoolCommunityT('Direction'))->context('secondary');
$table->addColumn('senderName', schoolCommunityT('Sender'))->context('secondary');
$table->addColumn('receiverName', schoolCommunityT('Recipient'))->context('secondary');
$table->addColumn('priorityLabel', schoolCommunityT('Priority'));
$table->addColumn('sendTimestamp', schoolCommunityT('Sent'))->context('secondary')->format(function ($message) {
    return Format::dateTime($message['sendTimestamp']);
});
$table->addActionColumn()->format(function ($message, $actions) {
    $actions->addAction('view', schoolCommunityT('Open'))
        ->setURL('/modules/School Community/message_view.php')
        ->addParam('messageID', $message['messageID']);

    if (!empty($message['isDeleted'])) {
        $actions->addAction('restoreDelete', schoolCommunityT('Restore from Trash'))
            ->setIcon('arrow-left-circle')
            ->setURL('/modules/School Community/message_threadConfirm.php')
            ->addParam('threadType', 'direct')
            ->addParam('threadID', $message['messageID'])
            ->addParam('action', 'restoreDelete');
        return;
    }

    $actions->addAction('archive', !empty($message['isArchived']) ? schoolCommunityT('Restore') : schoolCommunityT('Archive'))
        ->setIcon(!empty($message['isArchived']) ? 'arrow-left-circle' : 'archive')
        ->setURL('/modules/School Community/message_threadConfirm.php')
        ->addParam('threadType', 'direct')
        ->addParam('threadID', $message['messageID'])
        ->addParam('action', !empty($message['isArchived']) ? 'restore' : 'archive');
    $actions->addAction('delete', schoolCommunityT('Delete'))
        ->setIcon('delete')
        ->setURL('/modules/School Community/message_threadConfirm.php')
        ->addParam('threadType', 'direct')
        ->addParam('threadID', $message['messageID'])
        ->addParam('action', 'delete');
});

$groupsURL = isActionAccessible($guid, $connection2, '/modules/School Community/message_groupManage.php')
    ? '/modules/School Community/message_groupManage.php'
    : '/modules/School Community/message_groups.php';
$table->addHeaderAction('groups', schoolCommunityT('Message Groups'))
    ->setURL($groupsURL)
    ->setIcon('users')
    ->displayLabel();
if (schoolCommunityMessagingAccessAllowed(
    isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
    isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
)) {
    $table->addHeaderAction('send', schoolCommunityT('Send Message'))
        ->setURL('/modules/School Community/message_sending.php')
        ->setIcon('chat-bubble-text')
        ->displayLabel();
}
if (isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $table->addHeaderAction('categories', schoolCommunityT('Manage Categories'))
        ->setURL('/modules/School Community/message_categorySetting.php')
        ->setIcon('config')
        ->displayLabel();
}

$messagesHTML = $table->render(schoolCommunityMessageTable($messages, $gibbonPersonID));
// Place the mailbox tabs directly above the message table, below the DataTable header.
$messagesHTML = str_replace(
    '<div id="schoolCommunityMessages"',
    $tabsHTML.'<div id="schoolCommunityMessages"',
    $messagesHTML
);
$page->write($messagesHTML);
