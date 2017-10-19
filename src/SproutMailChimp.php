<?php

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutcore\base\BaseSproutTrait;
use barrelstrength\sproutcore\SproutCoreHelper;
use barrelstrength\sproutmailchimp\models\Settings;
use barrelstrength\sproutmailchimp\services\App;
use craft\base\Plugin;
use Craft;
use craft\web\UrlManager;
use yii\base\Event;
use craft\events\RegisterUrlRulesEvent;
use craft\web\View;
use craft\events\RegisterTemplateRootsEvent;

/**
 * Class SproutMailchimpPlugin
 *
 * @package Craft
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

	public function init()
	{
		parent::init();
		SproutCoreHelper::registerModule();

		$this->setComponents([
			'app' => App::class
		]);

		self::$app = $this->get('app');

		$this->hasCpSettings = true;
		$this->hasCpSection  = true;

		Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
			$event->rules = array_merge($event->rules, $this->getCpUrlRules());
		});
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'Sprout MailChimp';
	}

	/**
	 * @return string
	 */
	public function getDescription()
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
	private function getCpUrlRules()
	{
		return [
			'sprout-mail-chimp'                            =>
				'sprout-core/settings/edit-settings',

			'sprout-mail-chimp/settings'                            =>
				'sprout-core/settings/edit-settings',

			'sprout-mail-chimp/settings/<settingsSectionHandle:.*>' =>
				'sprout-core/settings/edit-settings'

		];
	}

	public function getCpNavItem()
	{
		$parent = parent::getCpNavItem();

		// Allow user to override plugin name in sidebar
		if ($this->getSettings()->pluginNameOverride)
		{
			$parent['label'] = $this->getSettings()->pluginNameOverride;
		}

		return array_merge($parent, [
			'subnav' => [
				'settings' => [
					'label' => SproutMailChimp::t('Settings'),
					'url' => 'sprout-mailchimp/settings'
				]
			]
		]);
	}
	///**
	// * @return array
	// */
	//public function defineSproutEmailMailers()
	//{
	//	Craft::import("plugins.sproutmailchimp.integrations.sproutemail.SproutMailChimpMailer");
	//
	//	return array(
	//		'mailchimp' => new SproutMailChimpMailer()
	//	);
	//}
	//
	///**
	// * Register our default Sprout Lists List Types
	// *
	// * @return array
	// */
	//public function registerSproutListsListTypes()
	//{
	//	Craft::import("plugins.sproutmailchimp.integrations.sproutlists.SproutLists_MailchimpListType");
	//
	//	return array(
	//		new SproutLists_MailchimpListType()
	//	);
	//}
}