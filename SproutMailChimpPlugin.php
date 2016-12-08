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
		return true;
	}

	protected function defineSettings()
	{
		return array(
			'inlineCss' => array(AttributeType::Bool, 'default' => false),
			'apiKey'    => AttributeType::String
		);
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

/**
 * @return SproutEmailService
 */
function sproutMailChimp()
{
	return Craft::app()->getComponent('sproutMailChimp');
}