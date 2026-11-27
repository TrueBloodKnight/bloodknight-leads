<?php
namespace BloodKnight\Leads;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\{IntegerField, StringField, DatetimeField};
use Bitrix\Main\Type\DateTime;

class LeadTable extends DataManager
{
    public static function getTableName()
    {
        return 'b_bloodknight_lead';
    }

    public static function getMap()
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new StringField('CODE'))->configureRequired(),
            (new IntegerField('RESULT_ID'))->configureRequired(),
            (new IntegerField('WEBFORM_ID'))->configureRequired(),
            (new DatetimeField('DATE_CREATED'))->configureDefaultValue(function () { return new DateTime(); }),
            (new IntegerField('STATUS_ID'))->configureDefaultValue(0),
            new StringField('IP'),
        ];
    }
}
