<?php

namespace barrelstrength\sproutmailchimp\models;

use craft\base\Model;

class CampaignModel extends Model
{
    public $title;
    public $subject;
    public $from_name;
    public $from_email;
    public $html;
    public $text;
    public $lists;
}
