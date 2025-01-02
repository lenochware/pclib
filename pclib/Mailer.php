<?php 
/**
 * @file
 * Email management and sending.
 * Requires PHPMailer library for sending emails.
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;
use pclib\orm\Model;

/**
 * Creating, sending, logging, scheduling e-mail messages.
 * Requires PHPMailer library for sending e-mails. 
 */
class Mailer extends system\BaseObject implements IService
{

/** Mailer driver used for actual sending emails */
public $sender;

/** var App */
protected $app;

/** var Db */
public $db;

protected $table;

/** Mailer configuration */
protected $options;

/** Layout template. @see setLayout() */
public $layout;

/**
 * Create mailer service.
 * @param $options Mailer configuration - see setOptions() for configuration keys.
 */
function __construct(array $options = [])
{
    global $pclib;

    parent::__construct();

    $this->app = $pclib->app;

    if ($options) {
        $this->setOptions($options);
    }
    else {
        $this->setOptions($this->app->config['service.mailer']);
    }

    $this->trigger('mailer.create', ['sender' => $this->sender]);
}

/*
 * Setup this service from configuration file.
 */
public function setOptions(array $options)
{
    $defaults = [
        'db_templates' => true,
        'templates_path' => 'tpl/mails/',
        'layout' => '',
        'table' => 'PCLIB_MAILS',
        'keep_days' => 10,
        'driver' => 'PhpMailer',
        'from' => 'noreply@mailer.com',
        'dsn' => '',
        'developer_only_mode_email' => false,
    ];

    $this->options = array_replace_recursive($defaults, $options);

    $className = '\\pclib\\system\\mail\\'.ucfirst($this->options['driver']).'Driver';

    if (!class_exists($className)) {
        throw new Exception("Mailer driver '".$this->options['driver']."' not found.");
    }    

    $this->sender = new $className($options);
    
    $this->table = $this->options['table'];

    if (!empty($this->options['layout'])) {
        $this->layout = new Layout($this->options['layout']);
    }
}

/**
 * Send email using template $id.
 * You can use also with MailMessage class: $mailer->send($message);
 * @param string $id Template path or database id
 * @param array $data Template values
 * @param array $mailFields Mail fields (from, to, cc, bcc, subject, replyTo)
 */
public function send($id, array $data = [], array $mailFields = [])
{
	$message = $this->create($id, $data, $mailFields);

    $this->trigger('mailer.before-send', ['message' => $message]);

    if (!$message->isValid()) throw new Exception('Invalid email message.');

    if (!$this->options['developer_only_mode_email']) {
	   $this->sender->send($message);
    }
    else {
        $preview = $message->preview();
        $message->clearRecipients();
        $message->to = $this->options['developer_only_mode_email'];
        $message->body = $preview;
        $this->sender->send($message);
    }

    $this->trigger('mailer.after-send', ['message' => $message]);

    if ($message->status == MailMessage::STATUS_SUBMITTED) {
        $action = 'mail/send';
    }
    else {
        $action = 'mail/failed';
    }

    if ($this->options['keep_days']) {
        $this->save($message);
        $this->clearMessages();
    }

    $itemId = ($id instanceof MailMessage)? $message->subject : $id;
    $to = $message->to[0];

    $this->app->log('mailer', $action, $to, $itemId);
}

/**
 * Create mail message from template $id.
 * @param string $id Template path or database id
 * @param array $data Template values
 * @param array $mailFields Mail fields (from, to, cc, bcc, subject, replyTo)
 * @return MailMessage $message
 */
public function create($id, array $data = [], array $mailFields = [])
{
    if ($id instanceof MailMessage) {
        return $id;
    }

    if (!$data) $data = [];

    $attachments = $mailFields['attachments'] ?? [];
    unset($mailFields['attachments']);

	$t = $this->template($id, $data);
	$t->values = array_merge($data, $mailFields);

    $fields = ['to', 'cc', 'bcc', 'replyTo', 'subject'];

    foreach ($fields as $name) {
        if (isset($data[$name])) throw new Exception("Field name mismatch: " . $name);
        $value = $t->getValue($name);
        if ($value) $mailFields[$name] = $value;
    }

    if ($this->layout) {
        $this->layout->values['CONTENT'] = $t;
        $mailFields['body'] = $this->layout->html();
    }
    else {
        $mailFields['body'] = $t->html();
    }

	$message = new MailMessage($mailFields);
    $message->from = $this->options['from'];

    foreach ($attachments as $value) {
        $message->setAttachment($this->app->path($value));
    }

    foreach ($t->elements as $name => $elem) {
        if ($elem['type'] == 'attachment') {
            $message->setAttachment($this->app->path($t->getValue($name)));
        }
    }

	return $message;
}

/**
 * Create and return mail template.
 * @param string $id Template path or database id
 * @param array $data Template values
 * @return Tpl $template
 */
protected function template($id, array $data)
{
    $path = $this->options['templates_path'];

    if ($this->options['db_templates']) {
        $this->service('db');
        $t = $this->db->template($id, $data);
        if ($t) return $t;
    }

    $filePath = $path . $id;

    if (!file_exists($filePath)) {
        throw new Exception("Template '$id' not found.");
    }
    
    $t = new PCTpl($filePath);
    $t->values = $data;
    return $t;
}

/**
 * Create and save message to be sent later.
 * @param string $id Template path or database id
 * @param array $data Template values
 * @param array $mailFields Mail fields (from, to, cc, bcc, subject, replyTo)
 * @return int $id Database id
 */
public function schedule($id, array $data = [], array $mailFields = [])
{
    $message = $this->create($id, $data, $mailFields);
    $message->setStatus(MailMessage::STATUS_SCHEDULED);
    return $this->save($message);
}

/**
 * Dispatch (send) scheduled messages.
 * @param int $n Send max $n messages in this run
 * @return array $results
 */
public function dispatch($n = null)
{
    $ok = $failed = 0;

    $mails = $this->db->selectAll(
        "select * from {table} where status='{status}' order by created_at
        ~ limit {n}",  
        ['table' => $this->table, 'status' => MailMessage::STATUS_SCHEDULED, 'n' => $n]
    );

    foreach ($mails as $mail) {
        $message = $this->load($mail['id']);
        $this->send($message);
        $this->save($message);

        if ($message->status == MailMessage::STATUS_SUBMITTED) $ok++;
        if ($message->status == MailMessage::STATUS_FAILED) $failed++;
    }

    return ['ok' => $ok, 'failed' => $failed];
}

/**
 * Save message into database.
 * @param MailMessage $message
 * @return int $id Database id
 */
public function save($message)
{
    if (!$message->isValid()) throw new Exception('Invalid email message.');

	$this->service('db');

    $model = Model::create($this->table, [], false);
    
    if ($message->id) {
        $found = $model->find($message->id);
        if (!$found) throw new Exception('Message not found.');
    }

    $recipients = $message->getRecipients();

	$data = [
        'FROM' => $message->from,
        'TO' => $recipients['to'][0],
		'RECIPIENTS' => json_encode($recipients),
		'SUBJECT' => $message->subject,
        'BODY' => $message->body,
        'BODY_TEXT' => $message->text,
		'STATUS' => $message->status,
		'ATTACHMENTS' => json_encode($message->getAttachments()),
        'SEND_AT' => $message->sendAt,
		'CREATED_AT' => date("Y-m-d H:i:s"),
	];

    $model->setValues($data);
    $model->save();

	return $model->ID;
}

/**
 * Load mail message from database.
 * @param int $id Database id
 * @return MailMessage $message
 */
function load($id)
{
    $this->service('db');

    $model = Model::create($this->table, [], false);
    $found = $model->find($id);
    if (!$found) throw new Exception('Message not found.');

    $recipients = json_decode($model->RECIPIENTS, true);
    $attachments = json_decode($model->ATTACHMENTS, true);

    $message = new MailMessage([
        'from' => $model->FROM,
        'to' => $recipients['to'],
        'cc' => $recipients['cc'],
        'bcc' => $recipients['bcc'],
        'replyTo' => $recipients['replyTo'],
        'subject' => $model->SUBJECT,
        'body' => $model->BODY,
        'text' => (string)$model->BODY_TEXT,
    ]);

    $message->id = $model->ID;
    $message->status = $model->STATUS;
    $message->sendAt = $model->SEND_AT;
    $message->createdAt = $model->CREATED_AT;
    $message->setAttachments($attachments);

    return $message;
}

/**
 * Remove submitted messages older than 'keep_days' from database.
 **/
protected function clearMessages()
{
    $this->service('db');

    $keepDays = $this->options['keep_days'];
    if ($keepDays < 0) return;

    $oldest = $this->db->select("select id, created_at FROM {0} where status > 1 ORDER BY id", $this->table);

    if ($oldest) {
        $createdAt = new \DateTime($oldest['created_at']);
        $now = new \DateTime();
        $interval = $now->diff($createdAt);

        if ($interval->days > $keepDays) {
            $this->db->delete($this->table, "status > 1 and (created_at < NOW() - INTERVAL {0} DAY)", $keepDays);
        }
    }    
}

/**
 * Set template layout used by all messages.
 * It works same way as application layout. You can set this by config parameter 'layout' too.
 * @param string $path Path to template.
 **/
function setLayout($path)
{
    $this->layout = new Layout($path);
}

/**
 * When enabled, all messages (preview) are sent not to real address, but to developer $email.
 * You can set this by config parameter 'developer_only_mode_email' too.
 * @param string $email
 **/
function setDeveloperOnlyMode($email)
{
    $this->options['developer_only_mode_email'] = $email;
}

}

?>