<?php
namespace Craft;

/**
 * Enables you to send your campaigns using MailChimp
 *
 * Class SproutMailchimpMailer
 *
 * @package Craft
 */
class SproutMailChimpMailer extends SproutEmailBaseMailer implements SproutEmailCampaignEmailSenderInterface
{
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

	public function getCpSettingsUrl()
	{
		return sproutMailChimp()->getSettingsUrl();
	}

	/**
	 * @return array
	 */
	public function getRecipientLists()
	{
		return sproutMailChimp()->getRecipientLists();
	}

	/**
	 * Renders the recipient list UI for this mailer
	 *
	 * @param SproutEmail_CampaignEmailModel[]|null $values
	 *
	 * @return string Rendered HTML content
	 */
	public function getRecipientListsHtml(array $values = null)
	{
		$lists = $this->getRecipientLists();

		$options  = array();
		$selected = array();

		if ($lists === false)
		{
			return craft()->templates->render('sproutmailchimp/lists/sslerror');
		}

		if (!is_array($lists))
		{
			return $lists;
		}

		if (!count($lists))
		{
			return craft()->templates->render('sproutmailchimp/lists/norecipientlists');
		}

		if (count($lists))
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

		if (is_array($values) && count($values))
		{
			foreach ($values as $value)
			{
				$selected[] = $value->list;
			}
		}

		return craft()->templates->renderMacro(
			'_includes/forms', 'checkboxGroup', array(
				array(
					'id'      => 'recipientLists',
					'name'    => 'recipient[recipientLists]',
					'options' => $options,
					'values'  => $selected,
				)
			)
		);
	}

	/**
	 * @param $id
	 *
	 * @return mixed
	 */
	public function getRecipientListById($id)
	{
		return sproutMailChimp()->getRecipientListById($id);
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaign
	 *
	 * @return array|SproutEmail_CampaignEmailModel
	 */
	public function prepareRecipientLists(SproutEmail_CampaignEmailModel $campaignEmail)
	{
		$ids   = craft()->request->getPost('recipient.recipientLists');
		$lists = array();

		if ($ids)
		{
			foreach ($ids as $id)
			{
				$model = new SproutEmail_RecipientListRelationsModel();

				$model->setAttribute('emailId', $campaignEmail->id);
				$model->setAttribute('mailer', $this->getId());
				$model->setAttribute('list', $id);

				$lists[] = $model;
			}
		}

		return $lists;
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

		// Create an array of all recipient list titles
		$lists = sproutEmail()->campaignEmails->getRecipientListsByEmailId($campaignEmail->id);

		$recipientLists = array();

		if (is_array($lists) && count($lists))
		{
			foreach ($lists as $list)
			{
				$current = sproutMailChimp()->getRecipientListById($list->list);

				array_push($recipientLists, $current);
			}
		}

		return craft()->templates->render(
			'sproutmailchimp/sendEmailPrepare',
			array(
				'mailer'         => $this,
				'campaignEmail'  => $campaignEmail,
				'campaignType'   => $campaignType,
				'recipientLists' => $recipientLists,
			)
		);
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return array|void
	 */
	public function sendCampaignEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		$response        = new SproutEmail_ResponseModel();

		try
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

			$lists       = sproutEmail()->campaignEmails->getRecipientListsByEmailId($campaignEmail->id);

			$mailChimpModel = new SproutMailChimp_CampaignModel;
			$mailChimpModel->title      = $campaignEmail->title;
			$mailChimpModel->subject    = $campaignEmail->title;
			$mailChimpModel->from_name  = $campaignEmail->fromName;
			$mailChimpModel->from_email = $campaignEmail->fromEmail;
			$mailChimpModel->lists      = $lists;
			$mailChimpModel->html       = $html;
			$mailChimpModel->text       = $text;

			$sentCampaign = sproutMailChimp()->sendCampaignEmail($mailChimpModel);

			$sentCampaignIds = $sentCampaign['ids'];

			$response->emailModel = $sentCampaign['emailModel'];

			$response->success = true;
			$response->message = Craft::t('Campaign successfully sent to {count} recipient lists.', array(
				'count' => count($sentCampaignIds)
			));
		}
		catch (\Exception $e)
		{
			$response->success = false;
			$response->message = $e->getMessage();

			sproutEmail()->error($e->getMessage());
		}

		$response->content = craft()->templates->render(
			'sproutmailchimp/sendEmailConfirmation',
			array(
				'mailer'  => $campaignEmail,
				'success' => $response->success,
				'message' => $response->message
			)
		);

		return $response;
	}

	public function includeModalResources()
	{
		craft()->templates->includeJsResource('sproutmailchimp/js/mailchimp.js');
	}
}
