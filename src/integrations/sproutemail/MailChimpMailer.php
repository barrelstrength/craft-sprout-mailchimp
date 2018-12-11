<?php

namespace barrelstrength\sproutmailchimp\integrations\sproutemail;

use barrelstrength\sproutbase\app\email\base\EmailElement;
use barrelstrength\sproutbase\app\email\base\Mailer;
use barrelstrength\sproutbase\app\email\base\CampaignEmailSenderInterface;
use barrelstrength\sproutbase\app\email\models\ModalResponse;
use barrelstrength\sproutemail\elements\CampaignEmail;
use barrelstrength\sproutemail\SproutEmail;
use barrelstrength\sproutmailchimp\models\CampaignModel;
use barrelstrength\sproutmailchimp\SproutMailchimp;
use craft\base\Plugin;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use Craft;
use yii\base\Exception;
use yii\swiftmailer\Message;

/**
 * Enables you to send your campaigns using Mailchimp
 *
 * Class MailchimpMailer
 *
 * @package Craft
 *
 * @property \Twig_Markup $recipientLists
 * @property string       $title
 */
class MailchimpMailer extends Mailer implements CampaignEmailSenderInterface
{
    /**
     * @var \Mailchimp
     */
    protected $client;

    /**
     * @throws \Mailchimp_Error
     */
    public function init()
    {
        parent::init();

        /** @var $plugin Plugin */
        $plugin = SproutMailchimp::getInstance();
        $this->settings = $plugin->getSettings();

        if (isset($this->settings['apiKey'])) {
            $apiKey = $this->settings['apiKey'];

            $client = new \Mailchimp($apiKey);

            $this->client = $client;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Mailchimp';
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Mailchimp';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return Craft::t('sprout-mailchimp', 'Send your email campaigns via Mailchimp.');
    }

    /**
     * @return string
     */
    public function getCpSettingsUrl(): string
    {
        return UrlHelper::cpUrl('settings/plugins/sprout-mailchimp');
    }


    /**
     * @return \Twig_Markup
     */
    public function getRecipientLists(): \Twig_Markup
    {
        $settings = $this->getSettings();

        $html = Craft::$app->getView()->renderPageTemplate('sproutmailchimp/_settings/plugin', [
            'settings' => $settings
        ]);

        return Template::raw($html);
    }

    /**
     * @param CampaignEmail $campaignEmail
     *
     * @return ModalResponse|mixed
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function sendCampaignEmail(CampaignEmail $campaignEmail)
    {
        $response = new ModalResponse();

        try {
            $mailChimpModel = $this->prepareMailchimpModel($campaignEmail);

            // @todo Do we really need two methods getting IDs here? This logic got added when
            // merging in the sendTestCampaignEmail in favor of getIsTest()
            // Mailchimp API does not support updating of campaign if already sent so always create a campaign.
            if ($campaignEmail->getIsTest()) {
                $campaignIds = $this->getCampaignIds($campaignEmail, $mailChimpModel);
            } else {
                $campaignIds = $this->createCampaign($mailChimpModel);
            }

            $listsCount = 0;

            if (isset($campaignEmail->listSettings)) {
                $listSettings = Json::decode($campaignEmail->listSettings);

                if (!empty($listSettings->listIds)) {
                    $listsCount = count($listSettings->listIds);
                }
            }

//            if (empty($campaignIds)) {
//                $response->success = false;
//                $response->message = Craft::t('sprout-mailchimp', 'No lists selected.');
//            }

            if (count($campaignIds)) {
                if ($campaignEmail->getIsTest()) {
                    // Test Email
                    // Send only one email by getting the first campaign ID for testing purpose only.
                    $firstCampaignId = $campaignIds[0];

                    try {
                        $this->client->campaigns->sendTest($firstCampaignId, $this->getOnTheFlyRecipients());
                    } catch (\Exception $e) {
                        throw $e;
                    }
                } else {
                    // Live Email
                    foreach ($campaignIds as $mailchimpCampaignId) {
                        try {
                            $this->send($mailchimpCampaignId);
                        } catch (\Exception $e) {
                            throw $e;
                        }
                    }
                }
            }

            $message = new Message();
            $message->setSubject($mailChimpModel->title);
            $message->setFrom([$mailChimpModel->from_email => $mailChimpModel->from_name]);
            $message->setTextBody($mailChimpModel->text);
            $message->setHtmlBody($mailChimpModel->html);

            $sentCampaign['ids'] = $campaignIds;
            $sentCampaign['emailModel'] = $message;

            if (!empty($sentCampaign['ids'])) {
                SproutEmail::$app->campaignEmails->saveEmailSettings($campaignEmail);
            }

            $response->emailModel = $sentCampaign['emailModel'];
            $response->success = true;
            $response->message = Craft::t('sprout-mailchimp', 'Campaign successfully sent to {count} recipient lists.', [
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
     * Sends a previously created/exported campaign via its mail chimp campaign id
     *
     * @param string $mailchimpCampaignId
     *
     * @throws \Exception
     * @return true
     */
    public function send($mailchimpCampaignId)
    {
        try {
            $this->client->campaigns->send($mailchimpCampaignId);

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param CampaignEmail $campaignEmail
     *
     * @return CampaignModel
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    private function prepareMailchimpModel(CampaignEmail $campaignEmail): CampaignModel
    {
        $html = $campaignEmail->getEmailTemplates()->getHtmlBody();
        $text = $campaignEmail->getEmailTemplates()->getTextBody();

        $listSettings = $campaignEmail->listSettings;
        $listSettings = Json::decode($listSettings);

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
     * @param EmailElement|CampaignEmail $email
     *
     * @return string
     * @throws Exception
     * @throws \Twig_Error_Loader
     * @throws \Exception
     */
    public function getPrepareModalHtml(EmailElement $email): string
    {
        if (strpos($email->replyToEmail, '{') !== false) {
            $email->replyToEmail = $email->fromEmail;
        }

        $listSettings = Json::decode($email->listSettings);

        $lists = [];

        if (!isset($listSettings['listIds'])) {
            throw new Exception(Craft::t('sprout-mailchimp', 'No list settings found. <a href="{cpEditUrl}">Add a list</a>', [
                'cpEditUrl' => $email->getCpEditUrl()
            ]));
        }

        if (is_array($listSettings['listIds']) && count($listSettings['listIds'])) {
            foreach ($listSettings['listIds'] as $list) {
                $currentList = $this->getListById($list);
                $currentList['members_count'] = $currentList['stats']['member_count'];

                $lists[] = $currentList;
            }
        }

        return Craft::$app->getView()->renderTemplate('sprout-base-email/_modals/campaigns/prepare-email-snapshot', [
            'mailer' => $this,
            'email' => $email,
            'campaignType' => $email->getCampaignType(),
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
     * @return array
     */
    public function getLists(): array
    {
        try {
            $client = new \Mailchimp($this->settings['apiKey']);

            $lists = $client->lists->getList();

            if (isset($lists['data'])) {
                return $lists['data'];
            }
        } catch (\Exception $e) {
//            if ($e->getMessage() == 'API call to lists/list failed: SSL certificate problem: unable to get local issuer certificate') {
//                return false;
//            }

            Craft::error('sprout-mailchimp', $e->getMessage());
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
                if (isset($list['id'], $list['name'])) {
                    $length = 0;

                    $listStats = null;
                    $params = ['list_id' => $list['id']];

                    try {
                        $lists = $this->client->lists->getList($params);

                        // Get List Stats
                        $listStats = $lists['data'][0]['stats'];
                    } catch (\Exception $e) {
                        throw $e;
                    }

                    if ($listStats) {
                        $length = number_format($listStats['member_count']);
                    }

                    $listUrl = 'https://us7.admin.mailchimp.com/lists/members/?id='.$list['web_id'];

                    $options[] = [
                        'label' => sprintf('<a target="_blank" href="%s">%s (%s)</a>', $listUrl, $list['name'], $length),
                        'value' => $list['id']
                    ];
                }
            }
        } else if ($lists === false) {
            $errors[] = Craft::t('sprout-mailchimp', 'Unable to retrieve lists due to an SSL certificate problem: unable to get local issuer certificate. Please contact you server administrator or hosting support.');
        } else {
            $errors[] = Craft::t('sprout-mailchimp', 'No lists found. Create your first list in Mailchimp.');
        }

        if ($values) {
            if (is_array($values)) {
                $listIds = $values['listIds'];
            } else {
                $values = Json::decode($values);
                $listIds = $values['listIds'];
            }

            if (!empty($listIds)) {
                foreach ($listIds as $value) {
                    $selected[] = $value;
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('sprout-mailchimp/_settings/lists', [
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
    public function prepareLists(CampaignEmail $campaignEmail): array
    {
        return [];
    }

    /**
     * @param $campaignEmail
     * @param $mailChimpModel
     *
     * @return array
     * @throws \Exception
     */
    private function getCampaignIds($campaignEmail, $mailChimpModel): array
    {
        $campaignIds = [];

        if ($campaignEmail->emailSettings != null AND !empty($campaignEmail->emailSettings['campaignIds'])) {
            $emailSettingsIds = $campaignEmail->emailSettings['campaignIds'];

            if (!empty($emailSettingsIds)) {
                // Make sure campaign is not deleted on mailchimp only include existing ones.
                $campaignIds = $this->getCampaignIdsIfExists($emailSettingsIds);
            }
        }

        if (empty($campaignIds)) {
            $campaignIds = $this->createCampaign($mailChimpModel);
        } else {
            foreach ($campaignIds as $campaignId) {
                $this->updateCampaignContent($campaignId, $mailChimpModel);
            }
        }

        return $campaignIds;
    }

    /**
     * @param $campaignId
     * @param $mailChimpModel
     */
    public function updateCampaignContent($campaignId, $mailChimpModel)
    {
        $options = [
            'title' => $mailChimpModel->title,
            'subject' => $mailChimpModel->subject,
            'from_name' => $mailChimpModel->from_name,
            'from_email' => $mailChimpModel->from_email,
            'tracking' => [
                'opens' => true,
                'html_clicks' => true,
                'text_clicks' => false
            ],
        ];

        $content = [
            'html' => $mailChimpModel->html,
            'text' => $mailChimpModel->text
        ];

        $this->client->campaigns->update($campaignId, 'options', $options);
        $this->client->campaigns->update($campaignId, 'content', $content);
    }

    /**
     * @param array $campaignIds
     *
     * @return array
     */
    public function getCampaignIdsIfExists(array $campaignIds = []): array
    {
        $apiIds = [];

        if (!empty($campaignIds)) {
            foreach ($campaignIds as $campaignId) {
                // If it returns an error it means campaign has been deleted, Do nothing instead of throwing an error.
                try {
                    $campaign = $this->client->campaigns->ready($campaignId);

                    if ($campaign !== null && $campaign['is_ready'] == true) {
                        $apiIds[] = $campaignId;
                    }
                } catch (\Exception $exception) {

                }
            }
        }

        return $apiIds;
    }

    /**
     * @param CampaignModel $mailChimpModel
     *
     * @return array
     * @throws \Exception
     */
    public function createCampaign(CampaignModel $mailChimpModel): array
    {
        $lists = $mailChimpModel->lists;

        $campaignIds = [];

        if ($lists && count($lists)) {
            $type = 'regular';
            $options = [
                'title' => $mailChimpModel->title,
                'subject' => $mailChimpModel->subject,
                'from_name' => $mailChimpModel->from_name,
                'from_email' => $mailChimpModel->from_email,
                'tracking' => [
                    'opens' => true,
                    'html_clicks' => true,
                    'text_clicks' => false
                ],
            ];

            $content = [
                'html' => $mailChimpModel->html,
                'text' => $mailChimpModel->text
            ];

            foreach ($lists as $list) {
                $options['list_id'] = $list;

                if ($this->settings['inlineCss']) {
                    $options['inline_css'] = true;
                }

                try {
                    $campaignType = $this->client->campaigns->create($type, $options, $content);

                    $campaignIds[] = $campaignType['id'];
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        }

        return $campaignIds;
    }
}
