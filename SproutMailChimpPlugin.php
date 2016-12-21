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

	protected function defineSettings()
	{
		return array(
			'inlineCss' => array(AttributeType::Bool, 'default' => false),
			'apiKey'    => array(AttributeType::String, 'required' => true)
		);
	}
	public function getSettingsHtml()
	{
		return craft()->templates->render('sproutmailchimp/settings', array(
			'settings' => $this->getSettings()
		));
	}

	public function registerCpRoutes()
	{
		return array(
			'sproutmailchimp/settings' => array( 'action' => 'sproutMailChimp/editSettings' )
		);
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