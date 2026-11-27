<?php
namespace BloodKnight\Leads;

use Bitrix\Main\UI\PageNavigation;

final class LeadGrid
{
    public static function page($status, array $sorting, array $fields, PageNavigation $nav)
    {
        $filter = in_array($status, ['pending', 'done'], true) ? ['=STATUS_ID' => (int)($status === 'done')] : [];
        $order = [];
        foreach (array_intersect_key($sorting, array_flip($fields)) as $field => $direction) {
            $order[$field] = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        }
        $order += ['ID' => 'DESC'];
        $nav->setRecordCount(LeadTable::getCount($filter));
        return LeadTable::getList([
            'select' => $fields, 'filter' => $filter, 'order' => $order,
            'limit' => $nav->getLimit(), 'offset' => $nav->getOffset(),
        ]);
    }
}
