<?php 
/**
 * @file
 * Creating, sending, logging, scheduling e-mail messages.
 * Requires PHPMailer library for sending e-mails.
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

public $sender;

/** var App */
protected $app;

public $db;

protected $table;

protected $options;

public $layout;

function __construct(array $options)
{
    global $pclib;

    parent::__construct();

    $this->app = $pclib->app;

    $defaults = [
        'templates_table' => 'PCLIB_TEMPLATES',
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
    
    $this->trigger('mailer.create', ['sender' => $this->sender]);

    $this->table = $this->options['table'];

    if (!empty($this->options['layout'])) {
        $this->layout = new Layout($this->options['layout']);
    }
}

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

protected function template($id, array $data)
{
    $table = $this->options['templates_table'];
    $path = $this->options['templates_path'];

    if ($table) {
        $this->service('db');
        $page = $this->db->select($table, ['name' => $id]);
        if ($page) {
            $t = new PCTpl;
            $t->loadString($page['body']);
            $t->values = $data;
            return $t;
        }
    }

    $filePath = $path . $id;

    if (!file_exists($filePath)) {
        throw new Exception("Template '$id' not found.");
    }
    
    $t = new PCTpl($filePath);
    $t->values = $data;
    return $t;
}

public function get($id)
{
    $this->service('db');
    $data = $this->db->select($this->table, ['ID' => $id]);
    
    if(!$data) return null;

    $message = new MailMessage;
    $message->load($data);
    return $message;
}

public function schedule($id, array $data = [], array $mailFields = [])
{
    $message = $this->create($id, $data, $mailFields);
    $message->setStatus(MailMessage::STATUS_SCHEDULED);
    return $this->save($message);
}

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

function setLayout($path)
{
    $this->layout = new Layout($path);
}

function setDeveloperOnlyMode($email)
{
    $this->options['developer_only_mode_email'] = $email;
}

}

?>