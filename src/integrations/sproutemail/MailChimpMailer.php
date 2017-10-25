<?php

namespace barrelstrength\sproutmailchimp\integrations\sproutemail;

use barrelstrength\sproutcore\contracts\sproutemail\BaseMailer;
use barrelstrength\sproutcore\contracts\sproutemail\CampaignEmailSenderInterface;
use barrelstrength\sproutemail\elements\CampaignEmail;
use barrelstrength\sproutemail\models\CampaignTypeModel;
use barrelstrength\sproutemail\models\ResponseModel;
use barrelstrength\sproutmailchimp\SproutMailChimp;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use Craft;

/**
 * Enables you to send your campaigns using MailChimp
 *
 * Class SproutMailchimpMailer
 *
 * @package Craft
 */
class MailChimpMailer extends BaseMailer implements CampaignEmailSenderInterface
{
	public function __construct()
	{
		$this->settings = SproutMailChimp::$app->getSettings();
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
		return SproutMailChimp::t('Send your email campaigns via MailChimp.');
	}

	/**
	 * @return string
	 */
	public function getCpSettingsUrl()
	{
		return UrlHelper::cpUrl('settings/plugins/sprout-mailchimp');
	}


	/**
	 * @return \Twig_Markup
	 */
	public function getRecipientLists()
	{
		$settings = $this->getSettings();

		$html = Craft::$app->getView()->renderPageTemplate('sproutmailchimp/_settings/plugin', array(
			'settings' => $settings
		));

		return Template::raw($html);
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return SproutEmail_ResponseModel
	 */
	public function sendCampaignEmail(CampaignEmail $campaignEmail, CampaignTypeModel $campaignType)
	{
		$response = new ResponseModel();

		try
		{
			$mailChimpModel = $this->prepareMailChimpModel($campaignEmail, $campaignType);

			// MailChimp API does not support updating of campaign if already sent so always create a campaign.
			$campaignIds = sproutMailChimp()->createCampaign($mailChimpModel);

			$sentCampaign = sproutMailChimp()->sendCampaignEmail($mailChimpModel, $campaignIds);

			if (!empty($sentCampaign['ids']))
			{
				sproutEmail()->campaignEmails->saveEmailSettings($campaignEmail);
			}

			$listsCount = 0;
			if (isset($campaignEmail->listSettings) && !empty($campaignEmail->listSettings['listIds']))
			{
				$listsCount = count($campaignEmail->listSettings['listIds']);
			}

			$response->emailModel = $sentCampaign['emailModel'];

			$response->success = true;
			$response->message = Craft::t('Campaign successfully sent to {count} recipient lists.', array(
				'count' => $listsCount
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

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 * @param array                          $emails
	 *
	 * @return SproutEmail_ResponseModel
	 */
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
	public function getPrepareModalHtml(CampaignEmail $campaignEmail, CampaignTypeModel $campaignType)
	{
		if (strpos($campaignEmail->replyToEmail, '{') !== false)
		{
			$campaignEmail->replyToEmail = $campaignEmail->fromEmail;
		}

		$listSettings = $campaignEmail->listSettings;

		$lists = array();

		if (!isset($listSettings['listIds']))
		{
			throw new \Exception(SproutMailChimp::t('No list settings found. <a href="{cpEditUrl}">Add a list</a>', array(
				'cpEditUrl' => $campaignEmail->getCpEditUrl()
			)));
		}

		if (is_array($listSettings['listIds']) && count($listSettings['listIds']))
		{
			foreach ($listSettings['listIds'] as $list)
			{
				$currentList                  = $this->getListById($list);
				$currentList['members_count'] = $currentList['stats']['member_count'];

				array_push($lists, $currentList);
			}
		}

		return Craft::$app->getView()->renderTemplate('sprout-email/_modals/campaigns/prepareEmailSnapshot', array(
			'mailer'       => $this,
			'email'        => $campaignEmail,
			'campaignType' => $campaignType,
			'lists'        => $lists,
			'canBeTested'  => false
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

					if ($lists = SproutMailChimp::$app->getListStatsById($list['id']))
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
				$errors[] = SproutMailChimp::t('Unable to retrieve lists due to an SSL certificate problem: unable to get local issuer certificate. Please contact you server administrator or hosting support.');
			}
			else
			{
				$errors[] = SproutMailChimp::t('No lists found. Create your first list in MailChimp.');
			}
		}

		if ($values)
		{
			if (is_array($values))
			{
				$listIds = $values['listIds'];
			}
			else
			{
				$values = json_decode($values);
				$listIds = $values->listIds;
			}

			if (!empty($listIds))
			{
				foreach ($listIds as $value)
				{
					$selected[] = $value;
				}
			}
		}

		return Craft::$app->getView()->renderTemplate('sprout-mail-chimp/_settings/lists', array(
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
	public function prepareLists(CampaignEmail $campaignEmail)
	{
		$lists = array();

		return $lists;
	}

	private function getCampaignIds($campaignEmail, $mailChimpModel)
	{
		$campaignIds = array();

		if ($campaignEmail->emailSettings != null AND !empty($campaignEmail->emailSettings['campaignIds']))
		{
			$emailSettingsIds = $campaignEmail->emailSettings['campaignIds'];

			if (!empty($emailSettingsIds))
			{
				// Make sure campaign is not deleted on mailchimp only include existing ones.
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
