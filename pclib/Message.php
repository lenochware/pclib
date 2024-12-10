<?php 
/**
 * @file
 * Email message - used by Mailer class.
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

class Message
{
    const STATUS_NEW = 0;
    const STATUS_SCHEDULED = 1;
    const STATUS_SUBMITTED = 2;
    const STATUS_FAILED = 9;

    protected $from = '';
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $replyTo = [];
    
    protected $attachments = [];
    protected $subject = '';
    protected $body = '';
    protected $text = '';

    public $id = null; //je ulozeno v db?
    public $status;
    public $sendAt;
    public $createdAt;

    protected $fields = ['from', 'to', 'cc', 'bcc', 'replyTo', 'subject', 'body', 'text'];

    public function __construct(array $data = [])
    {
        $this->status = self::STATUS_NEW;

        foreach ($data as $name => $value) {
        	$this->set($name, $value);
        }
    }

    public function set($name, $value)
    {
    	if (!in_array($name, $this->fields)) {
    		throw new Exception("Invalid field name: '$name'.");
    	}

    	if (in_array($name, ['subject', 'body', 'text'])) {
    		if (!is_string($value)) throw new Exception("Invalid value '$value'.");
    		$this->$name = $value;
    		return $this;
    	}

    	if ($name == 'from') {
    		if (!$this->isValidEmail($value))  throw new Exception("Invalid email address: '$value'");
    		$this->$name = $value;
    		return $this;
    	}

    	if (!is_array($value)) $value = [$value];

    	foreach ($value as $email) {
    		if (!$this->isValidEmail($email))  throw new Exception("Invalid email address: '$email'");
    	}

    	$this->$name = $value;

    	return $this;
    }

    public function get($name)
    {
    	if (!in_array($name, $this->fields)) {
    		throw new Exception("Invalid field name: '$name'.");
    	}

    	return $this->$name;
    }

    public function add($name, $value)
    {
    	if (!in_array($name, ['to', 'cc', 'bcc', 'replyTo'])) {
    		throw new Exception("Invalid field name: '$name'.");
    	}


    	if (!is_array($value)) $value = [$value];

    	foreach ($value as $email) {
    		if (!$this->isValidEmail($email))  throw new Exception("Invalid email address: '$email'");
    	}

    	$this->$name = array_merge($this->$name, $value);

    	return $this;
    }

    public function __get($name)
		{
			return $this->get($name);
		}

		/**
		 * PHP magic method.
		 * Implements following features:
		 * - Access to column value as $model->columnName
		 */
		public function __set($name, $value)
		{
			$this->set($name, $value);
		}

    /* pouzit pclib/tpl/ path */
    public function preview()
    {
        $t = new PCTpl('tpl/mails/preview.tpl');
        $t->values['to'] = implode('; ', $this->to);
        $t->values['cc'] = implode('; ', $this->cc);
        $t->values['bcc'] = implode('; ', $this->bcc);
        $t->values['reply-to'] = implode('; ', $this->replyTo);
        $t->values['from'] = $this->from;
        $t->values['subject'] = $this->subject;
        $t->values['body'] = $this->body;
        $t->values['status'] = $this->status;
        $t->values['attachments'] = implode('; ', $this->attachments);
        $t->values['send_at'] = $this->sendAt;
        $t->values['created_at'] = $this->createdAt;
        return $t->html();
    }


    public function isValid()
    {
        return (!empty($this->subject) and !empty($this->to));
    }

    // Validace e-mailové adresy
    protected function isValidEmail($email)
    {
        return filter_var($this->parseEmail($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    // Získání všech adres
    public function getRecipients()
    {
        return [
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'replyTo' => $this->replyTo,
        ];
    }

    public function clearRecipients()
		{
			$this->to = $this->cc = $this->bcc = $this->replyTo = [];
		    return $this;
		}

    public function setAttachment($path)
    {
        if (file_exists($path)) {
            $this->attachments[] = $path;
        } else {
            throw new \InvalidArgumentException("File '$path' does not exist.");
        }
        return $this;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
    }

    function parseName($input)
    {
        if (preg_match('/^(.+?)\s*<[^>]+>$/u', $input, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    function parseEmail($input)
    {
        // Pokud formát obsahuje email v hranatých závorkách
        if (preg_match('/<([^>]+)>/', $input, $matches)) {
            return trim($matches[1]);
        }

        // Pokud je zadán pouze email
        return $input;
    }

    // public function getModel()
    // {
    //     if ($this->id) $model = new pclib\orm\Model;

    // }

    // public function setModel($model)
    // {

    // }

}
 ?>