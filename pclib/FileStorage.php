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
 *  - Each file has record in database table FILESTORAGE and it is assigned to some entity (specified by location $loc).
 *  - Examples:
 *  $fs->setFiles(['products', 1], $fs->postedFiles()); //Save posted files
 *  $fs->getFiles(['products', 1]); //return array with list of files for product_id=1
 *  $fs->getFile(123); //return file with id=123
 **/
class FileStorage extends system\BaseObject implements IService
{
	/** Database table name. */
	public $TABLE = 'FILESTORAGE';

	/** If upload error occurs, this will contains error messages. */
	public $errors = [];

	/** You can use fields: HASH,EXT,ORIGNAME,ORIGNAME_NORMALIZED,FILE_ID or any field from $entity. */
	public $fileNameFormat = "{ENTITY_TYPE}_{HASH}.{EXT}";

	/** Unused - always "/Y/n/" in this version. */
	public $dirNameFormat = ''; 

	/** Files matching patterns cannot be uploaded. */
	public $uploadBlackList = ['*.php','*.php?','*.phtml','*.exe','.htaccess'];

	public $db;

	/** Path to your writable storage directory. */
	protected $rootDir;

/**
 * \param $rootDir Path to your writable storage directory.
 */
function __construct($rootDir)
{
	parent::__construct();

	if (!is_dir($rootDir)) throw new IOException("Directory '$rootDir' does not exists.");
	$this->rootDir = $rootDir;

	$this->service('db');
}


/**
 * Return file from location $loc.
 * @param int|array $loc File location
 * @param bool $withContent Return content of the file too
 */
function getFile($loc, $withContent = false)
{
	if (is_numeric($loc)) {
		$filter = ['ID' => $loc + 0];
	}
	elseif(is_array($loc) and count($loc) == 3) {
		$filter = [
			'ENTITY_TYPE'=>$loc[0],
			'ENTITY_ID'=>$loc[1],
			'FILE_ID'=>$loc[2],
		];
	}
	elseif(!empty($loc['HASH'])) {
		$filter = ['HASH' => $loc['HASH']];
	}
	else {
		throw new Exception('Bad parameters.');
	}

	$file = $this->db->select($this->TABLE, $filter);
	if (!$file) return false;

	if ($withContent) {
		$path = $this->rootDir.$file['FILEPATH'];
		if (!file_exists($path)) throw new FileNotFoundException("File '$path' not found.");
		$file['CONTENT'] = file_get_contents($path);
	}
	
	return $file;
}


/**
 * Store file $data into location $loc (insert or update).
 * @param int|array $loc File location
 * @param array $data File data
 */
function setFile($loc, $data)
{
	$this->trigger('file.before-save', ['loc' => $loc, 'file' => $data]);

	$isNew = false;

	if (is_numeric($loc)) {
		$filter = ['ID' => $loc + 0];
		$file = $this->getFile($loc);
		$loc = [$file['ENTITY_TYPE'], $file['ENTITY_ID'], $file['FILE_ID']];
	}
	elseif(is_array($loc) and (count($loc) == 3 or count($loc) == 2))
	{
		if (count($loc) == 2)
		{
			$isNew = true;

			if (!empty($data['FILE_ID'])) {
				$loc[2] = $data['FILE_ID'];

				if ($this->getFile($loc)) {
					$isNew = false;
				}
			}
		}

		$filter = [
			'ENTITY_TYPE'=>$loc[0],
			'ENTITY_ID'=>$loc[1],
			'FILE_ID'=> array_get($loc, 2),
		];
	}
	else {
		throw new Exception('Bad parameters.');
	}

	if ($isNew) {
		$data = $this->insertFile($loc, $data);
	}
	else {
		/* Upload new file or just update info? */
		if ($data['FILEPATH_SRC'] or $data['CONTENT']) {
			$oldFile = $this->deleteFile($loc);
			$data['HASH'] = $oldFile['HASH'];
			$data = $this->insertFile($loc, $data);
		}
		else {
			$udata = array_intersect_key($data, ['ANNOT' => 1,'ORIGNAME' => 1]);
			$this->db->update($this->TABLE, $udata, $filter);
		}
	}

	$this->trigger('file.after-save', ['loc' => $loc, 'file' => $data]);
	
	return $data['ID'];
}

/**
 * Copy file from directory path $path into filestorage location $loc.
 * @param string $path Full source path
 * @param int|array $loc Target location
 */
function copyFile($path, $loc)
{
	$this->setFile($loc, ['FILEPATH_SRC' => $path]);
}

/**
 * Delete file into location $loc.
 * @param int|array $loc File location
 */
function deleteFile($loc)
{
	$file = $this->getFile($loc);

	$this->trigger('file.before-delete', ['loc' => $loc, 'file' => $file]);

	if (!$file) throw new FileNotFoundException("File not found.");

	$path = $this->rootDir.$file['FILEPATH'];

	if (file_exists($path)) {
		$ok = @unlink($path);
		if (!$ok) throw new IOException("File '$path' cannot be deleted.");
	}

	$this->db->delete($this->TABLE, ['ID' => $file['ID']]);

	$this->trigger('file.after-delete', ['loc' => $loc, 'file' => $file]);

	return $file;
}

/** Upload new file into filesystem and create db record. */
protected function insertFile(array $loc, array $data)
{
	if (isset($data['CONTENT'])) {
		$data['FILEPATH_SRC'] = $this->createTempFile($data);
		unset($data['CONTENT']);
		$data['IS_TEMP'] = true;
	}

	$path = $data['FILEPATH_SRC'];
	if (!file_exists($path)) throw new FileNotFoundException("File '$path' not found.");

	//defaults...
	$data += [
		'FILE_ID' => null, 'MIMETYPE' => null, 'ANNOT' => null, 
		'USER_ID' => null, 'ORIGNAME' => basename($path)
	];

	$file = [
		'ENTITY_TYPE'=> $loc[0],
		'ENTITY_ID'=>   $loc[1],
		'FILE_ID' => isset($loc[2])? $loc[2] : $this->newFileId($loc),
		'SIZE' => filesize($path),
		'HASH' => isset($data['HASH'])? $data['HASH'] : $this->getUniqueHash(),
		'ORIGNAME' => $data['ORIGNAME'],
		'MIMETYPE' => $data['MIMETYPE'],
		'ANNOT' => $data['ANNOT'],
		'USER_ID' => $data['USER_ID'],
		'DT' => date("Y-m-d H:i:s"),
	];

	if ($this->fileInBlackList($file['ORIGNAME'])) {
		throw new IOException("File type is not allowed.");
		return;
	}

	$dir = $this->getDir($this->dirNameFormat, $file);
	$filename = $this->getFileName($this->fileNameFormat, $file);

	$file['FILEPATH'] = $dir.$filename;

	if (!$file['MIMETYPE']) {
		$file['MIMETYPE'] = mimetype($path);
	}
	
	if (!empty($data['IS_FORM_POST'])) {
		$ok = move_uploaded_file($path, $this->rootDir.$file['FILEPATH']);
	}
	elseif(!empty($data['IS_TEMP'])) {
	  $ok = rename($path, $this->rootDir.$file['FILEPATH']);
	}
	else {
	  $ok = copy($path, $this->rootDir.$file['FILEPATH']); //move?
	}

	if (!$ok) throw new IOException("Uploading '".$file['ORIGNAME']."' failed.");

	$filter = [
		'ENTITY_TYPE' => $loc[0],
		'ENTITY_ID' => $loc[1],
		'FILE_ID' => $data['FILE_ID'],
	];

	$file['ID'] = $this->db->insert($this->TABLE, $file, $filter);

	return $file;
}

protected function createTempFile($file)
{
	$ext = pathinfo($file['ORIGNAME'], PATHINFO_EXTENSION) ?: 'tmp';
	$dir = $this->getDir($this->dirNameFormat, $file);
	$path = $this->rootDir.$dir.'_tmp_'.Str::random(11).'.'.$ext;
	file_put_contents($path, $file['CONTENT']);
	return $path;
}

protected function newFileId($loc)
{
	  $max = $this->db->select(
    "select MAX(FILE_ID) N FROM $this->TABLE
    WHERE ENTITY_TYPE='{0}' AND ENTITY_ID='{1}' AND FILE_ID LIKE 'fs_%'",
    $loc
  );

  return isset($max['N']) ? ++$max['N'] : 'fs_0001';
}

/**
 * Return array of all files assigned to entity $loc.
 * @param int|array $loc [entity-type, entity-id]
 */
function getFiles($loc)
{
	$files = $this->db->selectAll(
		"select * from $this->TABLE where ENTITY_TYPE='{0}' AND ENTITY_ID='{1}' order by FILE_ID", 
		$loc
	);

	return $files;
}

/**
 * Store all $files into file storage and assign it to entity $loc.
 * @param int|array $loc [entity-type, entity-id]
 * @param array $files List of files
 */
function setFiles($loc, $files)
{
	foreach ($files as $file) {
		$this->setFile($loc, $file);
	}
}

/**
 * Delete all files assigned to entity $loc.
 * @param int|array $loc [entity-type, entity-id]
 */
function deleteFiles($loc)
{
	foreach ($this->getFiles($loc) as $file) {
		$this->deleteFile($file['ID']);
	}
}


protected function getMultipleField($input_id, $data)
{
	$multiple = [];
	$count = count($data['name']);
	for($i = 0; $i < $count; $i++) {
		foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
			$multiple[$input_id.'_'.$i][$key] = $data[$key][$i];
		}

		$multiple[$input_id.'_'.$i]['input_id'] = $input_id;
	}
	return $multiple;
}

/**
 * Return array of files submitted by some form.
 * You can store them by function setFiles().
 * @param string $input_id If present, it will return data from this input only
 * @return array $files List of files
 */
function postedFiles($input_id = null)
{
	$files = [];
	$this->errors = [];
	
	$posted = $input_id? array($input_id => $_FILES[$input_id]) : (array)$_FILES;

	//handle multiple file upload fields
	$multiples = [];
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

		if (!$data or $data['size']<=0 or !is_uploaded_file($data['tmp_name'])) {
			continue;
		}

		$files[] = [
			'INPUT_ID' => $data['input_id'], //for multiple input
			'FILE_ID' => $id,
			'FILEPATH_SRC' => $data['tmp_name'],
			'ORIGNAME' => $data['name'],
			'MIMETYPE' => $data['type'],
			'SIZE' => $data['size'],
			'IS_FORM_POST' => true,
		];
	}
	
	return $input_id? $files[0] : $files;
}

/**
 * Output file $loc (such as image or pdf) into the browser.
 * @param int|array $loc file location (you can use integer id too)
 * @param bool $showDownload Should browser show download dialog?
 */
function output($loc, $showDownload = false)
{
	$file = $this->getFile($loc, true);
	$path = $this->rootDir.$file['FILEPATH'];
	if (!$file or !file_exists($path)) throw new FileNotFoundException("File not found.");
	$disposition = $showDownload? 'attachment':'inline';
	header('Content-type: '.$file['MIMETYPE']);
	header('Content-Disposition: '.$disposition.'; filename="'.$file['ORIGNAME'].'"');
	
	die($file['CONTENT']);
}

/**
 * Check if file is image.
 */
function isImage($file) {
	return (strpos($file['MIMETYPE'], 'image/') === 0);
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
	return Str::id($filename, '\w\.-');
}

/**
 * Create and return directory name where file $file will be stored.
 * It is creating and using '/Year/month/' directories by default.
 * Parameters $format and $file are unused in current version.
 */
protected function getDir($format, $file)
{
	$dir = date("/Y/n/");
	$fulldir = $this->rootDir.$dir;
	if (!is_dir($fulldir)) {
		$oldumask = umask(0);
		$ok = mkdir($fulldir, 0777, true);
		umask($oldumask);
		if (!$ok) throw new IOException("Directory '$fulldir' cannot be created.");
	}
	return $dir;
}

protected function getUniqueHash()
{
	$hash = Str::random(11);

	while($this->db->exists($this->TABLE, "HASH='{0}'", $hash)) {
		$hash = Str::random(11);
	}

	return $hash;
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
	//$file['HASH'] = randomstr(8);
	return Str::format($format, $file);
}

}

?>