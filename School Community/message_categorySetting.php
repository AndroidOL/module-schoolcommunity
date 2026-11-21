<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs->add(schoolCommunityT('Message Categories'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$page->return->addReturns([
    'success0' => schoolCommunityT('Category created.'),
    'success1' => schoolCommunityT('Category updated.'),
    'success2' => schoolCommunityT('Category deleted.'),
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the category.'),
    'error3'   => schoolCommunityT('The category is in use and cannot be deleted.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$search = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';

$form = Form::create('schoolCommunityCategorySearch', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(schoolCommunityT('Search Categories'));
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/School Community/message_categorySetting.php');
$row = $form->addRow();
    $row->addLabel('search', schoolCommunityT('Search'));
    $row->addTextField('search')->setValue($search)->maxLength(100);
$row = $form->addRow();
    $row->addSearchSubmit($session, schoolCommunityT('Clear search'));
$page->write($form->getOutput());

$params = [];
$where = '';
if ($search !== '') {
    $where = 'WHERE categoryName LIKE :search';
    $params['search'] = '%'.$search.'%';
}
$categories = $pdo->select(
    "SELECT categoryID, categoryName, accessControl, groupMessages, createTime, updateTime
     FROM message_category
     {$where}
     ORDER BY categoryName",
    $params
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as &$category) {
    $category['permissions'] = schoolCommunityPermissionLabel($category['accessControl']);
    $category['groupMessagesLabel'] = schoolCommunityYesNoLabel($category['groupMessages']);
}
unset($category);

$table = DataTable::create('schoolCommunityCategories');
$table->setTitle(schoolCommunityT('Message Categories'));
$table->setDescription(schoolCommunityT('Categories control which role groups can send messages in each category.'));
$table->addColumn('categoryName', schoolCommunityT('Name'));
$table->addColumn('permissions', schoolCommunityT('Visible To'));
$table->addColumn('groupMessagesLabel', schoolCommunityT('Group Messages'));
$table->addColumn('updateTime', schoolCommunityT('Updated'))->format(function ($category) {
    return Format::dateTime($category['updateTime']);
});
$table->addHeaderAction('add', schoolCommunityT('Add Category'))
    ->setURL('/modules/School Community/message_categoryAdding.php')
    ->setIcon('add')
    ->displayLabel();

$table->addActionColumn()
    ->addParam('categoryID')
    ->format(function ($category, $actions) {
        $actions->addAction('edit', schoolCommunityT('Edit'))
            ->setIcon('edit')
            ->setURL('/modules/School Community/message_categoryEditing.php');
        $actions->addAction('delete', schoolCommunityT('Delete'))
            ->setIcon('delete')
            ->setURL('/modules/School Community/message_categoryDelete.php');
    });

$page->write($table->render(new Gibbon\Domain\DataSet($categories)));
