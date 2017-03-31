<?php

namespace Craft;

/**
 * Class SproutMailchimpPlugin
 *
 * @package Craft
 */
class SproutMailChimpPlugin extends BasePlugin
{
	public function getName()
	{
		return 'Sprout MailChimp';
	}

	public function getVersion()
	{
		return '0.5.0';
	}

	public function getDeveloper()
	{
		return 'Barrel Strength Design';
	}

	public function getDeveloperUrl()
	{
		return 'http://barrelstrengthdesign.com';
	}

	public function hasCpSection()
	{
		return false;
	}

	public function init()
	{
		parent::init();

		// Loads the MailChimp library and associated dependencies
		require_once dirname(__FILE__) . '/vendor/autoload.php';
	}

	protected function getSettingsModel()
	{
		return new SproutMailChimp_SettingsModel();
	}

	public function getSettingsHtml()
	{
		return craft()->templates->render('sproutmailchimp/_settings/plugin', array(
			'settings' => $this->getSettings()
		));
	}

	public function defineSproutEmailMailers()
	{
		Craft::import("plugins.sproutmailchimp.integrations.sproutemail.SproutMailChimpMailer");

		return array(
			'mailchimp' => new SproutMailChimpMailer()
		);
	}
}

function sproutMailChimp()
{
	return Craft::app()->getComponent('sproutMailChimp');
}