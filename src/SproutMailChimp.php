<?php

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbase\app\email\events\RegisterMailersEvent;
use barrelstrength\sproutbase\app\email\services\Mailers;
use barrelstrength\sproutbase\SproutBaseHelper;
use barrelstrength\sproutmailchimp\integrations\sproutemail\MailchimpMailer;
use barrelstrength\sproutmailchimp\models\Settings;
use barrelstrength\sproutmailchimp\services\App;
use craft\base\Plugin;
use Craft;
use craft\web\UrlManager;
use yii\base\Event;
use craft\events\RegisterUrlRulesEvent;

/**
 * Class SproutMailchimpPlugin
 *
 * @package Craft
 *
 * @property mixed $cpNavItem
 * @property array $cpUrlRules
 */
class SproutMailchimp extends Plugin
{
    use BaseSproutTrait;

    public $hasCpSettings = true;
    public $hasCpSection = true;

    /**
     * Enable use of SproutEmail::$plugin-> in place of Craft::$app->
     *
     * @var \barrelstrength\sproutmailchimp\services\App
     */
    public static $app;
    public static $pluginId = 'sprout-mailchimp';

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        SproutBaseHelper::registerModule();

        $this->setComponents([
            'app' => App::class
        ]);

        self::$app = $this->get('app');

        $this->hasCpSettings = true;
        $this->hasCpSection = true;

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getCpUrlRules());
        });

        Event::on(Mailers::class, Mailers::EVENT_REGISTER_MAILER_TYPES, function(RegisterMailersEvent $event) {
            $event->mailers[] = new MailchimpMailer();
        });
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Sprout Mailchimp';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Integrate Mailchimp into your Craft CMS workflow with Sprout Email.';
    }

    /**
     * @return Settings|\craft\base\Model|null
     */
    public function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @return string|null
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('sprout-mailchimp/_settings/general', [
            'settings' => $this->getSettings()
        ]);
    }

    /**
     * @return array
     */
    private function getCpUrlRules(): array
    {
        return [
            'sprout-mailchimp' =>
                'sprout/settings/edit-settings',

            'sprout-mailchimp/settings' =>
                'sprout/settings/edit-settings',

            'sprout-mailchimp/settings/<settingsSectionHandle:.*>' =>
                'sprout/settings/edit-settings'

        ];
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();

        // Allow user to override plugin name in sidebar
        if ($this->getSettings()->pluginNameOverride) {
            $parent['label'] = $this->getSettings()->pluginNameOverride;
        }

        return array_merge($parent, [
            'subnav' => [
                'settings' => [
                    'label' => Craft::t('sprout-mailchimp', 'Settings'),
                    'url' => 'sprout-mailchimp/settings'
                ]
            ]
        ]);
    }
}