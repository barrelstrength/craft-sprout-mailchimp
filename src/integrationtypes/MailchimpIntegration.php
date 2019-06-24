<?php

namespace barrelstrength\sproutmailchimp\integrationtypes;

use barrelstrength\sproutforms\base\Integration;
use barrelstrength\sproutforms\fields\formfields\Dropdown;
use barrelstrength\sproutforms\fields\formfields\Email;
use barrelstrength\sproutforms\fields\formfields\EmailDropdown;
use barrelstrength\sproutforms\fields\formfields\Name;
use barrelstrength\sproutforms\fields\formfields\SingleLine;
use barrelstrength\sproutmailchimp\services\Mailchimp;
use barrelstrength\sproutmailchimp\SproutMailchimp;
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

        $lists = SproutMailchimp::$app->mailchimp->getListsAsOptions();

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
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            return false;
        }

        $fields = $this->resolveFieldMapping();
        $email = $fields['email'];
        $firstName = $fields['firstName'];
        $lastName = $fields['lastName'];
        
        if ($firstName instanceof \barrelstrength\sproutbasefields\models\Name){
            $firstName = $firstName->firstName;
        }

        if ($lastName instanceof \barrelstrength\sproutbasefields\models\Name){
            $lastName = $lastName->lastName;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email: '.$email;
            $this->addError('global', $message);
            Craft::error($message, __METHOD__);

            return false;
        }

        $fields = ['FNAME' => $firstName, 'LNAME' => $lastName];

        $result = SproutMailchimp::$app->mailchimp->subscribeEmailToLists($this->lists, $email, $fields, Mailchimp::STATUS_SUBSCRIBED);
        $resultAsJson = json_encode($result);
        Craft::info("Mailchimp integration submitted: ".$resultAsJson, __METHOD__);

        if ($result['status'] == 400){
            $this->addError('global', $resultAsJson);
            return false;
        }

        $this->successMessage = "Email added to list(s)";

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

