<?php

namespace Craft;

class SproutMailChimp_SettingsModel extends BaseModel
{
	public function defineAttributes()
	{
		return array(
			'inlineCss' => array(AttributeType::Bool, 'default' => false),
			'apiKey'    => array(AttributeType::String, 'required' => true)
		);
	}

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

		$result = sproutMailChimp()->validateApiKey($value);

		if (!$result)
		{
			$message = Craft::t("API key is invalid.");
			$this->addError($attribute, $message);
		}
	}
}