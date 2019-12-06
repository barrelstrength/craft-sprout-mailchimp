<?php

namespace barrelstrength\sproutmailchimp\controllers;

use barrelstrength\sproutforms\base\ElementIntegration;
use barrelstrength\sproutforms\base\Integration;
use barrelstrength\sproutforms\fields\formfields\SingleLine;
use Craft;

use craft\errors\MissingComponentException;
use craft\web\Controller as BaseController;
use barrelstrength\sproutforms\SproutForms;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class IntegrationsController extends BaseController
{
    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws MissingComponentException
     */
    public function actionGetMailchimpFields(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $integrationId = Craft::$app->request->getRequiredBodyParam('integrationId');

        /** @var ElementIntegration $integration */
        $integration = SproutForms::$app->integrations->getIntegrationById($integrationId);

        $mailchimpFields = $integration->getMailchimpCustomFieldsAsOptions();
        $entryFieldsByRow = $this->getMailchimpFieldsAsOptionsByRow($mailchimpFields, $integration);

        return $this->asJson([
            'success' => true,
            'fieldOptionsByRow' => $entryFieldsByRow
        ]);
    }

    /**
     * @param             $entryFields
     * @param Integration $integration
     * @param             $entryTypeId
     *
     * @return array
     * @throws InvalidConfigException
     */
    private function getMailchimpFieldsAsOptionsByRow($mailchimpFields, $integration): array
    {
        $fieldMapping = $integration->fieldMapping;

        $formFields = $integration->getSourceFormFieldsAsMappingOptions();
        $rowPosition = 0;
        $finalOptions = [];

        foreach ($formFields as $formField) {
            $optionsByRow = $this->getMailchimpCompatibleFields($mailchimpFields, $formField);
            // We have rows stored and are for the same sectionType
            if ($fieldMapping &&
                isset($fieldMapping[$rowPosition])) {
                foreach ($optionsByRow as $key => $option) {
                    if ($option['value'] == $fieldMapping[$rowPosition]['targetIntegrationField'] &&
                        $fieldMapping[$rowPosition]['sourceFormField'] == $formField['value']) {
                        $optionsByRow[$key]['selected'] = true;
                    }
                }
            }

            $finalOptions[$rowPosition] = $optionsByRow;

            $rowPosition++;
        }

        return $finalOptions;
    }

    /**
     * @param array $mailchimpFields
     * @param array $formField
     *
     * @return array
     */
    private function getMailchimpCompatibleFields(array $mailchimpFields, array $formField): array
    {
        $fieldType = $formField['fieldType'] ?? SingleLine::class;
        $finalOptions = [];

        foreach ($mailchimpFields as $field) {
            $compatibleFields = $field['compatibleFormFields'] ?? '*';
            $option = [
                'label' => $field['label'].' ('.$field['value'].')',
                'value' => $field['value']
            ];

            if (is_array($compatibleFields) &&
                !in_array($fieldType, $compatibleFields, true)) {
                $option = null;
            }

            if ($option) {
                $finalOptions[] = $option;
            }
        }

        return $finalOptions;
    }
}