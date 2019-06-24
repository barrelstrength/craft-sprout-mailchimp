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
}
