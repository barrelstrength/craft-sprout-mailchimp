<?php

namespace Craft;

/**
 * Class SproutMailchimpPlugin
 *
 * @package Craft
 */
class SproutMailChimpPlugin extends BasePlugin
{
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

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return '0.6.2';
	}

	/**
	 * @return string
	 */
	public function getSchemaVersion()
	{
		return '0.6.0';
	}

	/**
	 * @return string
	 */
	public function getDeveloper()
	{
		return 'Barrel Strength Design';
	}

	/**
	 * @return string
	 */
	public function getDeveloperUrl()
	{
		return 'http://barrelstrengthdesign.com';
	}

	/**
	 * @return bool
	 */
	public function hasCpSection()
	{
		return false;
	}

	/**
	 *
	 */
	public function init()
	{
		parent::init();

		// Loads the MailChimp library and associated dependencies
		require_once dirname(__FILE__) . '/vendor/autoload.php';
	}

	/**
	 * @return SproutMailChimp_SettingsModel
	 */
	protected function getSettingsModel()
	{
		return new SproutMailChimp_SettingsModel();
	}

	/**
	 * @return string
	 */
	public function getSettingsHtml()
	{
		return craft()->templates->render('sproutmailchimp/_settings/plugin', array(
			'settings' => $this->getSettings()
		));
	}

	/**
	 * @return array
	 */
	public function defineSproutEmailMailers()
	{
		Craft::import("plugins.sproutmailchimp.integrations.sproutemail.SproutMailChimpMailer");

		return array(
			'mailchimp' => new SproutMailChimpMailer()
		);
	}

	/**
	 * Register our default Sprout Lists List Types
	 *
	 * @return array
	 */
	public function registerSproutListsListTypes()
	{
		Craft::import("plugins.sproutmailchimp.integrations.sproutlists.SproutLists_MailchimpListType");

		return array(
			new SproutLists_MailchimpListType()
		);
	}
}

/**
 * @return sproutMailChimpService
 */
function sproutMailChimp()
{
	return Craft::app()->getComponent('sproutMailChimp');
}