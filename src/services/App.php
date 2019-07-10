<?php

namespace barrelstrength\sproutmailchimp\services;

use craft\base\Component;

class App extends Component
{
    /**
     * @var Mailchimp
     */
    public $mailchimp;

    public function init()
    {
        $this->mailchimp = new Mailchimp();
    }
}
