<?php
/**
 * @file
 * PClib mailer driver.
 * Mailer drivers are stored in pclib/system/mail/ directory and loaded by class Mailer automatically.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system\mail;

use pclib\MailMessage;

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