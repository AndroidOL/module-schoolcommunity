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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\SchoolCommunity\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * CategoryGateway Gateway
 *
 * @version v18
 * @since   v18
 */
class CategoryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'message_category';

    private static $searchableColumns = ['i.categoryName'];

    public function queryCategory(QueryCriteria $criteria) {
        $query = $this->getDefaultTableQuery();
        $criteria->addFilterRules($this->getSharedQueryFilters());
        return $this->runQuery($query, $criteria);
    }

    public function queryCategoryCreator(QueryCriteria $criteria) {
        $query = $this->getDefaultTableQuery()
            ->innerJoin('gibbonPerson p on p.gibbonPersonID = i.gibbonPersonIDCreator');
        
        $query->cols([
            'p.title as creatorTitle',
            'p.surname as creatorSurname',
            'p.firstname as creatorFirstname',
            'p.preferredName as creatorPreferredName',
            'p.officialName as creatorOfficialName'
        ]);
        
        $criteria->addFilterRules($this->getSharedQueryFilters());
        
        return $this->runQuery($query, $criteria);
    }

    public function queryCategoryID(QueryCriteria $criteria) {
        $query = $this->getDefaultTableQuery()->where('message_category.categoryID = :CategoryID')
        ->bindValue('gibbonPersonID', $gibbonPersonID);
        return $this->runQuery($query, $criteria);
    }

    private function getSharedQueryFilters() {
        return [
            'creator' => function ($query, $needle) {
                return $query->where("i.gibbonPersonIDCreator = :creatorID")
                    ->bindValue('creatorID', $needle);
            },
        ];
    }
    
    private function getDefaultTableQuery() {
        return $this->newQuery()
            ->from($this->getTableName() . ' as i')
            ->innerJoin('gibbonPerson', 'i.userID = gibbonPersonID')
            ->orderBy(['i.createTime'])
            ->cols([
                'i.categoryID as CategoryID',
                'i.categoryName as CategoryName',
                'i.userID as gibbonPersonIDCreator',
                'CONCAT("/index.php?q=/modules/Staff/staff_view_details.php&gibbonPersonID=", i.userID) as gibbonPersonIDURL',
                'gibbonPerson.surname as gibbonPersonSurname',
                'gibbonPerson.firstName as gibbonPersonFirstName',
                'CONCAT(gibbonPerson.surname, " ", gibbonPerson.firstName) as gibbonPersonName',
                'i.accessControl as AccessControl',
                'i.createTime as TimeCreated',
                'i.updateTime as TimeUpdated',
            ]);
    }
}