<?php

/**
 * Template extended with 'head' tag, allowing add links to *.js and *.css files.
 * Moreover it contains support of flash messages.
 * @see App::setLayout()
 */
class App_Layout extends Tpl
{
protected $headTag;
protected $messagesTag;
public $MESSAGE_PATTERN = '<div class="%s">%s</div>';

/**
 * Add links to *.css, *.js scripts into template.
 * Template must contains a head tag.
 * Example: $app->layout->addScripts('js/jquery.js', 'css/bootstrap.css');
 * @param array|variable_number_of_arguments List of paths to css and js files
 */
public function addScripts()
{
	if (!$this->headTag) throw new NoValueException('Missing "head" tag in template.');
	$scripts = func_get_args();
	if (is_array($scripts[0])) $scripts = $scripts[0];
	if (is_array($this->values[$this->headTag])) $this->values[$this->headTag] += $scripts;
	else $this->values[$this->headTag] = $scripts;
}

function addInline($s) 
{
	if (!$this->headTag) throw new NoValueException('Missing "head" tag in template.');
	$this->elements[$this->headTag]['inline'] .= $s;
}

/**
 * Add flash (session stored) message.
 * Template must contains a messages tag.
 * @param string $message
 * @param string $cssClass Css-class of the message div
 * @param mixed $args Variable number of message arguments
 */
public function addMessage($message, $cssClass = null, $params = array())
{
	if (!$this->messagesTag) throw new NoValueException('Missing "messages" tag in template.');
	if (!$cssClass) $cssClass = 'message';
	$flash = $this->app->getSession('pclib.flash');
	$flash[$cssClass][] = $this->app->t($message, $params);
	$this->app->setSession('pclib.flash', $flash);
}

/**
 * Print content of webpage HEAD section.
 * @copydoc tag-handler
 */
function print_Head($id, $sub, $value)
{
	$scripts = explode(',', $this->elements[$id]['scripts']);
	if (is_array($value)) $scripts = array_merge($scripts, $value);

	foreach($scripts as $script) {
		if (!file_exists($script)) {
			throw new FileNotFoundException("File '$script' not found.");
		}
		
		$version = $this->elements[$id]['noversion']? '' : '?v='.filemtime($script);
		$ext = substr($script, strrpos($script, '.'));
		if ($script{0} != '/') $script = BASE_URL.$script;
		switch($ext) {
		case '.js': print "<script language=\"JavaScript\" src=\"$script$version\"></script>\n"; break;
		case '.css': print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$script$version\">\n"; break;
		}
	}

	$inline = $this->elements[$id]['inline'];
	if ($inline) print $inline;
}

/**
 * Print flash messages.
 * @copydoc tag-handler
 */
function print_Messages($id, $sub, $value)
{
	$flash = $this->app->getSession('pclib.flash');
	if (!$flash) return;
	foreach ($flash as $cssClass => $messages) {
		print sprintf($this->MESSAGE_PATTERN, $cssClass, implode('<br>', $messages));
	}
	$this->app->setSession('pclib.flash', null);
}

function __construct($path = '', $sessName = '')
{
	parent::__construct($path, $sessName);
}

function print_Element($id, $sub, $value)
{
	switch($this->elements[$id]["type"]) {
		case 'meta':
		case 'head':
			$this->print_Head($id,$sub,$value);
			break;
		case 'messages':
			$this->print_Messages($id,$sub,$value);
			break;
		default:
			parent::print_Element($id, $sub, $value);
			break;
	}
}

protected function parseLine($line)
{
	$id = parent::parseLine($line);
	if ($this->elements[$id]['type'] == 'head') $this->headTag = $id;
	if ($this->elements[$id]['type'] == 'messages') $this->messagesTag = $id;
	return $id;
}

}