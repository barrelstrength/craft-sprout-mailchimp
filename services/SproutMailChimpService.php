<?php
namespace Craft;

/**
 * Class SproutMailchimpService
 *
 * @package Craft
 */
class SproutMailChimpService extends BaseApplicationComponent
{
	/**
	 * @var Model
	 */
	protected $settings;

	/**
	 * @var \Mailchimp
	 */
	protected $client;

	public function init()
	{
		parent::init();

		$this->settings = craft()->plugins->getPlugin('sproutMailChimp')->getSettings();

		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		$this->client = $client;
	}

	public function getListStatsById($id)
	{
		$params = array('list_id' => $id);

		try
		{
			$lists = $this->client->lists->getList($params);

			$stats = $lists['data'][0]['stats'];

			return $stats;
		}
		catch (\Exception $e)
		{
			throw $e;
		}
	}

	public function sendCampaignEmail(SproutMailChimp_CampaignModel $mailChimpModel, $sendOnExport = true)
	{
		// @todo - refactor to use new $listSettings
		$lists = $mailChimpModel->lists;

		$campaignIds = array();

		if ($lists && count($lists))
		{
			$type    = 'regular';
			$options = array(
				'title'      => $mailChimpModel->title,
				'subject'    => $mailChimpModel->subject,
				'from_name'  => $mailChimpModel->from_name,
				'from_email' => $mailChimpModel->from_email,
				'tracking'   => array(
					'opens'       => true,
					'html_clicks' => true,
					'text_clicks' => false
				),
			);

			$content = array(
				'html' => $mailChimpModel->html,
				'text' => $mailChimpModel->text
			);

			foreach ($lists as $list)
			{
				$options['list_id'] = $list->list;

				if ($this->settings->inlineCss)
				{
					$options['inline_css'] = true;
				}

				try
				{
					$campaignType  = $this->client->campaigns->create($type, $options, $content);
					$campaignIds[] = $campaignType['id'];

					$this->info($campaignType);
				}
				catch (\Exception $e)
				{
					throw $e;
				}
			}
		}

		if (count($campaignIds) && $sendOnExport)
		{
			foreach ($campaignIds as $mailchimpCampaignId)
			{
				try
				{
					$this->send($mailchimpCampaignId);
				}
				catch (\Exception $e)
				{
					throw $e;
				}
			}
		}

		$email = new EmailModel();

		$email->subject   = $mailChimpModel->title;
		$email->fromName  = $mailChimpModel->from_name;
		$email->fromEmail = $mailChimpModel->from_email;
		$email->body      = $mailChimpModel->text;
		$email->htmlBody  = $mailChimpModel->html;

		if (!empty($recipients))
		{
			$email->toEmail = implode(', ', $recipients);
		}

		return array('ids' => $campaignIds, 'emailModel' => $email);
	}

	/**
	 * Sends a previously created/exported campaign via its mail chimp campaign id
	 *
	 * @param string $mailchimpCampaignId
	 *
	 * @throws \Exception
	 * @return true
	 */
	public function send($mailchimpCampaignId)
	{
		try
		{
			$this->client->campaigns->send($mailchimpCampaignId);

			return true;
		}
		catch (\Exception $e)
		{
			throw $e;
		}
	}

	public function validateApiKey($apiKey)
	{
		$result = false;

		$client = new \Mailchimp($apiKey, array('ssl_verifypeer' => false));

		try
		{
			$result = $client->call('helper/ping', array());
		}
		catch (\Exception $e)
		{
			$result = false;
		}

		return $result;
	}

	public function info($message, array $variables = array())
	{
		if (is_string($message))
		{
			$message = Craft::t($message, $variables);
		}
		else
		{
			$message = print_r($message, true);
		}

		SproutMailChimpPlugin::log($message, LogLevel::Info);
	}
}
