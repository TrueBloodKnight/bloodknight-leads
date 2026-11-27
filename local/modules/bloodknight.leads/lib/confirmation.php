<?php
namespace BloodKnight\Leads;

use Bitrix\Main\Application;

final class Confirmation
{
    public static function handle(string $method, string $uri): array
    {
        if ($method !== 'GET') {
            return [405, 'Для подтверждения откройте ссылку из письма.'];
        }
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || !preg_match('~^/lead/([a-f0-9]{25})/?$~D', $path, $match)) {
            return [404, 'Некорректная ссылка.'];
        }

        $db = Application::getConnection();
        $db->queryExecute("UPDATE ".LeadTable::getTableName()." SET STATUS_ID=1 WHERE STATUS_ID=0 AND CODE='".$match[1]."'");
        if ($db->getAffectedRowsCount() > 0) {
            return [200, 'Заявка обработана.'];
        }
        if (LeadTable::getRow(['filter' => ['=CODE' => $match[1]]])) {
            return [200, 'Заявка уже обработана.'];
        }
        return [404, 'Некорректная ссылка.'];
    }
}
