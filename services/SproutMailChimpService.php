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

	protected $errors = array();

	public function init()
	{
		parent::init();

		$this->settings = $this->getSettings();
        $client = null;

		try {
            $client = new \Mailchimp($this->settings['apiKey']);
        } catch (\Exception $exception) {
		    $this->addError($exception->getMessage());
        }

		$this->client = $client;
	}

	public function addError($message)
    {
        $this->errors[] = $message;
    }

    public function getErrors()
    {
        return $this->errors;
    }
	/**
	 * @return BaseModel
	 */
	public function getSettings()
	{
		$general = craft()->config->get('sproutEmail');

		if ($general != null && isset($general['mailchimp']))
		{
			$settings = $general['mailchimp'];
		}
		else
		{
			$plugin = craft()->plugins->getPlugin('sproutMailChimp');

			$settings = $plugin->getSettings()->getAttributes();
		}

		return $settings;
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

	public function sendCampaignEmail(SproutMailChimp_CampaignModel $mailChimpModel, array $campaignIds)
	{
		if (count($campaignIds))
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

		return array('ids' => $campaignIds, 'emailModel' => $email);
	}

	public function sendTestEmail(SproutMailChimp_CampaignModel $mailChimpModel, $emails, array $campaignIds)
	{
		if (count($campaignIds))
		{
			// Send only one email by getting the first campaign ID for testing purpose only.
			$firstCampaignId = $campaignIds[0];

			try
			{
				$this->client->campaigns->sendTest($firstCampaignId, $emails);
			}
			catch (\Exception $e)
			{
				throw $e;
			}
		}

		$email = new EmailModel();

		$email->subject   = $mailChimpModel->title;
		$email->fromName  = $mailChimpModel->from_name;
		$email->fromEmail = $mailChimpModel->from_email;
		$email->body      = $mailChimpModel->text;
		$email->htmlBody  = $mailChimpModel->html;

		return array('ids' => $campaignIds, 'emailModel' => $email);
	}

	public function createCampaign(SproutMailChimp_CampaignModel $mailChimpModel)
	{
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
				$options['list_id'] = $list;

				if ($this->settings['inlineCss'])
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

		return $campaignIds;
	}

	public function getCampaignIdsIfExists(array $campaignIds = array())
	{
		$apiIds = array();

		if (!empty($campaignIds))
		{
			foreach ($campaignIds as $campaignId)
			{
				// If it returns an error it means campaign has been deleted, Do nothing instead of throwing an error.
				try
				{
					$campaign  = $this->client->campaigns->ready($campaignId);

					if (!empty($campaign) && $campaign['is_ready'] == true)
					{
						$apiIds[] = $campaignId;
					}
				}
				catch (\Exception $exception)
				{

				}
			}
		}

		return $apiIds;
	}

	public function updateCampaignContent($campaignId, $mailChimpModel)
	{
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

		$this->client->campaigns->update($campaignId, 'options', $options);
		$this->client->campaigns->update($campaignId, 'content', $content);
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
