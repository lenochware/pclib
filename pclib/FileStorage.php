<?php
/**
 * @file
 * Provides file management functions. You can upload, store, list and manage files.
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 *  Provides file management functions. You can upload, store, list and manage files.
 *  - Files are stored in directory structure 'rootdir/year/month/' by default.
 *  - Each file has record in database table FILESTORAGE and it is assigned to some $entity.
 *  - Examples:
 *  $fs->save([100, 'products'], $fs->postedFiles()); //Save posted files
 *  $fs->getAll([100, 'products']); //return array with list of files for product_id=100
 **/
class FileStorage extends system\BaseObject implements IService
{
	/** Database table name. */
	public $TABLE = 'FILESTORAGE';

	/** If upload error occurs, this will contains error messages. */
	public $errors = array();

	/** You can use fields: HASH,EXT,ORIGNAME,ORIGNAME_NORMALIZED,FILE_ID or any field from $entity. */
	public $fileNameFormat = "{PREFIX}{HASH}.{EXT}";

	/** Unused - always "/Y/n/" in this version. */
	public $dirNameFormat = ''; 

	/** Occurs before file is saved. */
	public $onBeforeSave;

	/** Occurs after file is saved. */
	public $onAfterSave;

	/** Files matching patterns cannot be uploaded. */
	public $uploadBlackList = array('*.php','*.php?','*.phtml','*.exe','.htaccess');

	public $db;

	/** Path to your writable storage directory. */
	protected $rootdir;

/**
 * \param $rootdir Path to your writable storage directory.
 */
function __construct($rootdir)
{
	parent::__construct();

	if (!is_dir($rootdir)) throw new IOException("Directory '$rootdir' does not exists.");
	$this->rootdir = $rootdir;

	$this->service('db');
}

protected function getDbColumns($data)
{
	return array_intersect_key($data, $this->db->columns($this->TABLE));
}


/**
 *  Save file and assign it to the entity.
 *  If file FILE_ID already exists, it is rewritten.
 *  $file is array coming from function postedFiles()
 *  $entity must contains fields ID,TYPE identifying to what entity file is assigned.
 *  Except this fields, it can contains any optional fields.
 * \param $entity associative array with entity data
 * \param $file associative array with file to upload informations. See postedFiles().
 */
function saveFile($entity, $file)
{
	$this->onBeforeSave($entity, $file);

	if (!(int)$entity[0] or !$entity[1]) {
		throw new NoValueException('Cannot save file - invalid entity.');
	}
	
	if (!$this->hasUploadedFile($file)) {
		$this->updateMeta($entity, $file);
		return;
	}

	if ($this->fileInBlackList($file['ORIGNAME'])) {
		$this->errors[$file['INPUT_ID']] = 'File type is not allowed.';
		return;
	}

	$dir = $this->getDir($this->dirNameFormat, $file);
	$filename = $this->getFileName($this->fileNameFormat, $file);

	$found = $this->findOne(array(
		'ENTITY_ID'  =>$entity[0],
		'ENTITY_TYPE'=>$entity[1],
		'FILE_ID'=>$file['FILE_ID'],
	));
	if ($found) $this->delete($found['ID']);

	$ok = @move_uploaded_file($file['TMP_NAME'], $this->rootdir.$dir.$filename);
	if (!$ok) {
		$this->errors[$file['INPUT_ID']] = "Uploading '".$file['ORIGNAME']."' failed.";
		return;
	}


	$file += array(
	'ENTITY_ID' => $entity[0],
	'ENTITY_TYPE' => $entity[1],
	'FILEPATH' => $dir.$filename,
	'DT' => date("Y-m-d H:i:s"),
	);

	$id = $this->db->insert($this->TABLE, $this->getDbColumns($file));

	//$id = $this->db->insert($this->TABLE, $newfile);

	$this->onAfterSave($entity, $file, $id);
	return $id;
}

/** Copy file $file['SRC_PATH'] into file-storage. */
function copyFile($entity, $file)
{
	//$this->onBeforeSave($entity, $file);

	if (!(int)$entity[0] or !$entity[1]) {
		throw new Exception('Cannot save file - invalid entity.');
	}

	if ($this->fileInBlackList($file['ORIGNAME'])) {
		$this->errors[$file['INPUT_ID']] = 'File type is not allowed.';
		return;
	}

	$dir = $this->getDir($this->dirNameFormat, $file);
	$filename = $this->getFileName($this->fileNameFormat, $file);

	$found = $this->findOne(array(
		'ENTITY_ID'  =>$entity[0],
		'ENTITY_TYPE'=>$entity[1],
		'FILE_ID'=>$file['FILE_ID'],
	));
	if ($found) $this->delete($found['ID']);

	$ok = copy($file['SRC_PATH'], $this->rootdir.$dir.$filename);
	if (!$ok) {
		$this->errors[$file['INPUT_ID']] = "Uploading '".$file['ORIGNAME']."' failed.";
		return;
	}

	$file += array(
	'ENTITY_ID' => $entity[0],
	'ENTITY_TYPE' => $entity[1],
	'FILEPATH' => $dir.$filename,
	'DT' => date("Y-m-d H:i:s"),
	);

	$id = $this->db->insert($this->TABLE, $this->getDbColumns($file));

	//$this->onAfterSave($entity, $file, $id);
	return $id;
}

/**
 *  Save all files in $files array and assign these files to entity $entity.
 *  \param $entity associative array with entity data
 *  \param $files Files to upload coming from method postedFiles()
 *  \see saveFile()
 */
function save($entity, $files)
{
	foreach ($files as $file) {
		$this->saveFile($entity, $file);
	}
}

protected function getMultipleField($input_id, $data)
{
	$multiple = array();
	$count = count($data['name']);
	for($i = 0; $i < $count; $i++) {
		foreach (array('name', 'type', 'tmp_name', 'error', 'size') as $key) {
			$multiple[$input_id.'_'.$i][$key] = $data[$key][$i];
		}

		$multiple[$input_id.'_'.$i]['input_id'] = $input_id;
	}
	return $multiple;
}

/**
 *  Return array of posted files coming from php array _FILES.
 *  Example: $fs->save($entity, $fs->postedFiles());
 *  Example: $file = postedFiles('FILE_1'); //Read FILE_1 form field
 *  \param $input_id If present, it will return data from one input only
 */
function postedFiles($input_id = null)
{
	$files = array();
	$this->errors = array();
	$posted = $input_id? array($input_id => $_FILES[$input_id]) : (array)$_FILES;

	//handle multiple file upload fields
	$multiples = array();
	foreach ($posted as $k => $data) {
		if (is_array($data['name'])) {
			$multiples += $this->getMultipleField($k, $data);
			unset($posted[$k]);
		}
		else {
			$posted[$k]['input_id'] = $k;
		}
	}

	if ($multiples) {
		$posted += $multiples;
	}

	foreach($posted as $id => $data)
	{
		if ($data['error'] and $data['error'] != UPLOAD_ERR_NO_FILE) {
			$this->errors[$id] = $this->getErrorMessage($data['error']);
			continue;
		}

		if ($this->fileInBlackList($data['name'])) {
			$this->errors[$id] = 'File type is not allowed.';
			continue;
		}

		if (!$data or $data['size']<=0 or !is_uploaded_file($data['tmp_name'])) continue;

		$files[] = array(
		'INPUT_ID' => $data['input_id'],
		'FILE_ID' => $id,
		'TMP_NAME' => $data['tmp_name'],
		'ORIGNAME' => $data['name'],
		'MIMETYPE' => $data['type'],
		'SIZE' => $data['size'],
		);
	}
	return $input_id? $files[0] : $files;
}

protected function hasUploadedFile($file)
{
	return ($file['TMP_NAME'] and $file['SIZE'] > 0);
}

/**
 * Update file metadata.
 */
protected function updateMeta($entity, $file)
{
	$editables = array('ANNOT','ORIGNAME');

	$found = $this->findOne(array(
		'ENTITY_ID'=>$entity[0],
		'ENTITY_TYPE'=>$entity[1],
		'FILE_ID'=>$file['FILE_ID'],
	));
	if (!$found) return false;

	foreach($file as $k=>$tmp)
		if (!in_array($k,$editables)) unset($file[$k]);

	$this->db->update($this->TABLE, $file, pri($found['ID']));
}

/**
 * Return entity of the record from FILESTORAGE table.
 */
function getEntity($id)
{
	$r = $this->db->select($this->TABLE, pri($id));
	return array($r['ENTITY_ID'],$r['ENTITY_TYPE']);
}

/**
 * Delete multiple files according $filter array.
 */
function deleteAll($filter)
{
	$toDelete = $this->db->select_one($this->TABLE.':ID', $filter);
	foreach($toDelete as $id) $this->delete($id);
}

/**
 * Delete file with primary key $id.
 */
function delete($id)
{
	$file = $this->db->select($this->TABLE, pri($id));
	if (!$file) throw new IOException("File not found.");
	$path = $this->rootdir.$file['FILEPATH'];
	if (file_exists($path)) {
		$ok = @unlink($path);
		if (!$ok) throw new IOException("File '$path' cannot be deleted.");
	}
	$this->db->delete($this->TABLE, pri($id));
}

/**
 * Delete all files linked with $entity.
 */
function deleteEntity($entity)
{
	$this->deleteAll(array('ENTITY_ID' => $entity[0], 'ENTITY_TYPE' => $entity[1]));
}

/**
 * Output file $id to the end-user.
 */
function output($id, $attachment = false)
{
	$file = $this->db->select($this->TABLE, pri($id));
	$path = $this->rootdir.$file['FILEPATH'];
	if (!file_exists($path)) throw new FileNotFoundException("File '$path' not found.");

	$disposition = $attachment? 'attachment':'inline';
	header('Content-type: '.$file['MIMETYPE']);
	header('Content-Disposition: '.$disposition.'; filename="'.$file['ORIGNAME'].'"');
	readfile($path);
	die();
}

/**
 * Check if file is image.
 */
function isImage($file) {
	return (strpos($file['MIMETYPE'], 'image/') === 0);
}

/**
 * Return list of all files (rows from db-table) according used filter.
 * You can use any fields for filtering.
 */
function findAll($filter)
{
	$files = $this->db->select_all($this->TABLE, $filter);
	return $files;
}

/**
 * Return particular file (row from db-table) according used filter.
 * You can use any fields for filtering.
 */
function findOne($filter)
{
	if (is_numeric($filter)) $filter = array('ID'=>$filter);
	return $this->db->select($this->TABLE, $filter);
}

/**
 * Return list of all files assigned to $entity.
 * You can use any fields for filtering.
 */
function getAll($entity)
{
	$files = $this->db->select_all(
		"select * from $this->TABLE where ENTITY_ID='{0}' AND ENTITY_TYPE='{1}' order by FILE_ID", 
		$entity
	);
	return $files;
}

/**
 * Return particular file (row from db-table) for entity $entity.
 */
function getOne($entity, $file_id = null)
{
	$filter = array(
		'ENTITY_ID'=>$entity[0],
		'ENTITY_TYPE'=>$entity[1],
	);
	if ($file_id) $filter['FILE_ID'] = $file_id;

	return $this->db->select($this->TABLE, $filter);
}

/** Test if uploaded filename mask is on the blacklist. */
function fileInBlackList($fileName)
{
	foreach ($this->uploadBlackList as $pattern) {
		if (fnmatch($pattern, $fileName)) return true;
	}
	return false;
}

function getUploadErrors()
{
	return $this->errors;
}

/**
 * Return upload error message.
 */
function getErrorMessage($code)
{
	switch ($code) { 
		case UPLOAD_ERR_INI_SIZE: 
				$message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
				break; 
		case UPLOAD_ERR_FORM_SIZE: 
				$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form"; 
				break; 
		case UPLOAD_ERR_PARTIAL: 
				$message = "The uploaded file was only partially uploaded"; 
				break; 
		case UPLOAD_ERR_NO_FILE: 
				$message = "No file was uploaded"; 
				break; 
		case UPLOAD_ERR_NO_TMP_DIR: 
				$message = "Missing a temporary folder"; 
				break; 
		case UPLOAD_ERR_CANT_WRITE: 
				$message = "Failed to write file to disk"; 
				break; 
		case UPLOAD_ERR_EXTENSION: 
				$message = "File upload stopped by extension"; 
				break; 

		default: 
				$message = "Unknown upload error"; 
				break; 
	} 
	return $message; 
}


/**
 * Sanitize filename.
 * Replace non-ascii characters, remove diacriticts and replace whitespaces with '_'.
 */
protected function normalize($filename)
{
	return mkident($filename, '_');
}

/**
 * Create and return directory name where file $file will be stored.
 * It is creating and using '/Year/month/' directories by default.
 * Parameters $format and $file are unused in current version.
 */
protected function getDir($format, $file)
{
	$dir = date("/Y/n/");
	$fulldir = $this->rootdir.$dir;
	if (!is_dir($fulldir)) {
		$oldumask = umask(0);
		$ok = mkdir($fulldir, 0777, true);
		umask($oldumask);
		if (!$ok) throw new IOException("Directory '$fulldir' cannot be created.");
	}
	return $dir;
}

/**
 * Return filename used for storing particular $file.
 * You can setup filename format by setting attribute FileStorage->fileNameFormat.
 * \param $format Format string e.g. "PREFIX_{ORIGNAME_NORMALIZED}.{EXT}";
 * \param array $file File information (Any field can be used in $format string)
 */
protected function getFileName($format, $file)
{
	$file['ORIGNAME_NORMALIZED'] = $this->normalize($file['ORIGNAME']);
	$file['EXT'] = pathinfo($file['ORIGNAME'], PATHINFO_EXTENSION);
	$file['HASH'] = randomstr(8);
	return paramstr($format, $file);
}

}

?>