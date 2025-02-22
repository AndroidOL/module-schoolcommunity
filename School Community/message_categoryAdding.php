<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\SchoolCommunity\Domain\CategoryGateway;

//Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/' . 'School Community' . '/message_categorySetting.php') == false) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__m('查看分类'), 'message_categorySetting.php');
    $page->breadcrumbs->add(__m('添加分类'));

    $search = $_GET['search'] ?? '' ;

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/' . 'School Community' . '/message_categoryEditting.php&cEntryID=' . $_GET['editID'] . "&search=$search";
    }
    $page->return->setEditLink($editLink);

    if (!empty($search)) {
        $params = [
            "search" => $search
        ];
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('School Community', 'message_categorySetting.php')->withQueryParams($params));
    }

    $form = Form::create('action', $session->get('absoluteURL').'/modules/' . 'School Community' . '/message_categoryAddingProccess.php?search=' . $search);

    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('title', __('分类标题'));
        $row->addTextField('title')->isRequired()->maxLength(100);

    $row = $form->addRow();
        $row->addLabel('staff', __('员工可见？'));
        $row->addYesNo('staff')->isRequired();

    $row = $form->addRow();
        $row->addLabel('student', __('学生可见？'));
        $row->addYesNo('student')->isRequired();

    $row = $form->addRow();
        $row->addLabel('parent', __('家长可见？'));
        $row->addYesNo('parent')->isRequired();

    $row = $form->addRow();
        $row->addLabel('other', __('其他用户可见？'));
        $row->addYesNo('other')->isRequired();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}