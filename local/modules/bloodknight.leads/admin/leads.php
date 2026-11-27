<?php
use Bitrix\Main\Grid\Options;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use BloodKnight\Leads\LeadGrid;

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ разрешён администраторам.');
}
if (!\Bitrix\Main\Loader::includeModule('bloodknight.leads')) {
    throw new RuntimeException('Модуль заявок не установлен.');
}
$APPLICATION->SetTitle('Заявки веб-форм');
$gridId = 'BLOODKNIGHT_LEADS';
$statuses = [0 => 'Не обработана', 1 => 'Обработана'];
$filterFields = [[
    'id' => 'STATUS_ID', 'name' => 'Статус', 'type' => 'list',
    'items' => ['pending' => $statuses[0], 'done' => $statuses[1]], 'default' => true,
]];
$values = (new FilterOptions($gridId))->getFilter($filterFields);
$status = $values['STATUS_ID'] ?? '';
$labels = [
    'ID' => 'ID', 'RESULT_ID' => 'ID результата', 'WEBFORM_ID' => 'ID формы',
    'DATE_CREATED' => 'Дата поступления', 'STATUS_ID' => 'Статус', 'IP' => 'IP-адрес',
];
$columns = [];
foreach ($labels as $field => $label) {
    $columns[] = ['id' => $field, 'name' => $label, 'sort' => $field, 'default' => true];
}
$options = new Options($gridId);
$sorting = $options->getSorting(['sort' => ['ID' => 'DESC']]);
$nav = new PageNavigation('bloodknight-leads-page');
$nav->setPageSize(20)->initFromUri();
$result = LeadGrid::page($status, $sorting['sort'], array_keys($labels), $nav);
$rows = [];
while ($row = $result->fetch()) {
    $row['DATE_CREATED'] = (string)$row['DATE_CREATED'];
    $row['STATUS_ID'] = $statuses[$row['STATUS_ID']];
    $rows[] = ['id' => $row['ID'], 'columns' => array_map('htmlspecialcharsbx', $row)];
}

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $gridId, 'GRID_ID' => $gridId, 'FILTER' => $filterFields,
    'ENABLE_LIVE_SEARCH' => false, 'ENABLE_LABEL' => true,
]);
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $gridId, 'COLUMNS' => $columns, 'ROWS' => $rows, 'NAV_OBJECT' => $nav,
    'SHOW_ROW_CHECKBOXES' => false, 'SHOW_PAGINATION' => true, 'SHOW_PAGESIZE' => false,
    'AJAX_MODE' => 'Y', 'AJAX_OPTION_JUMP' => 'N', 'AJAX_OPTION_HISTORY' => 'N',
]);
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
