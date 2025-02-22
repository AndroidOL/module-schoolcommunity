<?php
namespace Gibbon\Module\SchoolCommunity\Tables;

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;
use Gibbon\Module\SchoolCommunity\Domain\MessageGateway;

/**
 * MessageList
 */
class MessageList
{
    protected $messageGateway;
    protected $gridRenderer;

    public function __construct(MessageGateway $messageGateway, GridView $gridRenderer)
    {
        $this->messageGateway = $messageGateway;
        $this->gridRenderer = $gridRenderer;
    }

    public function create($roleCategory, $canManage = false)
    {
        // 构建查询条件
        $criteria = $this->messageGateway->newQueryCriteria()
            ->filterBy('is' . $roleCategory, 'Y')
            ->searchBy('title')
            ->sortBy('priority', 'DESC')
            ->sortBy('title')
            ->fromPost();

        // 执行查询
        $messages = $this->messageGateway->queryMessages($criteria);

        // 创建数据表
        $table = DataTable::create('messageList')
            ->setRenderer($this->gridRenderer)
            ->withData($messages);

        // 添加表头操作
        if ($canManage) {
            $table->addHeaderAction('add', __('Create'))
                ->setURL('/modules/' . 'School Community' . '/message_sending.php')
                ->displayLabel();
        }

        // 添加元数据
        $table->addMetaData('gridClass', 'flex items-stretch border rounded bg-blue-50');
        $table->addMetaData('gridItemClass', 'w-full sm:w-1/2 p-4 text-center text-sm leading-normal');

        // 添加列
        $table->addColumn('logo')
            ->format(function ($message) {
                $logo = !empty($message['logo'])
                    ? $message['logo']
                    : 'modules/' . 'School Community' . '/img/anonymous.jpg';
                return Format::link($message['url'], Format::photo(trim($logo, '/'), 140, 'w-full p-1'));
            });

        $table->addColumn('link')
            ->format(function ($message) {
                return Format::link($message['url'], $message['title']);
            });

        return $table;
    }
}
