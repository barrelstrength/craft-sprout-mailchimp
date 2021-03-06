<?php

namespace Craft;

class SproutLists_MailchimpListType extends SproutListsBaseListType
{
	private $settings = null;

	public function __construct()
	{
		$this->settings = $this->getSettings();
	}
	/**
	 * @return BaseModel
	 */
	public function getSettings()
	{
		$plugin = craft()->plugins->getPlugin('sproutMailChimp');

		return $plugin->getSettings();
	}

	/**
	 * Subscribe a user to a list for this List Type
	 *
	 * @param $subscription
	 *
	 * @return bool
	 */
	public function subscribe($subscription)
	{
		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		try
		{
			$lists = $client->lists
				->subscribe($subscription->listId, array('email' => $subscription->email), null, 'html', true);
		}
		catch (\Exception $exception)
		{
			sproutLists()->addError($exception->getMessage());
		}

		if (!empty($lists))
		{
			return true;
		}

		return false;
	}

	/**
	 * Unsubscribe a user from a list for this List Type
	 *
	 * @param $user
	 *
	 * @return mixed
	 */
	public function unsubscribe($subscription)
	{
		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));
		try
		{
			$lists = $client->lists
				->unsubscribe($subscription->listId, array('email' => $subscription->email), false, false);
		}
		catch (\Exception $exception)
		{
			sproutLists()->addError($exception->getMessage());
		}

		if (!empty($lists))
		{
			return true;
		}

		return false;
	}

	/**
	 * Check if a user is subscribed to a list
	 *
	 * @param $criteria
	 *
	 * @return mixed
	 */
	public function isSubscribed($subscription)
	{
		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		$email = $subscription->email;

		if (!is_array($email))
		{
			$email = array('email' => $email);
		}

		try
		{
			$members = $client->lists->memberInfo($subscription->listId, array($email));
		}
		catch (\Exception $exception)
		{
			sproutLists()->addError($exception->getMessage());
		}

		$subscriber = null;

		$result = false;

		if (!empty($members['data']))
		{
			$subscriber = $members['data'][0];

			if ($subscriber)
			{
				if ($subscriber['status'] == 'subscribed')
				{
					$result = true;
				}
			}
		}

		return $result;
	}

	/**
	 * Return all lists for a given subscriber.
	 *
	 * @param $subscriber
	 *
	 * @return array
	 */
	public function getLists($subscriber)
	{
		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		$lists = array();

		if ($subscriber->email == null)
		{
			$result = $client->lists->getList();

			if (!empty($result['data']))
			{
				foreach ($result['data'] as $key => $data)
				{
					$lists[$key]['list_id']   = $data['id'];
					$lists[$key]['list_name'] = $data['name'];
				}
			}
		}
		else
		{
			$result = $client->helper->searchMembers($subscriber->email);

			if (!empty($result['exact_matches']['members']))
			{
				$count = 0;

				foreach ($result['exact_matches']['members'] as $member)
				{
					$status = $member['status'];

					// Only subscribed members are returned
					if ($status == 'subscribed')
					{
						$lists[$count]['list_id'] = $member['list_id'];
						$lists[$count]['list_name'] = $member['list_name'];
					}

					$count++;
				}
			}
		}

		return $lists;
	}

	/**
	 * @param null $subscriber
	 *
	 * @return int
	 */
	public function getListCount($subscriber = null)
	{
		$lists = $this->getLists($subscriber);

		return count($lists);
	}

	/**
	 * Get subscribers on a given list.
	 *
	 * @param $list
	 *
	 * @return mixed
	 * @internal param $criteria
	 *
	 */
	public function getSubscribers($list)
	{
		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		$subscribers = array();

		$members = $client->lists->members($list->handle);

		if (!empty($members['data']))
		{
			foreach ($members['data'] as $key => $member)
			{
				$names = array();

				$names['firstName'] = $member['merges']['FNAME'];
				$names['lastName']  = $member['merges']['LNAME'];

				$subscribers[$key] = array_merge($member, $names);
			}
		}

		return $subscribers;
	}

	public function getSubscriberCount($list)
	{
		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		$members = $client->lists->members($list->handle);

		return $members['total'];
	}
}