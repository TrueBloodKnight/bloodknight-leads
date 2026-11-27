<?php
define('NO_KEEP_STATISTIC', true);
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use BloodKnight\Leads\Confirmation;
use Bitrix\Main\Loader;


header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
if (!Loader::includeModule('bloodknight.leads')) {
    http_response_code(404);
    exit('Некорректная ссылка.');
}
[$status, $message] = Confirmation::handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
if ($status === 405) {
    header('Allow: GET');
}
http_response_code($status);
echo $message;