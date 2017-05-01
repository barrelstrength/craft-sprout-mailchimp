<?php

namespace Craft;

class SproutMailChimp_CampaignModel extends BaseModel
{
	public function defineAttributes()
	{
		return array(
			'title'      => array(AttributeType::String),
			'subject'    => array(AttributeType::String),
			'from_name'  => array(AttributeType::String),
			'from_email' => array(AttributeType::Email),
			'html'       => array(AttributeType::String),
			'text'       => array(AttributeType::String),
			'lists'      => array(AttributeType::Mixed)
		);
	}
}
