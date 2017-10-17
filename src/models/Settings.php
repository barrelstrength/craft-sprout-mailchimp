<?php

namespace barrelstrength\sproutmailchimp\models;

use barrelstrength\sproutmailchimp\SproutMailChimp;
use craft\base\Model;

class Settings extends Model
{
	public $inlineCss;

	public $apiKey;

	public function rules()
	{
		$rules   = parent::rules();
		$rules[] = ['apiKey', 'validateApiKey'];

		return $rules;
	}

	public function validateApiKey($attribute)
	{
		$value = $this->$attribute;

		if (empty($value))
		{
			return;
		}

		$result = SproutMailChimp::$app->validateApiKey($value);

		if (!$result)
		{
			$message = SproutMailChimp::t("API key is invalid.");
			$this->addError($attribute, $message);
		}
	}
}