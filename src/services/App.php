<?php

namespace barrelstrength\sproutmailchimp\services;

use craft\base\Component;

class App extends Component
{
    /**
     * @var Groups
     */
    public $mailchimp;

    public function init()
    {
        $this->mailchimp = new Mailchimp();
    }
}
