<?php

/**
 * Template extended with 'head' tag, allowing add links to *.js and *.css files.
 * Moreover it contains support of flash messages.
 * @see App::setLayout()
 */
class App_Layout extends Tpl
{

public $cssClassMessage = 'message';
public $cssClassWarning = 'warning';
public $cssClassError   = 'error';

protected $headTag;

/**
 * Add links to *.css, *.js scripts into template.
 * Template must contains a {HEAD} tag.
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
		default:
			parent::print_Element($id, $sub, $value);
			break;
	}
}

protected function parseLine($line)
{
	$id = parent::parseLine($line);
	if ($this->elements[$id]['type'] == 'head') $this->headTag = $id;
	return $id;
}


function out($block = null)
{
	if ($flash = $this->app->getSession('pclib.flash')) {
		if ($flash['WARNINGS']) $flash['PRECONTENT'] .= "<div class=\"$this->cssClassWarning\">".implode('<br>', $flash['WARNINGS']).'</div>';
		if ($flash['MESSAGES']) $flash['PRECONTENT'] .= "<div class=\"$this->cssClassMessage\">".implode('<br>', $flash['MESSAGES']).'</div>';
		unset($flash['MESSAGES'],$flash['WARNINGS']);
		$this->values = array_merge($this->values, $flash);
		$this->app->setSession('pclib.flash', null);
	}
	parent::out($block);
}

}  //class App_Layout