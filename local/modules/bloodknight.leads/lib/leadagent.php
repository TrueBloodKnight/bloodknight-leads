<?php
namespace BloodKnight\Leads;

use Bitrix\Main\Type\DateTime;

final class LeadAgent
{
    public const CALLBACK = '\\BloodKnight\\Leads\\LeadAgent::logOverdue();';

    public static function logOverdue(): string
    {
        $rows = LeadTable::getList([
            'select' => ['ID', 'RESULT_ID', 'WEBFORM_ID', 'DATE_CREATED'],
            'filter' => ['=STATUS_ID' => 0, '<=DATE_CREATED' => DateTime::createFromTimestamp(time() - 8 * 3600)],
            'order' => ['ID' => 'ASC'],
        ]);
        $path = dirname(__DIR__).'/logs/overdue.php';
        while ($row = $rows->fetch()) {
            $line = sprintf("%s lead=%d result=%d form=%d created=%s\n", date('c'), $row['ID'],
                $row['RESULT_ID'], $row['WEBFORM_ID'], $row['DATE_CREATED']->format('c'));
            if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Не удалось записать журнал просроченных заявок.');
            }
        }
        return self::CALLBACK;
    }
}
