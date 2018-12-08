<?php

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbase\app\email\events\RegisterMailersEvent;
use barrelstrength\sproutbase\app\email\services\Mailers;
use barrelstrength\sproutbase\SproutBaseHelper;
use barrelstrength\sproutmailchimp\integrations\sproutemail\MailchimpMailer;
use barrelstrength\sproutmailchimp\models\Settings;
use craft\base\Plugin;
use Craft;
use yii\base\Event;

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
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();

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
}