<?php
// Run against a disposable copy of Bitrix Standard with a dedicated MariaDB database.
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UrlRewriter;
use BloodKnight\Leads\Confirmation;
use BloodKnight\Leads\Handler;
use BloodKnight\Leads\LeadAgent;
use BloodKnight\Leads\LeadGrid;
use BloodKnight\Leads\LeadTable;

if (PHP_SAPI !== 'cli' || empty($argv[1])) {
    exit(1);
}
$root = realpath($argv[1]);
if (!$root || !is_file($root.'/.bloodknight-test-site')) {
    exit("Expected a marked disposable test site.\n");
}
$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', $root);
$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
$_SERVER += ['HTTP_HOST' => 'example.test', 'SERVER_NAME' => 'example.test',
    'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SERVER_PORT' => 80];
define('NO_AGENT_CHECK', true);
define('NO_KEEP_STATISTIC', true);
define('DisableEventsCheck', true);
$bootstrap = $argv[2] ?? $root.'/bitrix/modules/main/include/prolog_before.php';
require $bootstrap;

function verify(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo "PASS  $message\n";
}

$moduleId = 'bloodknight.leads';
$db = Application::getConnection();
$config = $db->getConfiguration();
if ($config['database'] !== 'bk_leads_test' || ModuleManager::isModuleInstalled($moduleId)
    || $db->isTableExists('b_bloodknight_lead')) {
    fwrite(STDERR, "Expected bk_leads_test without an existing leads installation.\n");
    exit(1);
}
$installer = CModule::CreateModuleObject($moduleId);
$stub = $root.'/bitrix/admin/bloodknight_leads.php';
$moduleDir = $root.'/local/modules/'.$moduleId;
$installed = false;
try {
    verify($installer->DoInstall() === true, 'Installation');
    $installed = true;
    verify(is_file($stub) && Loader::includeModule($moduleId) && $db->isTableExists(LeadTable::getTableName()), 'Admin entry, module loader and table');
    $agent = CAgent::GetList([], ['MODULE_ID' => $moduleId])->Fetch();
    verify($agent && $agent['NAME'] === LeadAgent::CALLBACK && (int)$agent['AGENT_INTERVAL'] === 3600, 'Hourly agent registration');
    $route = '/lead/index.php';
    $rules = UrlRewriter::getList('s1', ['PATH' => $route]);
    verify(count($rules) === 1 && is_file($root.$route), 'URL rewrite and endpoint');

    $sid = 'BK_'.bin2hex(random_bytes(5));
    $event = 'FORM_FILLING_'.$sid;
    $form = $db->add('b_form', ['NAME' => $sid, 'SID' => $sid, 'FIRST_SITE_ID' => 's1', 'MAIL_EVENT_TYPE' => $event]);
    $db->add('b_form_2_site', ['FORM_ID' => $form, 'SITE_ID' => 's1']);
    $db->add('b_form_status', ['FORM_ID' => $form, 'TITLE' => 'New', 'DEFAULT_VALUE' => 'Y']);
    $field = $db->add('b_form_field', ['FORM_ID' => $form, 'TITLE' => 'Message', 'SID' => 'MESSAGE']);
    $answer = $db->add('b_form_answer', ['FIELD_ID' => $field, 'MESSAGE' => ' ', 'FIELD_TYPE' => 'text']);
    $template = $db->add('b_event_message', ['EVENT_NAME' => $event, 'LID' => 's1', 'MESSAGE' => '#STATUS_LINK#']);
    $db->add('b_event_message_site', ['EVENT_MESSAGE_ID' => $template, 'SITE_ID' => 's1']);
    $db->add('b_form_2_mail_template', ['FORM_ID' => $form, 'MAIL_TEMPLATE_ID' => $template]);

    $codes = [];
    for ($i = 0; $i < 22; $i++) {
        $resultId = CFormResult::Add($form, ['form_text_'.$answer => 'Fixture '.$i], 'N');
        $lead = LeadTable::getRow(['filter' => ['=RESULT_ID' => $resultId]]);
        if (!$resultId || !$lead || (int)$lead['WEBFORM_ID'] !== $form || (int)$lead['STATUS_ID'] !== 0
            || $lead['IP'] !== '2001:db8::1' || !($lead['DATE_CREATED'] instanceof DateTime)
            || !preg_match('/^[a-f0-9]{25}$/D', $lead['CODE'])) {
            throw new RuntimeException('Invalid captured result at index '.$i);
        }
        $codes[] = $lead['CODE'];
    }
    Handler::onAfterResultAdd($form, $resultId);
    verify(count(array_unique($codes)) === 22 && LeadTable::getCount() === 22, '22 form submissions, distinct codes, ORM fields and idempotent capture');
    CFormResult::Mail($resultId);
    $mail = $db->query("SELECT C_FIELDS FROM b_event WHERE EVENT_NAME='".$event."' ORDER BY ID DESC")->fetch();
    $fields = $mail ? unserialize($mail['C_FIELDS'], ['allowed_classes' => false]) : [];
    $path = '/lead/'.end($codes);
    verify(($fields['STATUS_LINK'] ?? '') === 'https://example.test'.$path, 'Manager template receives STATUS_LINK in the mail queue');
    verify((bool)preg_match(reset($rules)['CONDITION'], $path), 'Registered rule matches the generated URL');
    verify(Confirmation::handle('HEAD', $path)[0] === 405, 'HEAD does not confirm');
    verify(Confirmation::handle('GET', $path) === [200, 'Заявка обработана.'], 'First visit confirms');
    verify(Confirmation::handle('GET', $path) === [200, 'Заявка уже обработана.'], 'Repeat visit');
    verify(Confirmation::handle('GET', '/lead/broken')[0] === 404
        && Confirmation::handle('GET', '/lead/'.str_repeat('0', 25))[0] === 404, 'Malformed and unknown links');

    $nav = (new PageNavigation('test'))->setPageSize(20);
    $page = function ($status, $order, $number) use ($nav) {
        $nav->setCurrentPage($number);
        return array_map('intval', array_column(LeadGrid::page($status, ['ID' => $order], ['ID'], $nav)->fetchAll(), 'ID'));
    };
    $first = $page('pending', 'ASC', 1);
    $second = $page('pending', 'ASC', 2);
    $desc = $page('pending', 'DESC', 1);
    verify(count($first) === 20 && count($second) === 1 && end($first) < $second[0]
        && $nav->getRecordCount() === 21 && $desc === array_slice(array_reverse(array_merge($first, $second)), 0, 20), 'Pending filter, both sort directions and second page');
    verify($page('done', 'ASC', 1) === [(int)$lead['ID']], 'Processed filter');

    $old = DateTime::createFromTimestamp(time() - 8 * 3600);
    foreach ([$first[0], $lead['ID']] as $id) {
        verify(LeadTable::update($id, ['DATE_CREATED' => $old])->isSuccess(), 'Set boundary fixture '.$id);
    }
    LeadTable::update($first[1], ['DATE_CREATED' => DateTime::createFromTimestamp(time() - 8 * 3600 + 60)]);
    $log = $moduleDir.'/logs/overdue.php';
    $offset = filesize($log);
    verify(LeadAgent::logOverdue() === LeadAgent::CALLBACK, 'Agent reschedules itself');
    $appended = trim(file_get_contents($log, false, null, $offset));
    verify(substr_count($appended, 'lead=') === 1 && strpos($appended, 'lead='.$first[0].' ') !== false, 'Only the overdue pending lead is logged');
} catch (Throwable $e) {
    $failure = $e;
} finally {
    if ($installed) {
        $installer->DoUninstall();
    }
}
if (isset($failure)) {
    fwrite(STDERR, $failure."\n");
    exit(1);
}
verify(!$db->isTableExists('b_bloodknight_lead') && !file_exists($stub) && !file_exists($root.$route)
    && !ModuleManager::isModuleInstalled($moduleId) && !CAgent::GetList([], ['MODULE_ID' => $moduleId])->Fetch()
    && !UrlRewriter::getList('s1', ['PATH' => $route])
    && !$db->query("SELECT ID FROM b_module_to_module WHERE TO_MODULE_ID='bloodknight.leads'")->fetch(), 'Uninstall removes all module resources');
echo "ALL TESTS PASSED\n";
