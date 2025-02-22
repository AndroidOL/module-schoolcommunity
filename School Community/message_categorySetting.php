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
    $search = isset($_GET['search'])? $_GET['search'] : '';

    $form = Form::create('search', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Search'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/message_categorySetting.php');

    $row = $form->addRow();
        $row->addLabel('search', __('查询'))->description(__('Title'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($gibbon->session, __('清空搜索'));

    echo $form->getOutput();

    $cGateway = $container->get(CategoryGateway::class);
    $criteria = $cGateway->newQueryCriteria()
        ->searchBy('i.title', $_GET['search'] ?? '')
        ->fromPost();

    $igrid = $cGateway->queryCategory($criteria);

    $table = DataTable::createPaginated('schoolcommunity', $criteria);
    $table->setTitle(__('查看分类'));

    $table
        ->addHeaderAction('add', __('添加分类'))
        ->setURL('/modules/' . 'School Community' . '/message_categoryAdding.php')
        ->addParam('search', $_GET['search'] ?? '')
        ->displayLabel();

    $table->addColumn('logo', __('头像'))
        ->width('100px')
        ->format(Format::using('userPhoto', [
            'logo',
            75
        ]));

    $table->addColumn('title', __('姓名'))->format(Format::using('link', ['https://gibbon.tianhao.me/index.php?q=/modules/Staff/staff_view_details.php&gibbonPersonID=0000000001' . 'url','title']));
    $table->addColumn('AccessControl', __('访问控制'));

    $actions = $table->addActionColumn()
        ->addParam('infoGridEntryID')
        ->addParam('search', $_GET['search'] ?? '')
        ->format(function ($infoGridItem, $actions) {
            $actions
                ->addAction('edit', 'Edit')
                ->setURL('/modules/' . 'School Community' . '/message_categoryEditting.php');

            $actions
                ->addAction('delete', 'Delete')
                ->setURL('/modules/' . 'School Community' . '/message_categoryDelete.php');
        });

    echo $table->render($igrid);
}