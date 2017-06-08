<?php

namespace Craft;

// Loads the MailChimp library and associated dependencies
require_once CRAFT_PLUGINS_PATH . 'sproutmailchimp/vendor/autoload.php';

/**
 * Enables you to send your campaigns using MailChimp
 *
 * Class SproutMailchimpMailer
 *
 * @package Craft
 */
class SproutMailChimpMailer extends SproutEmailBaseMailer implements SproutEmailCampaignEmailSenderInterface
{
	public function __construct()
	{
		$this->settings = sproutMailChimp()->getSettings();
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'mailchimp';
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return 'MailChimp';
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return Craft::t('Send your email campaigns via MailChimp.');
	}

	/**
	 * @return string
	 */
	public function getCpSettingsUrl()
	{
		return UrlHelper::getCpUrl('settings/plugins/sproutmailchimp');
	}

	/**
	 * @return array
	 */
	public function defineSettings()
	{
		return array(
			'inlineCss' => array(AttributeType::Bool, 'default' => false),
			'apiKey'    => array(AttributeType::String, 'required' => true)
		);
	}

	/**
	 * @return \Twig_Markup
	 */
	public function getRecipientLists()
	{
		$settings = isset($settings['settings']) ? $settings['settings'] : $this->getSettings();

		$html = craft()->templates->render('sproutmailchimp/_settings/plugin', array(
			'settings' => $settings
		));

		return TemplateHelper::getRaw($html);
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return SproutEmail_ResponseModel
	 */
	public function sendCampaignEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		$response = new SproutEmail_ResponseModel();

		try
		{
			$mailChimpModel = $this->prepareMailChimpModel($campaignEmail, $campaignType);

			$campaignIds = $this->getCampaignIds($campaignEmail, $mailChimpModel);

			$sentCampaign = sproutMailChimp()->sendCampaignEmail($mailChimpModel, $campaignIds);

			if (!empty($sentCampaign['ids']))
			{
				sproutEmail()->campaignEmails->saveEmailSettings($campaignEmail, array(
					'campaignIds' => $sentCampaign['ids']
				));
			}

			$response->emailModel = $sentCampaign['emailModel'];

			$response->success = true;
			$response->message = Craft::t('Campaign successfully sent to {count} recipient lists.', array(
				'count' => count($sentCampaign['ids'])
			));
		}
		catch (\Exception $e)
		{
			$response->success = false;
			$response->message = $e->getMessage();

			sproutEmail()->error($e->getMessage());
		}

		$response->content = craft()->templates->render('sproutemail/_modals/response', array(
			'email'   => $campaignEmail,
			'success' => $response->success,
			'message' => $response->message
		));

		return $response;
	}

	private function prepareMailChimpModel(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		$params = array(
			'email'     => $campaignEmail,
			'campaign'  => $campaignType,
			'recipient' => array(
				'firstName' => 'First',
				'lastName'  => 'Last',
				'email'     => 'user@domain.com'
			),

			// @deprecate - in favor of `email` in v3
			'entry'     => $campaignEmail
		);

		$html = sproutEmail()->renderSiteTemplateIfExists($campaignType->template, $params);
		$text = sproutEmail()->renderSiteTemplateIfExists($campaignType->template . '.txt', $params);

		$listSettings = $campaignEmail->listSettings;

		$lists = array();

		if (!empty($listSettings['listIds']) && is_array($listSettings['listIds']))
		{
			$lists = $listSettings['listIds'];
		}

		$mailChimpModel             = new SproutMailChimp_CampaignModel();
		$mailChimpModel->title      = $campaignEmail->title;
		$mailChimpModel->subject    = $campaignEmail->title;
		$mailChimpModel->from_name  = $campaignEmail->fromName;
		$mailChimpModel->from_email = $campaignEmail->fromEmail;
		$mailChimpModel->lists      = $lists;
		$mailChimpModel->html       = $html;
		$mailChimpModel->text       = $text;

		return $mailChimpModel;
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return string
	 */
	public function getPrepareModalHtml(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		if (strpos($campaignEmail->replyToEmail, '{') !== false)
		{
			$campaignEmail->replyToEmail = $campaignEmail->fromEmail;
		}

		$listSettings = $campaignEmail->listSettings;

		$lists = array();

		if (!isset($listSettings['listIds']))
		{
			throw new Exception(Craft::t('No list settings found. <a href="{cpEditUrl}">Add a list</a>', array(
				'cpEditUrl' => $campaignEmail->getCpEditUrl()
			)));
		}

		if (is_array($listSettings['listIds']) && count($listSettings['listIds']))
		{
			foreach ($listSettings['listIds'] as $list)
			{
				$currentList = $this->getListById($list);

				array_push($lists, $currentList);
			}
		}

		return craft()->templates->render('sproutemail/_modals/campaigns/prepareEmailSnapshot', array(
			'mailer'       => $this,
			'email'        => $campaignEmail,
			'campaignType' => $campaignType,
			'lists'        => $lists
		));
	}

	/**
	 * @param $id
	 *
	 * @throws \Exception
	 * @return array|null
	 */
	public function getListById($id)
	{
		$params = array('list_id' => $id);

		try
		{
			$client = new \Mailchimp($this->settings['apiKey']);

			$lists = $client->lists->getList($params);

			if (isset($lists['data']) && ($list = array_shift($lists['data'])))
			{
				return $list;
			}
		}
		catch (\Exception $e)
		{
			throw $e;
		}

		return null;
	}

	/**
	 * @return bool|null|string
	 */
	public function getLists()
	{
		try
		{
			$client = new \Mailchimp($this->settings['apiKey']);

			$lists = $client->lists->getList();

			if (isset($lists['data']))
			{
				return $lists['data'];
			}
		}
		catch (\Exception $e)
		{
			if ($e->getMessage() == 'API call to lists/list failed: SSL certificate problem: unable to get local issuer certificate')
			{
				return false;
			}
			else
			{
				return $e->getMessage();
			}
		}

		return null;
	}

	/**
	 * Renders the recipient list UI for this mailer
	 *
	 * @param SproutEmail_CampaignEmailModel[]|null $values
	 *
	 * @return string Rendered HTML content
	 */
	public function getListsHtml($values = null)
	{
		$lists = $this->getLists();

		$options  = array();
		$selected = array();
		$errors   = array();

		if (!empty($lists))
		{
			foreach ($lists as $list)
			{
				if (isset($list['id']) && isset($list['name']))
				{
					$length = 0;

					if ($lists = sproutMailChimp()->getListStatsById($list['id']))
					{
						$length = number_format($lists['member_count']);
					}

					$listUrl = "https://us7.admin.mailchimp.com/lists/members/?id=" . $list['web_id'];

					$options[] = array(
						'label' => sprintf('<a target="_blank" href="%s">%s (%s)</a>', $listUrl, $list['name'], $length),
						'value' => $list['id']
					);
				}
			}
		}
		else
		{
			if ($lists === false)
			{
				$errors[] = Craft::t('Unable to retrieve lists due to an SSL certificate problem: unable to get local issuer certificate. Please contact you server administrator or hosting support.');
			}
			else
			{
				$errors[] = Craft::t('No lists found. Create your first list in MailChimp.');
			}
		}

		if (!empty($values['listIds']) && is_array($values['listIds']))
		{
			foreach ($values['listIds'] as $value)
			{
				$selected[] = $value;
			}
		}

		return craft()->templates->render('sproutmailchimp/_settings/lists', array(
			'options' => $options,
			'values'  => $selected,
			'errors'  => $errors
		));
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 *
	 * @return array
	 */
	public function prepareLists(SproutEmail_CampaignEmailModel $campaignEmail)
	{
		$lists = array();

		return $lists;
	}

	public function sendTestEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType, $emails = array())
	{
		$response = new SproutEmail_ResponseModel();

		try
		{
			$mailChimpModel = $this->prepareMailChimpModel($campaignEmail, $campaignType);

			$campaignIds = $this->getCampaignIds($campaignEmail, $mailChimpModel);

			$sentCampaign = sproutMailChimp()->sendTestEmail($mailChimpModel, $emails, $campaignIds);

			if (!empty($sentCampaign['ids']))
			{
				sproutEmail()->campaignEmails->saveEmailSettings($campaignEmail, array(
					'campaignIds' => $sentCampaign['ids']
				));
			}

			$response->emailModel = $sentCampaign['emailModel'];

			$response->success = true;
			$response->message = Craft::t('Test Campaign sent to {emails}.', array(
				'emails' => implode(", ", $emails)
			));
		}
		catch (\Exception $e)
		{
			$response->success = false;
			$response->message = $e->getMessage();

			sproutEmail()->error($e->getMessage());
		}

		$response->content = craft()->templates->render('sproutemail/_modals/response', array(
			'email'   => $campaignEmail,
			'success' => $response->success,
			'message' => $response->message
		));

		return $response;
	}

	private function getCampaignIds($campaignEmail, $mailChimpModel)
	{
		$campaignIds = array();

		if ($campaignEmail->emailSettings != null AND !empty($campaignEmail->emailSettings['campaignIds']))
		{
			$emailSettingsIds = $campaignEmail->emailSettings['campaignIds'];

			if (!empty($emailSettingsIds))
			{
				$campaignIds = sproutMailChimp()->getCampaignIdsIfExists($emailSettingsIds);
			}
		}

		if (empty($campaignIds))
		{
			$campaignIds = sproutMailChimp()->createCampaign($mailChimpModel);
		}
		else
		{
			foreach ($campaignIds as $campaignId)
			{
				sproutMailChimp()->updateCampaignContent($campaignId, $mailChimpModel);
			}
		}

		return $campaignIds;
	}
}
