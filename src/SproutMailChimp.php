<?php

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutbaseemail\events\RegisterMailersEvent;
use barrelstrength\sproutbaseemail\services\Mailers;
use barrelstrength\sproutmailchimp\integrations\sproutemail\MailchimpMailer;
use barrelstrength\sproutmailchimp\models\Settings;
use craft\base\Plugin;
use Craft;
use yii\base\Event;
use craft\web\UrlManager;
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
    /**
     * @var bool
     */
    public $hasSettings = true;

    /**
     * @var bool
     */
    public $hasCpSection = true;

    public function init()
    {
        parent::init();

        Event::on(Mailers::class, Mailers::EVENT_REGISTER_MAILER_TYPES, function(RegisterMailersEvent $event) {
            $event->mailers[] = new MailchimpMailer();
        });

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getCpUrlRules());
        });
    }

    private function getCpUrlRules(): array
    {
        return [
            'sprout-mailchimp/settings/<settingsSectionHandle:.*>' =>
                'sprout/settings/edit-settings',

            'sprout-mailchimp/settings' =>
                'sprout/settings/edit-settings'
        ];
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
     * @return array
     */
    public function getCpNavItem(): array
    {
        $parent = parent::getCpNavItem();

        $navigation = [];

        $navigation['subnav']['settings'] = [
            'label' => Craft::t('sprout-campaign', 'Settings'),
            'url' => 'sprout-mailchimp/settings/general'
        ];

        return array_merge($parent, $navigation);
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
}