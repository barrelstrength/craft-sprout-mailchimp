<?php

namespace barrelstrength\sproutmailchimp\models;

use barrelstrength\sproutmailchimp\SproutMailchimp;
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

        $result = SproutMailchimp::$app->validateApiKey($value);

        if (!$result) {
            $message = Craft::t('sprout-mailchimp', 'API key is invalid.');
            $this->addError($attribute, $message);
        }
    }

    public function getSettingsNavItems(): array
    {
        return [
            'settingsHeading' => [
                'heading' => Craft::t('sprout-mailchimp', 'Settings'),
            ],
            'general' => [
                'label' => Craft::t('sprout-mailchimp', 'General'),
                'url' => 'sprout-mailchimp/settings/general',
                'selected' => 'general',
                'template' => 'sprout-mailchimp/_settings/general'
            ]
        ];
    }
}