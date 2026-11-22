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

$categories = schoolCommunityCategoryOptions($pdo, $roleCategory, false, true);
if (empty($categories)) {
    $page->addError(schoolCommunityT('No group-enabled message categories are available for your role. Contact an administrator.'));
    return;
}

$people = schoolCommunityPersonOptions($pdo, $currentPersonID, null, false);

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

// Build a lightweight index for client-side filtering (role, form group, course classes).
$memberIndex = [];
foreach (array_keys($people) as $personID) {
    $memberIndex[(string) $personID] = ['role' => '', 'formGroup' => '', 'classes' => []];
}
$roleRows = $pdo->select(
    "SELECT person.gibbonPersonID, role.category
     FROM gibbonPerson AS person
     JOIN gibbonRole AS role ON role.gibbonRoleID=person.gibbonRoleIDPrimary
     WHERE person.status='Full'"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($roleRows as $row) {
    $id = (string) $row['gibbonPersonID'];
    if (isset($memberIndex[$id])) {
        $memberIndex[$id]['role'] = (string) $row['category'];
    }
}
$enrolRows = $pdo->select(
    "SELECT gibbonPersonID, gibbonFormGroupID FROM gibbonStudentEnrolment WHERE gibbonSchoolYearID=:gibbonSchoolYearID",
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($enrolRows as $row) {
    $id = (string) $row['gibbonPersonID'];
    if (isset($memberIndex[$id])) {
        $memberIndex[$id]['formGroup'] = (string) $row['gibbonFormGroupID'];
    }
}
$classRows = $pdo->select(
    "SELECT classPerson.gibbonPersonID, classPerson.gibbonCourseClassID
     FROM gibbonCourseClassPerson AS classPerson
     JOIN gibbonCourseClass AS class ON class.gibbonCourseClassID=classPerson.gibbonCourseClassID
     JOIN gibbonCourse AS course ON course.gibbonCourseID=class.gibbonCourseID
     WHERE course.gibbonSchoolYearID=:gibbonSchoolYearID",
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($classRows as $row) {
    $id = (string) $row['gibbonPersonID'];
    if (isset($memberIndex[$id])) {
        $memberIndex[$id]['classes'][] = (string) $row['gibbonCourseClassID'];
    }
}

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
    $row->addLabel('filterFormGroupID', schoolCommunityT('Administrative Class'));
    $row->addSelect('filterFormGroupID')->fromArray($formGroups)->placeholder(schoolCommunityT('All'))->setID('scFilterFormGroup');

$row = $form->addRow();
    $row->addLabel('filterCourseClassID', schoolCommunityT('Course Class'));
    $row->addSelect('filterCourseClassID')->fromArray($courseClasses)->placeholder(schoolCommunityT('All'))->setID('scFilterCourseClass');

$row = $form->addRow();
    $row->addLabel('filterRole', schoolCommunityT('Role'));
    $row->addSelect('filterRole')->fromArray([
        'Staff'   => schoolCommunityT('Staff'),
        'Student' => schoolCommunityT('Student'),
        'Parent'  => schoolCommunityT('Parent'),
    ])->placeholder(schoolCommunityT('All'))->setID('scFilterRole');

$row = $form->addRow();
    $row->addContent(
        '<button type="button" id="scGroupFilterClear" class="text-sm text-blue-600 hover:text-blue-800">'
        .schoolCommunityT('Clear filters')
        .'</button>'
    );

$row = $form->addRow();
    $row->addLabel('memberIDs', schoolCommunityT('Members'))->description(schoolCommunityT('Select the people who should be in this group.'));
    $memberColumn = $row->addColumn();
    $memberColumn->addContent(
        '<input type="text" id="scGroupMemberFilter" autocomplete="off" placeholder="'.htmlPrep(schoolCommunityT('Search by name')).'" class="w-full rounded-t-md border border-gray-300 border-b-0 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-inset focus:ring-blue-500" />'
    );
    $memberColumn->addCheckbox('memberIDs')
        ->fromArray($people)
        ->alignLeft()
        ->setLabelClass('leading-normal text-gray-800 py-1.5 px-1 rounded cursor-pointer select-none hover:bg-gray-100')
        ->setOuterClass('scGroupMemberList w-full h-64 overflow-y-auto rounded-b-md border border-gray-300 border-t-0 bg-white p-2');
    $memberColumn->addContent(
        '<div id="scGroupNoRecords" class="hidden scGroupNoRecords w-full h-64 items-center justify-center rounded-b-md border border-gray-300 border-t-0 bg-white text-sm text-gray-500">'
        .schoolCommunityT('No records')
        .'</div>'
    );

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

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
echo '<script type="application/json" id="scGroupMemberIndex">'.json_encode($memberIndex, $jsonFlags).'</script>';
echo <<<'JS'
<script>
(function () {
    var indexEl = document.getElementById('scGroupMemberIndex');
    var index = indexEl ? JSON.parse(indexEl.textContent) : {};

    function applyGroupFilters() {
        var form = document.getElementById('schoolCommunityGroupAdd');
        if (!form) return;
        function norm(v) { var n = parseInt(String(v || '').trim(), 10); return isNaN(n) ? '' : String(n); }
        var fg = (document.getElementById('scFilterFormGroup') || {}).value || '';
        var cc = (document.getElementById('scFilterCourseClass') || {}).value || '';
        var role = (document.getElementById('scFilterRole') || {}).value || '';
        var term = ((document.getElementById('scGroupMemberFilter') || {}).value || '').trim().toLowerCase();
        form.querySelectorAll('input[name="memberIDs[]"]').forEach(function (box) {
            var row = box.closest('div');
            if (!row) return;
            var info = index[box.value] || {};
            var label = row.querySelector('label');
            var name = label ? label.textContent.toLowerCase() : '';
            var ok = true;
            if (role) ok = ok && (info.role === role);
            if (fg) ok = ok && (norm(info.formGroup) === norm(fg));
            if (cc) ok = ok && (Array.isArray(info.classes) && info.classes.some(function (x) { return norm(x) === norm(cc); }));
            if (term) ok = ok && name.indexOf(term) !== -1;
            row.classList.toggle('hidden', !ok);
        });
        var list = form.querySelector('.scGroupMemberList');
        var noRecords = document.getElementById('scGroupNoRecords');
        var visible = 0;
        form.querySelectorAll('input[name="memberIDs[]"]').forEach(function (box) {
            var row = box.closest('div');
            if (row && !row.classList.contains('hidden')) visible++;
        });
        if (list) list.classList.toggle('hidden', visible === 0);
        if (noRecords) {
            noRecords.classList.toggle('hidden', visible !== 0);
            noRecords.classList.toggle('flex', visible === 0);
        }
    }

    ['scFilterFormGroup', 'scFilterCourseClass', 'scFilterRole'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', applyGroupFilters);
    });
    var search = document.getElementById('scGroupMemberFilter');
    if (search) search.addEventListener('input', applyGroupFilters);
    var clear = document.getElementById('scGroupFilterClear');
    if (clear) clear.addEventListener('click', function (e) {
        e.preventDefault();
        ['scFilterFormGroup', 'scFilterCourseClass', 'scFilterRole', 'scGroupMemberFilter'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        applyGroupFilters();
    });
    applyGroupFilters();
})();
</script>
JS;
