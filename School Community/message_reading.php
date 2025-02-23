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

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Module\SchoolCommunity\Tables\MessageList;
use Gibbon\Module\SchoolCommunity\Domain\CategoryGateway;
use Gibbon\Domain\School\FacilityGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('查看所有消息'));

if (!isActionAccessible($guid, $connection2, '/modules/' .'School Community' . '/message_reading.php')) {
    //Acess denied
    $page->addError(__('您没有权限访问该页面。'));
} else {

    $search = isset($_GET['search'])? $_GET['search'] : '';

    $form = Form::create('search', $session->get('absoluteURL').'/index.php', 'get');
    $form->setTitle(__('Search'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/message_reading.php');

    $row = $form->addRow();
        $row->addLabel('search', __('查询'))->description(__('标题'));
        $row->addTextField('search')->setValue($search);

    // Get Message Category
    $cGateway = $container->get(CategoryGateway::class);
    $categoryOptions = array_column($cGateway->queryExistCategory()->fetchAll(), 'categoryName', 'categoryID');
    /*
    try {
        echo '<pre>r3r3r3';
        print_r($cGateway->queryExistCategorySQL()->fetchAll());
        echo '</pre>3535';
    } catch (Exception $ex) {
        echo '<pre>r3r3r3';
        print_r($ex);
        echo '</pre>3535';
        $errorMessage = "Database error: " . $ex->getMessage() . "\n";
        $errorMessage .= "In file: " . $ex->getFile() . " on line " . $ex->getLine() . "\n";
        $errorMessage .= "Stack trace:\n" . $ex->getTraceAsString() . "\n";
    
        // 将错误信息记录到日志文件中
        error_log($errorMessage, 3, "./logfile.log");
    }
    */
    
    $row = $form->addRow();
        $row->addLabel('searchCategory', __('查询'))->description(__('消息分类'));
        $row->addSelect('searchCategory')
            ->fromArray($categoryOptions)
            ->placeholder();
    $row = $form->addRow();
        $row->addLabel('searchToOrFrom', __('查询'))->description(__('消息类型'));
        $row->addSelect('searchToOrFrom')
            ->fromArray([
                'r' => '发送给我',
                's' => '我发送的',
            ])
            ->placeholder();

    $row = $form->addRow();
        $row->addSearchSubmit($gibbon->session, __('清空搜索'));

    echo $form->getOutput();

    $cGateway = $container->get(CategoryGateway::class);
    $criteria = $cGateway->newQueryCriteria()
        ->searchBy('i.categoryName', $_GET['search'] ?? '')
        ->fromPost();

    $igrid = $cGateway->queryCategory($criteria);

    $table = DataTable::createPaginated('schoolcommunity', $criteria);
    $table->setTitle(__('查看分类'));

    $table
        ->addHeaderAction('add', __('发送消息'))
        ->setURL('/modules/' . 'School Community' . '/message_sending.php')
        ->addParam('search', $_GET['search'] ?? '')
        ->displayLabel();

}