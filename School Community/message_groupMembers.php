<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Groups'), 'message_groups.php')
    ->add(schoolCommunityT('Manage Group Members'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$page->return->addReturns([
    'success1' => schoolCommunityT('Group member added.'),
    'success2' => schoolCommunityT('Group member removed.'),
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the message group.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$groupID = is_scalar($_GET['groupID'] ?? null) ? trim((string) $_GET['groupID']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$group = $groupID !== '' ? schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory, true) : null;

if (empty($group)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$members = $pdo->select(
    "SELECT member.gibbonPersonID, member.joinedAt, gibbonPerson.title, gibbonPerson.preferredName,
            gibbonPerson.surname, gibbonPerson.username, gibbonRole.category AS roleCategory
     FROM message_group_member AS member
     JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=member.gibbonPersonID
     JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
     WHERE member.groupID=:groupID
     ORDER BY CASE WHEN member.gibbonPersonID=:ownerID THEN 0 ELSE 1 END,
              gibbonPerson.surname, gibbonPerson.preferredName",
    ['groupID' => $groupID, 'ownerID' => $group['ownerID']]
)->fetchAll(PDO::FETCH_ASSOC);

$existingMemberIDs = array_column($members, 'gibbonPersonID');
$people = schoolCommunityGroupMemberOptions($pdo, $group, $currentPersonID, $existingMemberIDs);

if (!empty($people)) {
    $form = Form::create('schoolCommunityGroupAddMember', $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php', 'post');
    $form->addHiddenValue('action', 'addMember');
    $form->addHiddenValue('groupID', $groupID);
    $form->setTitle(schoolCommunityT('Add Group Member'));
    $form->setDescription(schoolCommunityT('Only active users whose role is allowed by this group category can be added.'));
    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', schoolCommunityT('Person'));
        $row->addSelectPerson('gibbonPersonID')->fromArray($people)->required()->placeholder();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Add Member'));
    $page->write($form->getOutput());
} else {
    $page->write('<p>'.schoolCommunityT('There are no other eligible people to add to this group.').'</p>');
}

$rows = [];
foreach ($members as $member) {
    $name = Format::name($member['title'], $member['preferredName'], $member['surname'], $member['roleCategory'], true);
    $remove = '';
    if (!schoolCommunitySamePersonID($member['gibbonPersonID'], $group['ownerID'])) {
        $removeForm = Form::create(
            'schoolCommunityGroupRemove'.$member['gibbonPersonID'],
            $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php',
            'post'
        );
        $removeForm->addHiddenValue('action', 'removeMember');
        $removeForm->addHiddenValue('groupID', $groupID);
        $removeForm->addHiddenValue('gibbonPersonID', $member['gibbonPersonID']);
        $removeForm->addRow()->addSubmit(schoolCommunityT('Remove'))->setClass('small');
        $remove = $removeForm->getOutput();
    } else {
        $remove = '<span class="schoolCommunityOwnerLabel">'.schoolCommunityT('Owner').'</span>';
    }

    $rows[] = $member + [
        'name' => $name,
        'remove' => $remove,
    ];
}

$table = DataTable::create('schoolCommunityGroupMembers');
$table->setTitle(sprintf(schoolCommunityT('%s Members'), htmlPrep($group['groupName'])));
$table->addMetaData('allowHTML', ['remove']);
$table->addColumn('name', schoolCommunityT('Name'));
$table->addColumn('roleCategory', schoolCommunityT('Role'))->format(function ($member) {
    return schoolCommunityT($member['roleCategory']);
});
$table->addColumn('username', schoolCommunityT('Username'));
$table->addColumn('joinedAt', schoolCommunityT('Joined'))->format(function ($member) {
    return Format::dateTime($member['joinedAt']);
});
$table->addColumn('remove', schoolCommunityT('Action'))->format(function ($member) {
    return $member['remove'];
});

$page->write($table->render(new Gibbon\Domain\DataSet($rows)));
