<?php

namespace barrelstrength\sproutmailchimp\models;

use barrelstrength\sproutbase\base\SproutSettingsInterface;
use craft\base\Model;
use Craft;

/**
 *
 * @property array $settingsNavItems
 */
class Settings extends Model implements SproutSettingsInterface
{
    public $apiKey;

    public $inlineCss;

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
        $apiKey = $this->$attribute;

        if (empty($apiKey)) {
            return;
        }

        $client = new \Mailchimp($apiKey, ['ssl_verifypeer' => false]);

        try {
            $result = $client->call('helper/ping', []);
        } catch (\Exception $e) {
            $result = false;
        }

        if (!$result) {
            $message = Craft::t('sprout-mailchimp', 'API key is invalid.');

            $this->addError($attribute, $message);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsNavItems(): array
    {
        return [
            'settingsHeading' => [
                'heading' => Craft::t('sprout-mailchimp', 'Settings'),
            ],
            'general' => [
                'label' => Craft::t('sprout-campaign', 'General'),
                'url' => 'sprout-mailchimp/settings/general',
                'selected' => 'general',
                'template' => 'sprout-mailchimp/_settings/general'
            ]
       ];
    }
}