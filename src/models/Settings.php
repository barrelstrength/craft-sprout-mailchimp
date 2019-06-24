<?php

namespace barrelstrength\sproutmailchimp\models;

use craft\base\Model;

/**
 *
 * @property array $settingsNavItems
 */
class Settings extends Model
{
    public $mailchimpApi = '';


    public function rules(): array
    {
        return [
            [['mailchimpApi'], 'required']
        ];
    }
}
