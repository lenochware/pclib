<?php 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\DSNConfigurator;

class PhpMailerDriver
{
    protected $options;

    function __construct(array $options)
    {
        $this->options = $options;
    }

    public function send(Message $message)
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

        return $mail->send();
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