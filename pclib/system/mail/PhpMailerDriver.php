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
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\DSNConfigurator;

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
        $configurator->configure($mailer, $this->options['dsn']);

        // $mailer->SMTPAuth = true;
        // $mailer->SMTPSecure = 'tls';
        // $mailer->Port = 587;

        return $mailer;
    }
}

 ?>