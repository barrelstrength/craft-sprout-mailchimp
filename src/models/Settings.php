<?php

namespace barrelstrength\sproutmailchimp\models;

use barrelstrength\sproutmailchimp\SproutMailChimp;
use craft\base\Model;
use Craft;

/**
 *
 * @property array $settingsNavItems
 */
class Settings extends Model
{
    public $inlineCss;

    public $apiKey;

    public $pluginNameOverride;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = ['apiKey', 'validateApiKey'];

        return $rules;
    }

    /**
     * @param $attribute
     *
     * @throws \Mailchimp_Error
     */
    public function validateApiKey($attribute)
    {
        $value = $this->$attribute;

        if (empty($value)) {
            return;
        }

        $result = SproutMailChimp::$app->validateApiKey($value);

        if (!$result) {
            $message = Craft::t('sprout-mail-chimp', 'API key is invalid.');
            $this->addError($attribute, $message);
        }
    }

    public function getSettingsNavItems(): array
    {
        return [
            'settingsHeading' => [
                'heading' => Craft::t('sprout-mail-chimp', 'Settings'),
            ],
            'general' => [
                'label' => Craft::t('sprout-mail-chimp', 'General'),
                'url' => 'sprout-mail-chimp/settings/general',
                'selected' => 'general',
                'template' => 'sprout-mail-chimp/_settings/general'
            ]
        ];
    }
}