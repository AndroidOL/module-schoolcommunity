<?php
namespace Gibbon\Module\SchoolCommunity\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * MessageGateway
 */
class MessageGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'message_totals';

    private static $searchableColumns = ['m.title'];

    public function queryMessages(QueryCriteria $criteria)
    {
        $query = $this->getDefaultTableQuery();

        $criteria->addFilterRules($this->getSharedQueryFilters());

        return $this->runQuery($query, $criteria);
    }

    public function queryMessageCreator(QueryCriteria $criteria)
    {
        $query = $this->getDefaultTableQuery()
            ->innerJoin('gibbonPerson p on p.gibbonPersonID = m.gibbonPersonIDSender');
        
        $query->cols([
            'p.title as senderTitle',
            'p.surname as senderSurname',
            'p.firstname as senderFirstname',
            'p.preferredName as senderPreferredName',
            'p.officialName as senderOfficialName'
        ]);
        
        $criteria->addFilterRules($this->getSharedQueryFilters());
        
        return $this->runQuery($query, $criteria);
    }

    private function getSharedQueryFilters()
    {
        return [
            'sender' => function ($query, $needle) {
                return $query->where("m.gibbonPersonIDSender = :senderID")
                    ->bindValue('senderID', $needle);
            },
        ];
    }
    
    private function getDefaultTableQuery()
    {
        return $this
            ->newQuery()
            ->from($this->getTableName(). ' as m')
            ->cols([
                'm.messageID as messageID',
                'm.title as title',
                'm.body as body',
                'm.priority as priority',
                'm.categoryID as categoryID',
                'm.senderID as gibbonPersonIDSender',
                'm.receiverID as gibbonPersonIDSender',
                'm.sendTimestamp as timestampSent',
                'm.updateTimestamp as timestampUpdated'
            ]);
    }
}
