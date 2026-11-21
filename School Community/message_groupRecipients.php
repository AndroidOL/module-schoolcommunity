<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Groups'), 'message_groups.php')
    ->add(schoolCommunityT('Recipients'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$groupID = is_scalar($_GET['groupID'] ?? null) ? trim((string) $_GET['groupID']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$group = $groupID !== ''
    ? schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory)
    : null;

if (empty($group)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$members = $pdo->select(
    "SELECT member.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
            gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
     FROM message_group_member AS member
     JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=member.gibbonPersonID
     JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
     WHERE member.groupID=:groupID
     ORDER BY gibbonPerson.surname, gibbonPerson.preferredName",
    ['groupID' => $groupID]
)->fetchAll(PDO::FETCH_ASSOC);
$audience = schoolCommunityGroupAudience($members, $group['accessControl']);

$rows = [];
foreach ($audience['eligible'] as $member) {
    $rows[] = [
        'recipientName' => Format::name(
            $member['title'],
            $member['preferredName'],
            $member['surname'],
            $member['roleCategory'],
            true
        ),
        'roleCategory' => schoolCommunityT($member['roleCategory']),
    ];
}

$table = DataTable::create('schoolCommunityGroupRecipients');
$table->setTitle(sprintf(schoolCommunityT('%d recipients'), count($rows)));
$table->setDescription(sprintf(
    schoolCommunityT('%d group members are excluded because their account is inactive or their role is not allowed by this category.'),
    count($audience['excluded'])
));
$table->addMetaData('blankSlate', schoolCommunityT('The actual recipient list is empty. No message can be sent.'));
$table->addColumn('recipientName', schoolCommunityT('Recipient'));
$table->addColumn('roleCategory', schoolCommunityT('Role'));

$page->write($table->render(new \Gibbon\Domain\DataSet($rows)));
