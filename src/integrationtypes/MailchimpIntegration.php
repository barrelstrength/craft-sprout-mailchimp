<?php

namespace barrelstrength\sproutmailchimp\integrationtypes;

use barrelstrength\sproutforms\base\Integration;
use barrelstrength\sproutforms\fields\formfields\Dropdown;
use barrelstrength\sproutforms\fields\formfields\Email;
use barrelstrength\sproutforms\fields\formfields\EmailDropdown;
use barrelstrength\sproutforms\fields\formfields\Name;
use barrelstrength\sproutforms\fields\formfields\SingleLine;
use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\InvalidConfigException;

/**
 * Add a subscriber into lists in Mailchmimp
 */
class MailchimpIntegration extends Integration
{
    /**
     * @var array
     */
    public $lists;

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-mailchimp', 'Mailchimp');
    }

    public function getUpdateTargetFieldsAction()
    {
        return 'sprout-mailchimp/integrations/get-mailchimp-fields';
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidConfigException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSettingsHtml()
    {
        $this->prepareFieldMapping();

        $lists = [];

        return Craft::$app->getView()->renderTemplate('sprout-mailchimp/_components/integrationtypes/mailchimp/settings',
            [
                'integration' => $this,
                'listOptions' => $lists
            ]
        );
    }

    /**
     * @return array
     */
    public function getMailchimpCustomFieldsAsOptions(): array
    {
        $options = [
            [
                'label' => 'Email',
                'value' => 'email',
                'compatibleFormFields' => [
                    Email::class,
                    SingleLine::class,
                    Dropdown::class,
                    EmailDropdown::class
                ]
            ],
            [
                'label' => 'First Name',
                'value' => 'firstName',
                'compatibleFormFields' => [
                    SingleLine::class,
                    Name::class,
                    Dropdown::class
                ]
            ],
            [
                'label' => 'Last Name',
                'value' => 'lastName',
                'compatibleFormFields' => [
                    SingleLine::class,
                    Name::class,
                    Dropdown::class
                ]
            ],
        ];

        return $options;
    }

    /**
     * @inheritDoc
     */
    public function submit(): bool
    {
        if ($this->submitAction == '' || Craft::$app->getRequest()->getIsCpRequest()) {
            return false;
        }

        $entry = $this->entry;
        $fields = $this->resolveFieldMapping();
        $endpoint = $this->submitAction;

        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $message = $entry->formName.' submit action is an invalid URL: '.$endpoint;
            $this->addError('global', $message);
            Craft::error($message, __METHOD__);

            return false;
        }

        $client = new Client();

        Craft::info($fields, __METHOD__);

        $response = $client->post($endpoint, [
            RequestOptions::JSON => $fields
        ]);

        $res = $response->getBody()->getContents();
        $resAsString = is_array($res) ? json_encode($res) : $res;
        $this->successMessage = $resAsString;
        Craft::info($res, __METHOD__);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function resolveFieldMapping(): array
    {
        $fields = [];
        $entry = $this->entry;

        if ($this->fieldMapping) {
            foreach ($this->fieldMapping as $fieldMap) {
                if (isset($entry->{$fieldMap['sourceFormField']}) && $fieldMap['targetIntegrationField']) {
                    $fields[$fieldMap['targetIntegrationField']] = $entry->{$fieldMap['sourceFormField']};
                }
            }
        }

        return $fields;
    }
}

