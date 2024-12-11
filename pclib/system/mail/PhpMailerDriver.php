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
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\DSNConfigurator;

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/**
 * PHPMailer driver.
 * Implements support of PHPMailer library. PHMailer library is required for this driver.
 */
class PhpMailerDriver
{
    protected $options;

    function __construct(array $options)
    {
        $this->options = $options;
    }

    public function send(MailMessage $message)
    {
        $mail = $this->createMailer();

				$mail->Subject = $message->subject;

				$mail->setFrom($message->parseEmail($message->from), $message->parseName($message->from));

				foreach ($message->to as $value) {
					$mail->addAddress($message->parseEmail($value), $message->parseName($value));
				}

				foreach ($message->cc as $value) {
					$mail->addCC($message->parseEmail($value), $message->parseName($value));
				}

				foreach ($message->bcc as $value) {
					$mail->addBCC($message->parseEmail($value), $message->parseName($value));
				}

				foreach ($message->replyTo as $value) {
					$mail->addReplyTo($message->parseEmail($value), $message->parseName($value));
				}
				foreach ($message->getAttachments() as $value) {
					$mail->addAttachment($value);
				}

        $mail->msgHTML($message->body);

        $ok = $mail->send();

        $message->status = $ok? MailMessage::STATUS_SUBMITTED : MailMessage::STATUS_FAILED;
        $message->sendAt = date("Y-m-d H:i:s");

        return $ok;
    }

    protected function createMailer()
    {
        $mailer = new PHPMailer(true);

        //$mailer->SMTPDebug = 4;
        $mailer->isSMTP();
        $mailer->CharSet = 'utf-8';
        $mailer->Encoding = 'quoted-printable';
        $mailer->isHTML(true);

        $configurator = new DSNConfigurator();
        $configurator->configure($mailer, $this->options['sender']['dsn']);

        // $sender = $this->options['sender'];

        // $mailer->Host = $sender['host'];
        // $mailer->SMTPAuth = true;
        // $mailer->Username = $sender['username'];
        // $mailer->Password = $sender['password'];
        // $mailer->SMTPSecure = 'tls';
        // $mailer->Port = 587;
        // //$mailer->setFrom($sender['from']);

        return $mailer;
    }
}

 ?>