<?php
/**
 * Sprout Mailchimp integrations plugin for Craft the Sprout Plugins
 *
 * @link      https://www.barrelstrengthdesign.com/
 * @copyright Copyright (c) 2018 Barrel Strength
 */

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutmailchimp\integrationtypes\MailchimpIntegration;
use barrelstrength\sproutforms\services\Integrations;
use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Plugins;
use barrelstrength\sproutmailchimp\models\Settings;
use yii\base\Event;

class SproutMailchimp extends Plugin
{
    /**
     * @var bool
     */
    public $hasCpSettings = true;

    /**
     * @var bool
     */
    public $hasCpSection = false;

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $projectConfig = Craft::$app->getProjectConfig();
        $pluginHandle = 'sprout-forms';
        $currentSettings = $projectConfig->get(Plugins::CONFIG_PLUGINS_KEY.'.'.$pluginHandle);
        if (isset($currentSettings['enabled']) && $currentSettings['enabled']){
            Event::on(Integrations::class, Integrations::EVENT_REGISTER_INTEGRATIONS, static function(RegisterComponentTypesEvent $event) {
                $event->types[] = MailchimpIntegration::class;
            });
        }
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'sprout-mailchimp/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
