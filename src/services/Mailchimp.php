<?php

namespace barrelstrength\sproutmailchimp\services;

use \DrewM\MailChimp\MailChimp as MailchimpWrapper;
use barrelstrength\sproutmailchimp\SproutMailchimp;
use Craft;
use craft\base\Component;

/**
 *
 * @property Integration[] $allIntegrations
 * @property mixed         $allIntegrationTypes
 */
class Mailchimp extends Component
{
    const STATUS_SUBSCRIBED = 'subscribed';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';
    const STATUS_CLEANED = 'cleaned';
    const STATUS_PENDING = 'pending';
    const STATUS_TRANSACTIONAL = 'transactional';

    /**
     * @return MailchimpWrapper|null
     */
    public function getMailchimp()
    {
        $mailchimp = null;

        try{
            $settings = SproutMailchimp::getInstance()->getSettings();
            $apiKey = Craft::parseEnv($settings->mailchimpApi);
            $mailchimp = new MailchimpWrapper($apiKey);
        }catch (\Exception $e){
            Craft::error($e->getMessage(), __METHOD__);
        }

        return $mailchimp;
    }

    public function getListsAsOptions()
    {
        $options = [];
        $mailchimp = $this->getMailchimp();

        if (is_null($mailchimp)){
            return $options;
        }

        $result = $mailchimp->get('lists');
        if (isset($result['lists']) && $result['lists']){
            foreach ($result['lists'] as $list) {
                $options[] = [
                    'label' => $list['name'],
                    'value' => $list['id']
                ];
            }
        }

        return $options;
    }

    /**
     * @param array $lists
     * @param string $email
     * @param array $fields
     * @param string $status
     * @return |null
     */
    public function subscribeEmailToLists(array $lists, string $email, array $fields = [], $status = self::STATUS_SUBSCRIBED)
    {
        $mailchimp = $this->getMailchimp();
        $result = null;

        if (is_null($mailchimp)){
            return $result;
        }

        $params = [
            'email_address' => $email,
            'status'        => $status
        ];

        if ($fields){
            $params['merge_fields'] = $fields;
        }

        foreach ($lists as $list) {
            $result = $mailchimp->post("lists/{$list}/members", $params);
        }

        return $result;
    }
}
