<?php
use Bitrix\Main\Application;
use Bitrix\Main\UrlRewriter;

class bloodknight_leads extends CModule
{
    public $MODULE_ID = 'bloodknight.leads';
    public $MODULE_NAME = 'BloodKnight.leads';
    public $MODULE_DESCRIPTION = 'Регистрация заявок и контроль обработки.';
    private const ROUTE = '/lead/index.php';
    private const EVENTS = [
        ['form', 'onAfterResultAdd', 'onAfterResultAdd'],
        ['main', 'OnBeforeEventAdd', 'onBeforeEventAdd'],
    ];

    public function __construct()
    {
        include __DIR__.'/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    private function routes($install)
    {
        $sites = \Bitrix\Main\SiteTable::getList(['select' => ['LID']]);
        while ($site = $sites->fetch()) {
            $directory = \Bitrix\Main\SiteTable::getDocumentRoot($site['LID']).'/lead';
            $entry = $directory.'/index.php';
            $source = __DIR__.'/lead.php';
            $owned = is_file($entry) && hash_file('sha256', $entry) === hash_file('sha256', $source);

            if ($install && !$owned) {
                if (is_link($directory) || file_exists($entry) || is_link($entry)) {
                    throw new RuntimeException('Путь /lead/index.php уже занят.');
                }
                if ((!is_dir($directory) && !mkdir($directory)) || !copy($source, $entry)) {
                    throw new RuntimeException('Не удалось создать обработчик /lead/index.php.');
                }
            }
            UrlRewriter::delete($site['LID'], ['PATH' => '/local/modules/bloodknight.leads/public/status.php']);
            if (!$install && !$owned) {
                continue;
            }
            UrlRewriter::delete($site['LID'], ['PATH' => self::ROUTE, 'CONDITION' => '#^/lead(?:/|\\?|$)#']);
            if (!$install && unlink($entry) && scandir($directory) === ['.', '..']) {
                rmdir($directory);
            }
            if ($install) {
                UrlRewriter::add($site['LID'], [
                    'CONDITION' => '#^/lead(?:/|\\?|$)#', 'RULE' => '',
                    'ID' => '', 'PATH' => self::ROUTE, 'SORT' => 10,
                ]);
            }
        }
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if (!CModule::IncludeModule('form')) {
            $APPLICATION->ThrowException('Для установки требуется модуль «Веб-формы».');
            return false;
        }
        $db = Application::getConnection();
        $created = false;
        try {
            if (!$db->isTableExists('b_bloodknight_lead')) {
                $db->queryExecute(file_get_contents(__DIR__.'/table.sql'));
                $created = true;
            }
            if (!copy(__DIR__.'/admin.php', $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/bloodknight_leads.php')) {
                throw new RuntimeException('Не удалось создать административную страницу.');
            }
            RegisterModule($this->MODULE_ID);
            \Bitrix\Main\Loader::includeModule($this->MODULE_ID);
            foreach (self::EVENTS as list($module, $event, $method)) {
                RegisterModuleDependences($module, $event, $this->MODULE_ID, 'BloodKnight\Leads\Handler', $method);
            }
            $this->routes(true);
            if (!CAgent::AddAgent(\BloodKnight\Leads\LeadAgent::CALLBACK, $this->MODULE_ID, 'N', 3600)) {
                throw new RuntimeException('Не удалось зарегистрировать агента.');
            }
        } catch (Throwable $error) {
            $this->uninstallLeadResources($created);
            $APPLICATION->ThrowException($error->getMessage());
            return false;
        }
        return true;
    }

    private function uninstallLeadResources($dropTable)
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);
        foreach (self::EVENTS as list($module, $event, $method)) {
            UnRegisterModuleDependences($module, $event, $this->MODULE_ID, 'BloodKnight\Leads\Handler', $method);
        }
        $this->routes(false);
        $stub = $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/bloodknight_leads.php';
        if (is_file($stub)) {
            unlink($stub);
        }
        if ($dropTable) {
            Application::getConnection()->queryExecute('DROP TABLE IF EXISTS b_bloodknight_lead');
        }
        UnRegisterModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        $this->uninstallLeadResources(true);
    }
}
