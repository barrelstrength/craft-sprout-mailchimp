<?php

namespace barrelstrength\sproutmailchimp\integrations\sproutemail;

use barrelstrength\sproutbase\app\email\base\Mailer;
use barrelstrength\sproutbase\app\email\base\CampaignEmailSenderInterface;
use barrelstrength\sproutbase\app\email\models\Response;
use barrelstrength\sproutemail\elements\CampaignEmail;
use barrelstrength\sproutemail\models\CampaignType;
use barrelstrength\sproutemail\SproutEmail;
use barrelstrength\sproutmailchimp\models\CampaignModel;
use barrelstrength\sproutmailchimp\SproutMailChimp;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use Craft;

/**
 * Enables you to send your campaigns using MailChimp
 *
 * Class SproutMailChimpMailer
 *
 * @package Craft
 */
class MailChimpMailer extends Mailer implements CampaignEmailSenderInterface
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
        return 'MailChimp';
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
        return Craft::t('sprout-mail-chimp', 'Send your email campaigns via MailChimp.');
    }

    /**
     * @return string
     */
    public function getCpSettingsUrl()
    {
        return UrlHelper::cpUrl('sprout-mail-chimp/settings');
    }


    /**
     * @return \Twig_Markup
     */
    public function getRecipientLists()
    {
        $settings = $this->getSettings();

        $html = Craft::$app->getView()->renderPageTemplate('sproutmailchimp/_settings/plugin', [
            'settings' => $settings
        ]);

        return Template::raw($html);
    }

    /**
     * @param CampaignEmail $campaignEmail
     * @param CampaignType  $campaignType
     *
     * @return Response|mixed
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function sendCampaignEmail(CampaignEmail $campaignEmail, CampaignType $campaignType)
    {
        $response = new Response();

        try {
            $mailChimpModel = $this->prepareMailChimpModel($campaignEmail, $campaignType);

            // MailChimp API does not support updating of campaign if already sent so always create a campaign.
            $campaignIds = SproutMailChimp::$app->createCampaign($mailChimpModel);

            $listsCount = 0;

            if (isset($campaignEmail->listSettings)) {
                $listSettings = json_decode($campaignEmail->listSettings);

                if (!empty($listSettings->listIds)) {
                    $listsCount = count($listSettings->listIds);
                }
            }

            $sentCampaign = SproutMailChimp::$app->sendCampaignEmail($mailChimpModel, $campaignIds);

            if (!empty($sentCampaign['ids'])) {
                SproutEmail::$app->campaignEmails->saveEmailSettings($campaignEmail);
            }

            $response->emailModel = $sentCampaign['emailModel'];

            $response->success = true;
            $response->message = Craft::t('sprout-mail-chimp', 'Campaign successfully sent to {count} recipient lists.', [
                'count' => $listsCount
            ]);
        } catch (\Exception $e) {
            $response->success = false;
            $response->message = $e->getMessage();

            SproutEmail::error($e->getMessage());
        }

        $response->content = Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/response', [
            'email' => $campaignEmail,
            'success' => $response->success,
            'message' => $response->message
        ]);

        return $response;
    }

    /**
     * @todo - change method signature and remove $emails in favor of $campaignEmail->getRecipients()
     *
     * @param CampaignEmail $campaignEmail
     * @param CampaignType  $campaignType
     * @param array         $emails
     *
     * @return Response|null
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function sendTestCampaignEmail(CampaignEmail $campaignEmail, CampaignType $campaignType, array $emails = [])
    {
        $response = new Response();

        try {
            $mailChimpModel = $this->prepareMailChimpModel($campaignEmail, $campaignType);

            $campaignIds = $this->getCampaignIds($campaignEmail, $mailChimpModel);

            if (empty($campaignIds)) {
                $response->success = false;
                $response->message = Craft::t('sprout-mail-chimp', "No lists selected.");
            } else {
                $sentCampaign = SproutMailChimp::$app->sendTestEmail($mailChimpModel, $emails, $campaignIds);

                if (!empty($sentCampaign['ids'])) {
                    SproutEmail::$app->campaignEmails->saveEmailSettings($campaignEmail, [
                        'campaignIds' => $sentCampaign['ids']
                    ]);
                }

                $response->emailModel = $sentCampaign['emailModel'];

                $response->success = true;
                $response->message = Craft::t('sprout-mail-chimp', 'Test Campaign sent to {emails}.', [
                    'emails' => implode(", ", $emails)
                ]);
            }
        } catch (\Exception $e) {
            $response->success = false;
            $response->message = $e->getMessage();

            SproutEmail::error($e->getMessage());
        }

        $response->content = Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/response', [
            'email' => $campaignEmail,
            'success' => $response->success,
            'message' => $response->message
        ]);

        return $response;
    }

    /**
     * @param CampaignEmail $campaignEmail
     * @param CampaignType  $campaignType
     *
     * @return CampaignModel
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    private function prepareMailChimpModel(CampaignEmail $campaignEmail, CampaignType $campaignType)
    {
        $params = [
            'email' => $campaignEmail,
            'campaign' => $campaignType,
            'recipient' => [
                'firstName' => 'First',
                'lastName' => 'Last',
                'email' => 'user@domain.com'
            ],

            // @deprecate - in favor of `email` in v3
            'entry' => $campaignEmail
        ];

        $html = $campaignEmail->getEmailTemplates()->getHtmlBody();
        $text = $campaignEmail->getEmailTemplates()->getTextBody();

        $listSettings = $campaignEmail->listSettings;
        $listSettings = json_decode($listSettings);

        $lists = [];

        if (!empty($listSettings->listIds) && is_array($listSettings->listIds)) {
            $lists = $listSettings->listIds;
        }

        $mailChimpModel = new CampaignModel();
        $mailChimpModel->title = $campaignEmail->title;
        $mailChimpModel->subject = $campaignEmail->title;
        $mailChimpModel->from_name = $campaignEmail->fromName;
        $mailChimpModel->from_email = $campaignEmail->fromEmail;
        $mailChimpModel->lists = $lists;
        $mailChimpModel->html = $html;
        $mailChimpModel->text = $text;

        return $mailChimpModel;
    }

    /**
     * @param CampaignEmail $campaignEmail
     * @param CampaignType  $campaignType
     *
     * @return string
     * @throws \Exception
     */
    public function getPrepareModalHtml(CampaignEmail $campaignEmail, CampaignType $campaignType)
    {
        if (strpos($campaignEmail->replyToEmail, '{') !== false) {
            $campaignEmail->replyToEmail = $campaignEmail->fromEmail;
        }

        $listSettings = json_decode($campaignEmail->listSettings);

        $lists = [];

        if (!isset($listSettings->listIds)) {
            throw new \Exception(Craft::t('sprout-mail-chimp', 'No list settings found. <a href="{cpEditUrl}">Add a list</a>', [
                'cpEditUrl' => $campaignEmail->getCpEditUrl()
            ]));
        }

        if (is_array($listSettings->listIds) && count($listSettings->listIds)) {
            foreach ($listSettings->listIds as $list) {
                $currentList = $this->getListById($list);
                $currentList['members_count'] = $currentList['stats']['member_count'];

                array_push($lists, $currentList);
            }
        }

        return Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/campaigns/prepare-email-snapshot', [
            'mailer' => $this,
            'email' => $campaignEmail,
            'campaignType' => $campaignType,
            'lists' => $lists,
            'canBeTested' => false
        ]);
    }

    /**
     * @param $id
     *
     * @throws \Exception
     * @return array|null
     */
    public function getListById($id)
    {
        $params = ['list_id' => $id];

        try {
            $client = new \Mailchimp($this->settings['apiKey']);

            $lists = $client->lists->getList($params);

            if (isset($lists['data']) && ($list = array_shift($lists['data']))) {
                return $list;
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return null;
    }

    /**
     * @return bool|null|string
     */
    public function getLists()
    {
        try {
            $client = new \Mailchimp($this->settings['apiKey']);

            $lists = $client->lists->getList();

            if (isset($lists['data'])) {
                return $lists['data'];
            }
        } catch (\Exception $e) {
            if ($e->getMessage() == 'API call to lists/list failed: SSL certificate problem: unable to get local issuer certificate') {
                return false;
            } else {
                return $e->getMessage();
            }
        }

        return null;
    }


    /**
     * @param null $values
     *
     * @return null|string
     * @throws \Exception
     */
    public function getListsHtml($values = null)
    {
        $lists = $this->getLists();

        $options = [];
        $selected = [];
        $errors = [];

        if (is_iterable($lists)) {
            foreach ($lists as $list) {
                if (isset($list['id']) && isset($list['name'])) {
                    $length = 0;

                    if ($lists = SproutMailChimp::$app->getListStatsById($list['id'])) {
                        $length = number_format($lists['member_count']);
                    }

                    $listUrl = 'https://us7.admin.mailchimp.com/lists/members/?id='.$list['web_id'];

                    $options[] = [
                        'label' => sprintf('<a target="_blank" href="%s">%s (%s)</a>', $listUrl, $list['name'], $length),
                        'value' => $list['id']
                    ];
                }
            }
        } else {
            if ($lists === false) {
                $errors[] = Craft::t('sprout-mail-chimp', 'Unable to retrieve lists due to an SSL certificate problem: unable to get local issuer certificate. Please contact you server administrator or hosting support.');
            } else {
                $errors[] = Craft::t('sprout-mail-chimp', 'No lists found. Create your first list in MailChimp.');
            }
        }

        if ($values) {
            if (is_array($values)) {
                $listIds = $values['listIds'];
            } else {
                $values = json_decode($values);
                $listIds = $values->listIds;
            }

            if (!empty($listIds)) {
                foreach ($listIds as $value) {
                    $selected[] = $value;
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('sprout-mail-chimp/_settings/lists', [
            'options' => $options,
            'values' => $selected,
            'errors' => $errors
        ]);
    }

    /**
     * @param CampaignEmail $campaignEmail
     *
     * @return array
     */
    public function prepareLists(CampaignEmail $campaignEmail)
    {
        $lists = [];

        return $lists;
    }

    /**
     * @param $campaignEmail
     * @param $mailChimpModel
     *
     * @return array
     * @throws \Exception
     */
    private function getCampaignIds($campaignEmail, $mailChimpModel)
    {
        $campaignIds = [];

        if ($campaignEmail->emailSettings != null AND !empty($campaignEmail->emailSettings['campaignIds'])) {
            $emailSettingsIds = $campaignEmail->emailSettings['campaignIds'];

            if (!empty($emailSettingsIds)) {
                // Make sure campaign is not deleted on mailchimp only include existing ones.
                $campaignIds = SproutMailChimp::$app->getCampaignIdsIfExists($emailSettingsIds);
            }
        }

        if (empty($campaignIds)) {
            $campaignIds = SproutMailChimp::$app->createCampaign($mailChimpModel);
        } else {
            foreach ($campaignIds as $campaignId) {
                SproutMailChimp::$app->updateCampaignContent($campaignId, $mailChimpModel);
            }
        }

        return $campaignIds;
    }
}
