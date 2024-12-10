<?php 

use pclib\orm\Model;

//jak vyresit osetreni chyb? parametr throwsException?
//uppercase tabulky?

class Mailer extends pclib\system\BaseObject
{

public $sender;

/** var App */
protected $app;

public $db;

protected $table;

protected $options;

function __construct(array $options)
{
    global $pclib;

    parent::__construct();

    $this->app = $pclib->app;

    $defaults = [
        'templates' => [
            'table' => 'pages',
            'path' => 'tpl/mails/',
        ],

        'save' => [
            'table' => 'mails',
            'keep_days' => 10,
        ],

        'sender' => [
            'driver' => 'PhpMailer',
            'from' => 'noreply@mailer.com',
        ],

        'developer_only_mode_email' => false,
    ];

    $this->options = array_replace_recursive($defaults, $options);

    $className = /*'\\pclib\\system\\database\\'.*/ucfirst($this->options['sender']['driver']).'Driver';

    if (!class_exists($className)) {
        throw new Exception("Mailer driver '".$this->options['sender']['driver']."' not found.");
    }    

    $this->sender = new $className($options);
    
    $this->trigger('mailer.create', ['sender' => $this->sender]);

    $this->table = $this->options['save']['table'];
}

public function send($id, $data = [])
{
	$message = $this->create($id, $data);

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

    if ($message->status == Message::STATUS_SUBMITTED) {
        $action = 'mail/send';
        $message->sendAt = date("Y-m-d H:i:s");
    }
    else {
        $action = 'mail/failed';
    }

    if ($this->options['save']['keep_days']) {
        $this->save($message);
        $this->clearMessages();
    }

    $itemId = ($id instanceof Message)? $message->subject : $id;
    $to = $message->to[0];

    $this->app->log('mailer', $action, $to, $itemId);
}

public function create($id, $data = [])
{
    if ($id instanceof Message) {
        return $id;
    }

	$t = $this->template($id, $data);
	$t->values = $data;

	$data['body'] = $t->html();

	$data['to'] = $t->getValue('to');
	$data['subject'] = $t->getValue('subject');

	$message = new Message($data);
    $message->from = $this->options['sender']['from'];
	return $message;
}

protected function template($id, $data)
{
    $table = $this->options['templates']['table'];
    $path = $this->options['templates']['path'];

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
    $data = $this->db->select($this->table, ['id' => $id]);
    
    if(!$data) return null;

    $message = new Message;
    $message->load($data);
    return $message;
}

public function schedule($id, $data = [])
{
    $message = $this->create($id, $data, $options);
    $message->setStatus(Message::STATUS_SCHEDULED);
    return $this->save($message);
}

public function dispatch($n = null)
{
    $ok = $failed = 0;

    $mails = $this->db->selectAll(
        "select * from {table} where status='{status}' order by created_at
        ~ limit {n}",  
        ['table' => $this->table, 'status' => Message::STATUS_SCHEDULED, 'n' => $n]
    );

    foreach ($mails as $mail) {
        $message = $this->load($mail['id']);
        $this->send($message);
        $this->save($message);

        if ($message->status == Message::STATUS_SUBMITTED) $ok++;
        if ($message->status == Message::STATUS_FAILED) $failed++;
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
        'from' => $message->from,
        'to' => $recipients['to'][0],
		'recipients' => json_encode($recipients),
		'subject' => $message->subject,
        'body' => $message->body,
        'body_text' => $message->text,
		'status' => $message->status,
		'attachments' => json_encode($message->getAttachments()),
        'send_at' => $message->sendAt,
		'created_at' => date("Y-m-d H:i:s"),
	];

    $model->setValues($data);
    $model->save();

	return $model->id;
}

function load($id)
{
    $this->service('db');

    $model = Model::create($this->table, [], false);
    $found = $model->find($id);
    if (!$found) throw new Exception('Message not found.');

    $recipients = json_decode($model->recipients, true);
    $attachments = json_decode($model->attachments, true);

    $message = new Message([
        'from' => $model->from,
        'to' => $recipients['to'],
        'cc' => $recipients['cc'],
        'bcc' => $recipients['bcc'],
        'replyTo' => $recipients['replyTo'],
        'subject' => $model->subject,
        'body' => $model->body,
        'text' => (string)$model->body_text,
    ]);

    $message->id = $model->id;
    $message->status = $model->status;
    $message->sendAt = $model->send_at;
    $message->createdAt = $model->created_at;
    $message->setAttachments($attachments);

    return $message;
}

protected function clearMessages()
{
    $this->service('db');

    $keepDays = $this->options['save']['keep_days'];
    if ($keepDays < 0) return;

    $oldest = $this->db->select("select id, created_at FROM {0} where status > 1 ORDER BY id", $this->table);

    if ($oldest) {
        $createdAt = new DateTime($oldest['created_at']);
        $now = new DateTime();
        $interval = $now->diff($createdAt);

        if ($interval->days > $keepDays) {
            $this->db->delete($this->table, "created_at < NOW() - INTERVAL {0} DAY", $keepDays);
        }
    }    
}

function setDeveloperOnlyMode($email)
{
    $this->options['developer_only_mode_email'] = $email;
}

} //End Mailer

?>