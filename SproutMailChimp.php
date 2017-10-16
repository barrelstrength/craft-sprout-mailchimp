<?php

namespace barrelstrength\sproutmailchimp;

use barrelstrength\sproutcore\base\BaseSproutTrait;
use barrelstrength\sproutcore\SproutCoreHelper;
use barrelstrength\sproutmailchimp\services\App;
use craft\base\Plugin;
use Craft;

/**
 * Class SproutMailchimpPlugin
 *
 * @package Craft
 */
class SproutMailChimp extends Plugin
{
	use BaseSproutTrait;

	public $hasSettings = true;

	/**
	 * Enable use of SproutEmail::$plugin-> in place of Craft::$app->
	 *
	 * @var \barrelstrength\sproutmailchimp\services\App
	 */
	public static $app;
	public static $pluginId = 'sprout-email';

	public function init()
	{
		parent::init();
		SproutCoreHelper::registerModule();

		$this->setComponents([
			'app' => App::class
		]);

		$this->hasCpSettings = true;
		$this->hasCpSection  = true;

		self::$app = $this->get('app');
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
	//
	//public function init()
	//{
	//	parent::init();
	//
	//	// Loads the MailChimp library and associated dependencies
	//	require_once dirname(__FILE__) . '/vendor/autoload.php';
	//}
	//
	///**
	// * @return SproutMailChimp_SettingsModel
	// */
	//protected function getSettingsModel()
	//{
	//	return new SproutMailChimp_SettingsModel();
	//}
	//
	///**
	// * @return string
	// */
	//public function getSettingsHtml()
	//{
	//	return craft()->templates->render('sproutmailchimp/_settings/plugin', array(
	//		'settings' => $this->getSettings()
	//	));
	//}
	//
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