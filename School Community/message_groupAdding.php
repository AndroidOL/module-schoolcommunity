<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Groups'), 'message_groups.php')
    ->add(schoolCommunityT('Create Group'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_groupManage.php')
    && !schoolCommunityMessagingAccessAllowed(
        isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
        isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
    )
) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
$filterFormGroupID = is_scalar($_GET['filterFormGroupID'] ?? null) ? trim((string) $_GET['filterFormGroupID']) : '';
$filterCourseClassID = is_scalar($_GET['filterCourseClassID'] ?? null) ? trim((string) $_GET['filterCourseClassID']) : '';
$filterRole = is_scalar($_GET['filterRole'] ?? null) ? trim((string) $_GET['filterRole']) : '';

$categories = schoolCommunityCategoryOptions($pdo, $roleCategory, false, true);
if (empty($categories)) {
    $page->addError(schoolCommunityT('No group-enabled message categories are available for your role. Contact an administrator.'));
    return;
}

$people = schoolCommunityPersonOptions($pdo, $currentPersonID, null, false);
$filterSets = [];
if ($filterFormGroupID !== '') {
    $set = [];
    $students = $pdo->select(
        "SELECT gibbonPersonID FROM gibbonStudentEnrolment WHERE gibbonFormGroupID=:gibbonFormGroupID",
        ['gibbonFormGroupID' => $filterFormGroupID]
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($students as $studentID) {
        $set[(string) $studentID] = true;
    }
    $tutors = $pdo->selectOne(
        "SELECT gibbonPersonIDTutor, gibbonPersonIDTutor2, gibbonPersonIDTutor3
         FROM gibbonFormGroup WHERE gibbonFormGroupID=:gibbonFormGroupID",
        ['gibbonFormGroupID' => $filterFormGroupID]
    );
    if (!empty($tutors)) {
        foreach (['gibbonPersonIDTutor', 'gibbonPersonIDTutor2', 'gibbonPersonIDTutor3'] as $key) {
            if (!empty($tutors[$key])) {
                $set[(string) $tutors[$key]] = true;
            }
        }
    }
    $filterSets[] = $set;
}
if ($filterCourseClassID !== '') {
    $set = [];
    $members = $pdo->select(
        "SELECT gibbonPersonID FROM gibbonCourseClassPerson WHERE gibbonCourseClassID=:gibbonCourseClassID",
        ['gibbonCourseClassID' => $filterCourseClassID]
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($members as $memberID) {
        $set[(string) $memberID] = true;
    }
    $filterSets[] = $set;
}
if ($filterRole !== '' && in_array($filterRole, ['Staff', 'Student', 'Parent', 'Other'], true)) {
    $set = [];
    $rolePeople = $pdo->select(
        "SELECT person.gibbonPersonID
         FROM gibbonPerson AS person
         JOIN gibbonRole AS role ON role.gibbonRoleID=person.gibbonRoleIDPrimary
         WHERE person.status='Full' AND role.category=:category",
        ['category' => $filterRole]
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rolePeople as $rolePersonID) {
        $set[(string) $rolePersonID] = true;
    }
    $filterSets[] = $set;
}
if (!empty($filterSets)) {
    $people = array_filter($people, function ($personID) use ($filterSets) {
        foreach ($filterSets as $set) {
            if (!isset($set[(string) $personID])) {
                return false;
            }
        }

        return true;
    }, ARRAY_FILTER_USE_KEY);
}

$formGroups = $pdo->select(
    "SELECT gibbonFormGroupID AS value, name FROM gibbonFormGroup
     WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name",
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchKeyPair();
$courseClasses = $pdo->select(
    "SELECT gibbonCourseClass.gibbonCourseClassID AS value,
            CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) AS name
     FROM gibbonCourseClass
     JOIN gibbonCourse ON gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID
     WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
     ORDER BY name",
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchKeyPair();

$filterForm = Form::create('schoolCommunityGroupFilter', $session->get('absoluteURL').'/index.php', 'get');
$filterForm->setTitle(schoolCommunityT('Filter Members'));
$filterForm->setClass('noIntBorder w-full');
$filterForm->addHiddenValue('q', '/modules/School Community/message_groupAdding.php');
$row = $filterForm->addRow();
    $row->addLabel('filterFormGroupID', schoolCommunityT('Administrative Class'));
    $row->addSelect('filterFormGroupID')->fromArray($formGroups)->selected($filterFormGroupID)->placeholder();
$row = $filterForm->addRow();
    $row->addLabel('filterCourseClassID', schoolCommunityT('Course Class'));
    $row->addSelect('filterCourseClassID')->fromArray($courseClasses)->selected($filterCourseClassID)->placeholder();
$row = $filterForm->addRow();
    $row->addLabel('filterRole', schoolCommunityT('Role'));
    $row->addSelect('filterRole')->fromArray([
        'Staff'   => schoolCommunityT('Staff'),
        'Student' => schoolCommunityT('Student'),
        'Parent'  => schoolCommunityT('Parent'),
    ])->selected($filterRole)->placeholder();
$row = $filterForm->addRow();
    $row->addFooter();
    $row->addSearchSubmit($session, schoolCommunityT('Clear filters'));
$page->write($filterForm->getOutput());

$form = Form::create(
    'schoolCommunityGroupAdd',
    $session->get('absoluteURL').'/modules/School Community/message_groupProcess.php',
    'post',
    'standardForm disable-warnings'
);
$form->addHiddenValue('action', 'create');
$form->setTitle(schoolCommunityT('Create Group'));
$form->setDescription(schoolCommunityT('Give the group a name and choose the people who belong to it. Members can be edited afterwards.'));

$row = $form->addRow();
    $row->addLabel('groupName', schoolCommunityT('Group Name'));
    $row->addTextField('groupName')->required()->maxLength(100);

$row = $form->addRow();
    $memberColumn = $row->addColumn();
    $memberColumn->addLabel('memberIDs', schoolCommunityT('Members'))->description(schoolCommunityT('Select the people who should be in this group.'));
    $memberColumn->addContent(
        '<input type="text" id="scGroupMemberSearch" name="memberSearch" autocomplete="off" placeholder="'.htmlPrep(schoolCommunityT('Search by name')).'" class="w-full mb-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-inset focus:ring-blue-500" />'
    );
    $memberColumn->addCheckbox('memberIDs')
        ->fromArray($people)
        ->alignLeft()
        ->setLabelClass('leading-normal text-gray-800 py-1.5 px-1 rounded cursor-pointer select-none hover:bg-gray-100')
        ->setOuterClass('w-full max-h-80 overflow-y-auto rounded-md border border-gray-200 bg-white p-2');
    if (empty($people)) {
        $memberColumn->addContent(
            '<p class="mt-1 text-xs text-gray-500">'.schoolCommunityT('No eligible people match this filter.').'</p>'
        );
    }

$row = $form->addRow();
    $row->addContent(
        '<div class="text-sm text-gray-700">'
        .'<span class="font-semibold">'.schoolCommunityT('Selected').': <span id="scGroupSelectedCount">0</span></span>'
        .'<div id="scGroupSelectedNames" class="mt-1 text-xs text-gray-600"></div>'
        .'</div>'
    );

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Create Group'));

$page->write($form->getOutput());
