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

// Build a lightweight name index for client-side text filtering.
$fgNameById = [];
foreach ($formGroups as $formGroupID => $formGroupName) {
    $fgNameById[(string) (int) $formGroupID] = (string) $formGroupName;
}
$ccNameById = [];
foreach ($courseClasses as $courseClassID => $courseClassName) {
    $ccNameById[(string) (int) $courseClassID] = (string) $courseClassName;
}

$memberIndex = [];
foreach (array_keys($people) as $personID) {
    $memberIndex[(string) $personID] = [
        'role'          => '',
        'roleLabel'     => '',
        'formGroupName' => '',
        'classNames'    => [],
    ];
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
        $memberIndex[$id]['roleLabel'] = schoolCommunityT((string) $row['category']);
    }
}
$enrolRows = $pdo->select(
    "SELECT gibbonPersonID, gibbonFormGroupID FROM gibbonStudentEnrolment WHERE gibbonSchoolYearID=:gibbonSchoolYearID",
    ['gibbonSchoolYearID' => $gibbonSchoolYearID]
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($enrolRows as $row) {
    $id = (string) $row['gibbonPersonID'];
    if (isset($memberIndex[$id])) {
        $memberIndex[$id]['formGroupName'] = $fgNameById[(string) (int) $row['gibbonFormGroupID']] ?? '';
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
        $className = $ccNameById[(string) (int) $row['gibbonCourseClassID']] ?? '';
        if ($className !== '') {
            $memberIndex[$id]['classNames'][] = $className;
        }
    }
}

$memberRows = '';
foreach ($people as $personID => $personLabel) {
    $inputId = 'scMember'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $personID);
    $memberRows .= '<div class="w-full inline-flex items-center gap-2 py-1">'
        .'<input type="checkbox" name="memberIDs[]" id="'.htmlPrep($inputId).'" value="'.htmlPrep((string) $personID).'" class="h-4 w-4 rounded text-blue-500 focus:ring-blue-600">'
        .'<label for="'.htmlPrep($inputId).'" class="flex-1 text-sm text-gray-800 cursor-pointer select-none">'.htmlPrep((string) $personLabel).'</label>'
        .'</div>';
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
    $row->addSearchSelect('filterFormGroupID')->fromArray($formGroups)->placeholder(schoolCommunityT('All'))->setID('scFilterFormGroup');

$row = $form->addRow();
    $row->addLabel('filterCourseClassID', schoolCommunityT('Course Class'));
    $row->addSearchSelect('filterCourseClassID')->fromArray($courseClasses)->placeholder(schoolCommunityT('All'))->setID('scFilterCourseClass');

$row = $form->addRow();
    $row->addLabel('filterRole', schoolCommunityT('Role'));
    $row->addSearchSelect('filterRole')->fromArray([
        'Staff'   => schoolCommunityT('Staff'),
        'Student' => schoolCommunityT('Student'),
        'Parent'  => schoolCommunityT('Parent'),
    ])->placeholder(schoolCommunityT('All'))->setID('scFilterRole');

$row = $form->addRow();
    $row->addLabel('memberIDs', schoolCommunityT('Members'))->description(schoolCommunityT('Select the people who should be in this group.'));
    $memberColumn = $row->addColumn();
    $memberColumn->addContent(
        '<div class="scGroupMemberPicker w-full rounded-md border border-gray-300 bg-white">'
        .'<div class="p-2 border-b border-gray-200">'
        .'<input type="text" id="scGroupMemberFilter" autocomplete="off" placeholder="'.htmlPrep(schoolCommunityT('Search by name')).'" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-inset focus:ring-blue-500" />'
        .'</div>'
        .'<div class="scGroupMemberList max-h-80 overflow-y-auto p-2">'.$memberRows.'</div>'
        .'<div id="scGroupNoRecords" class="hidden h-64 w-full items-center justify-center p-4 text-center text-sm text-gray-500">'.schoolCommunityT('No records').'</div>'
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

    function selectedText(id) {
        var sel = document.getElementById(id);
        if (!sel) return '';
        var opt = sel.options[sel.selectedIndex];
        return opt ? String(opt.text || '').trim() : '';
    }
    function applyGroupFilters() {
        var form = document.getElementById('schoolCommunityGroupAdd');
        if (!form) return;
        var fg = selectedText('scFilterFormGroup');
        var cc = selectedText('scFilterCourseClass');
        var role = selectedText('scFilterRole');
        var term = ((document.getElementById('scGroupMemberFilter') || {}).value || '').trim().toLowerCase();
        form.querySelectorAll('input[name="memberIDs[]"]').forEach(function (box) {
            var row = box.closest('div');
            if (!row) return;
            var info = index[box.value] || {};
            var label = row.querySelector('label');
            var name = label ? label.textContent.toLowerCase() : '';
            var ok = true;
            if (fg) ok = ok && (String(info.formGroupName || '') === fg);
            if (cc) ok = ok && (Array.isArray(info.classNames) && info.classNames.indexOf(cc) !== -1);
            if (role) ok = ok && (String(info.roleLabel || '') === role || String(info.role || '') === role);
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
    applyGroupFilters();
})();
</script>
JS;
