<?php 
/**
 * @file
 * MailMessage class - used by Mailer.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib;
use pclib;

/**
 * Plain data object with email message containing addresses, body of message, status and attachments.
 * By class Mailer you can send it, schedule for sending, load/save from/to database, show preview etc.
 */
class MailMessage
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

    public $id = null;
    public $status;
    public $sendAt;
    public $createdAt;

    protected $fields = ['from', 'to', 'cc', 'bcc', 'replyTo', 'subject', 'body', 'text'];

    /** Create message from $data array (fieldName => value pairs). */
    public function __construct(array $data = [])
    {
        $this->status = self::STATUS_NEW;

        foreach ($data as $name => $value) {
        	$this->set($name, $value);
        }
    }

    /** 
     * Set email field $name to $value.
     * For address fields you can use one address or array of addresses.
     * Address field can be plain address or "Some Name <some.name@email.com>" format.
     * @param string $name ('from', 'to', 'cc', 'bcc', 'replyTo', 'subject', 'body', 'text')
     * @param mixed $value of the field
     * @return $this (fluent interface)
     **/
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

    /** 
     * Get email field value. 
     * @param string $name ('from', 'to', 'cc', 'bcc', 'replyTo', 'subject', 'body', 'text')
     * @see set()
     * @return mixed $value
     **/
    public function get($name)
    {
    	if (!in_array($name, $this->fields)) {
    		throw new Exception("Invalid field name: '$name'.");
    	}

    	return $this->$name;
    }

    /** 
     * Add another value to address field (for example more "to" addresses).
     * @return $this (fluent interface)
     **/
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

    /**
     * PHP magic method.
     * Read / write email properties directly (e.g. $address = $message->to)
     */
    public function __get($name)
	{
		return $this->get($name);
	}

	/**
	 * PHP magic method.
	 * Read / write email properties directly (e.g. $message->to = 'some@email.com')
	 */
	public function __set($name, $value)
	{
		$this->set($name, $value);
	}

    /**
     * Show message preview.
     * @return string $preview
     */
    public function preview($templatePath = null)
    {
        global $pclib;

        if (!$templatePath) {
            $templatePath = $pclib->app->paths['templates'] . 'mail-preview.tpl';
        }
        
        $t = new PCTpl($templatePath);
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


    /** Is message valid? */
    public function isValid()
    {
        return (!empty($this->subject) and !empty($this->to));
    }

    protected function isValidEmail($email)
    {
        return filter_var($this->parseEmail($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Return array of all recipients. */
    public function getRecipients()
    {
        return [
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'replyTo' => $this->replyTo,
        ];
    }

    /** Remove all recipients. */
    public function clearRecipients()
		{
			$this->to = $this->cc = $this->bcc = $this->replyTo = [];
		    return $this;
		}

    /** 
     * Add attachment. 
     * @param string $path Path to file
     * @return $this (fluent interface)
     */
    public function setAttachment($path)
    {
        if (file_exists($path)) {
            $this->attachments[] = $path;
        } else {
            throw new \InvalidArgumentException("File '$path' does not exist.");
        }
        return $this;
    }

     /** 
     * Return attachments. 
     * @return array $attachments
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
    }

    protected function parseName($input)
    {
        if (preg_match('/^(.+?)\s*<[^>]+>$/u', $input, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    protected function parseEmail($input)
    {
        // Pokud formát obsahuje email v hranatých závorkách
        if (preg_match('/<([^>]+)>/', $input, $matches)) {
            return trim($matches[1]);
        }

        return $input;
    }

}
 ?>