<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs->add(schoolCommunityT('Manage Message Groups'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_groupManage.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$page->return->addReturns([
    'success0' => schoolCommunityT('Message group created.'),
    'success1' => schoolCommunityT('Group member added.'),
    'success2' => schoolCommunityT('Group member removed.'),
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the message group.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$search = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';

$searchForm = Form::create('schoolCommunityGroupManageSearch', $session->get('absoluteURL').'/index.php', 'get');
$searchForm->setTitle(schoolCommunityT('Search Message Groups'));
$searchForm->setClass('noIntBorder w-full');
$searchForm->addHiddenValue('q', '/modules/School Community/message_groupManage.php');
$row = $searchForm->addRow();
    $row->addLabel('search', schoolCommunityT('Search'))->description(schoolCommunityT('Group, category or owner'));
    $row->addTextField('search')->setValue($search)->maxLength(100);
$row = $searchForm->addRow();
    $row->addLabel('categoryID', schoolCommunityT('Category'));
    $row->addSelect('categoryID')
        ->fromArray(schoolCommunityCategoryOptions($pdo, '', true))
        ->selected($categoryID)
        ->placeholder();
$row = $searchForm->addRow();
    $row->addSearchSubmit($session, schoolCommunityT('Clear search'));
$page->write($searchForm->getOutput());

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(message_group.groupName LIKE :search
        OR message_category.categoryName LIKE :search
        OR owner.preferredName LIKE :search
        OR owner.surname LIKE :search)';
    $params['search'] = '%'.$search.'%';
}
if ($categoryID !== '') {
    $where[] = 'message_group.categoryID=:categoryID';
    $params['categoryID'] = $categoryID;
}

$groups = $pdo->select(
    "SELECT message_group.*, message_category.categoryName,
            owner.title AS ownerTitle, owner.preferredName AS ownerPreferredName,
            owner.surname AS ownerSurname, ownerRole.category AS ownerRoleCategory,
            (SELECT COUNT(*) FROM message_group_member AS member
             WHERE member.groupID=message_group.groupID) AS memberCount,
            (SELECT MAX(message.sendTimestamp)
             FROM message_group_message AS message
             WHERE message.groupID=message_group.groupID) AS latestTimestamp
     FROM message_group
     JOIN message_category ON message_category.categoryID=message_group.categoryID
     JOIN gibbonPerson AS owner ON owner.gibbonPersonID=message_group.ownerID
     JOIN gibbonRole AS ownerRole ON ownerRole.gibbonRoleID=owner.gibbonRoleIDPrimary"
    .(!empty($where) ? ' WHERE '.implode(' AND ', $where) : '')
    ." ORDER BY message_category.categoryName, message_group.groupName",
    $params
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($groups as &$group) {
    $group['ownerName'] = Format::name(
        $group['ownerTitle'],
        $group['ownerPreferredName'],
        $group['ownerSurname'],
        $group['ownerRoleCategory'] ?? 'Staff',
        true
    );
    $group['sourceLabel'] = schoolCommunityGroupSourceLabel($group['sourceType'] ?? 'custom');
}
unset($group);

$table = DataTable::create('schoolCommunityManagedMessageGroups');
$table->setTitle(schoolCommunityT('Manage Message Groups'));
$table->setDescription(schoolCommunityT('Review all message groups, their audience category and membership. Membership is managed manually.'));
$table->addMetaData('blankSlate', schoolCommunityT('There are no message groups yet.'));
$table->addColumn('groupName', schoolCommunityT('Group'))->context('primary');
$table->addColumn('categoryName', schoolCommunityT('Category'))->context('primary');
$table->addColumn('sourceLabel', schoolCommunityT('Source'))->context('secondary');
$table->addColumn('ownerName', schoolCommunityT('Owner'))->context('secondary');
$table->addColumn('memberCount', schoolCommunityT('Members'))->context('secondary');
$table->addColumn('latestTimestamp', schoolCommunityT('Latest Message'))->context('secondary')->format(function ($group) {
    return empty($group['latestTimestamp']) ? schoolCommunityT('No messages yet') : Format::dateTime($group['latestTimestamp']);
});
$table->addHeaderAction('create', schoolCommunityT('Create Group'))
    ->setURL('/modules/School Community/message_groupAdding.php')
    ->setIcon('plus')
    ->displayLabel();
$table->addActionColumn()
    ->addParam('groupID')
    ->format(function ($group, $actions) {
        $actions->addAction('view', schoolCommunityT('Open'))
            ->setIcon('markbook')
            ->setURL('/modules/School Community/message_groupView.php');
        $actions->addAction('members', schoolCommunityT('Members'))
            ->setIcon('users')
            ->setURL('/modules/School Community/message_groupMembers.php');
    });

$page->write($table->render(new \Gibbon\Domain\DataSet($groups)));
