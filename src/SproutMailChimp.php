<?php

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbase\app\email\events\RegisterMailersEvent;
use barrelstrength\sproutbase\app\email\services\Mailers;
use barrelstrength\sproutbase\SproutBaseHelper;
use barrelstrength\sproutmailchimp\integrations\sproutemail\MailChimpMailer;
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
class SproutMailChimp extends Plugin
{
    use BaseSproutTrait;

    public $hasSettings = true;
    public $hasCpSection = true;

    /**
     * Enable use of SproutEmail::$plugin-> in place of Craft::$app->
     *
     * @var \barrelstrength\sproutmailchimp\services\App
     */
    public static $app;
    public static $pluginId = 'sprout-mail-chimp';

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
            $event->mailers[] = new MailChimpMailer();
        });
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Sprout MailChimp';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Integrate MailChimp into your Craft CMS workflow with Sprout Email.';
    }

    public function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @return array
     */
    private function getCpUrlRules(): array
    {
        return [
            'sprout-mail-chimp' =>
                'sprout/settings/edit-settings',

            'sprout-mail-chimp/settings' =>
                'sprout/settings/edit-settings',

            'sprout-mail-chimp/settings/<settingsSectionHandle:.*>' =>
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
                    'label' => Craft::t('sprout-mail-chimp', 'Settings'),
                    'url' => 'sprout-mail-chimp/settings'
                ]
            ]
        ]);
    }
}