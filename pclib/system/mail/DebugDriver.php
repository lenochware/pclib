<?php
/**
 * @file
 * PClib mailer driver.
 * Mailer drivers are stored in pclib/system/mail/ directory and loaded by class Mailer automatically.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

namespace pclib\system\mail;

use pclib\MailMessage;

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/**
 * Mailer driver.
 */
class DebugDriver
{
    protected $options;

    function __construct(array $options)
    {
        $this->options = $options;
    }

    public function send(MailMessage $message)
    {
        print ($message->preview());
    }
}

 ?>