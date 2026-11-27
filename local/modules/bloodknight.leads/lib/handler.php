<?php
namespace BloodKnight\Leads;

use Bitrix\Main\Config\Option;


final class Handler
{
    public static function onAfterResultAdd($formId, $resultId)
    {
        $identity = ['=RESULT_ID' => (int)$resultId];
        if (LeadTable::getRow(['filter' => $identity])) {
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        try {
            $result = LeadTable::add([
                'CODE' => substr(bin2hex(random_bytes(13)), 0, 25),
                'WEBFORM_ID' => (int)$formId, 'RESULT_ID' => (int)$resultId,
                'IP' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '',
            ]);
            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode(', ', $result->getErrorMessages()));
            }
        } catch (\Throwable $error) {
            if (!LeadTable::getRow(['filter' => $identity])) {
                throw $error;
            }
        }
    }

    public static function onBeforeEventAdd(&$event, &$lid, &$fields)
    {
        if (strpos($event, 'FORM_FILLING_') !== 0 || empty($fields['RS_RESULT_ID'])) {
            return;
        }
        $lead = LeadTable::getRow(['filter' => ['=RESULT_ID' => (int)$fields['RS_RESULT_ID']]]);
        if (!$lead) {
            return;
        }
        $siteId = is_array($lid) ? reset($lid) : explode(',', $lid)[0];
        $site = \CSite::GetByID($siteId)->Fetch();
        $host = trim(($site['SERVER_NAME'] ?? '') ?: Option::get('main', 'server_name', ''));
        if (preg_match('/\A[a-zA-Z0-9.-]+(?::[0-9]+)?\z/', $host)) {
            $fields['STATUS_LINK'] = 'https://'.$host.'/lead/'.$lead['CODE'];
        }
    }
}
