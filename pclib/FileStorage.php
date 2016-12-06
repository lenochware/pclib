<?php
/**
 * @file
 * Store binary files into directory structure - file metadata are stored in table FILESTORAGE.
 *
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
 *  Store binary files into directory structure - file metadata are stored in table FILESTORAGE.
 *  Each file is assigned to $entity ($entity can be invoice, order, user etc.) One entity can have multiple files.
 *  File is identified with key ENTITY_ID, ENTITY_TYPE and FILE_ID in the database.
 *  It will generate structure of subdierectories, by default in format 'storage/year/month/'.
 *  It will rename files - by default 8-characters hash will be used.
 *  For your own rules about directories and filenames, redefine methods getFileName() or getDir().
 *
 **/
class FileStorage extends system\BaseObject implements IService
{
  /** Database table name. */
  public $TABLE = 'FILESTORAGE';

  /** If upload error occurs, this will contains error messages. */
  public $errors = array();

  /** You can use fields: HASH,EXT,ORIGNAME,ORIGNAME_NORMALIZED,FILE_ID or any field from $entity. */
  public $fileNameFormat = "{HASH}.{EXT}";

  /** Unused - always "/Y/n/" in this version. */
  public $dirNameFormat = ''; 

  /** Occurs before file is saved. */
  public $onBeforeSave;

  /** Occurs after file is saved. */
  public $onAfterSave;

  protected $db;

  /** Path to your writable storage directory. */
  protected $rootdir;

  protected $user;

/**
 * \param $rootdir Path to your writable storage directory.
 */
function __construct($rootdir) {
  global $pclib;
  if (!$pclib->app) throw new RuntimeException('No instance of application (class app) found.');
  if (!is_dir($rootdir)) throw new IOException("Directory '$rootdir' does not exists.");
  //$this->app = $pclib->app;
  //$this->config = $this->app->config;
  $this->db = $pclib->app->db;
  $this->rootdir = $rootdir;
  $this->user = $pclib->app->auth? $pclib->app->auth->getuser() : array();
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
function saveFile($entity, $file) {
  $this->onBeforeSave($entity, $file);
  
  if (!$this->hasUploadedFile($file)) {
    $this->updateMeta($entity, $file);
    return;
  }

  if (!$file['FILE_ID']) $file['FILE_ID'] = $this->newFileId($entity);

  $dir = $this->getDir($this->dirNameFormat, $file);
  $filename = $this->getFileName($this->fileNameFormat, $file);

  $found = $this->findOne(array(
    'ENTITY_ID'  =>$entity[0],
    'ENTITY_TYPE'=>$entity[1],
    'FILE_ID'=>$file['FILE_ID'],
  ));
  if ($found) $this->delete($found['ID']);

  $ok = @move_uploaded_file($file['TMP_NAME'], $this->rootdir.$dir.$filename);
  if (!$ok) throw new IOException("Uploading '".$file['ORIGNAME']."' failed.");

  $newfile = array(
  'ENTITY_ID' => $entity[0],
  'ENTITY_TYPE' => $entity[1],
  'FILE_ID' => $file['FILE_ID'],
  'FILEPATH' => $dir.$filename,
  'ORIGNAME' => $file['ORIGNAME'],
  'ANNOT' => $file['ANNOT'],
  'MIMETYPE' => $file['MIMETYPE'],
  'SIZE' => $file['SIZE'],
  'USER_ID' => $this->user['ID'],
  'DT' => date("Y-m-d H:i:s"),
  );

  $id = $this->db->insert($this->TABLE, $newfile);

  $this->onAfterSave($entity, $newfile, $id);
  return $id;
}

/**
 *  Save all files in $files array and assign these files to entity $entity.
 *  \param $entity associative array with entity data
 *  \param $files Files to upload coming from method postedFiles()
 *  \see saveFile()
 */
function save($entity, $files) {
  foreach ($files as $file) {
    $this->saveFile($entity, $file);
  }
}

/**
 *  Return array of posted files coming from php array _FILES.
 *  Example: $fs->save($entity, $fs->postedFiles());
 *  Example: $file = postedFiles('FILE_1'); //Read FILE_1 form field, FILE_ID = 1
 *  \param $input_id If present, it will return data from one input only
 */
function postedFiles($input_id = null) {
  $files = array();
  $posted = $input_id? array($input_id => $_FILES[$input_id]) : (array)$_FILES;
  
  foreach($posted as $id => $data) {
    if ($data['error'] and $data['error'] != UPLOAD_ERR_NO_FILE) {
      $this->errors[$id] = $this->getError($data['error']);
    }
    if (!$data or $data['size']<=0 or !is_uploaded_file($data['tmp_name'])) continue;

    $aId = explode('_',$id);

    $files[] = array(
    'INPUT_ID' => $id,
    'FILE_ID' => (int)array_pop($aId),
    'TMP_NAME' => $data['tmp_name'],
    'ORIGNAME' => $data['name'],
    'MIMETYPE' => $data['type'],
    'SIZE' => $data['size'],
    );
  }
  return $input_id? $files[0] : $files;
}

function hasUploadedFile($file) {
  return ($file['TMP_NAME'] and $file['SIZE'] > 0);
}

/**
 * Update file metadata.
 */
function updateMeta($entity, $file) {

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
function getEntity($id) {
  $r = $this->db->select($this->TABLE, pri($id));
  return array($r['ENTITY_ID'],$r['ENTITY_TYPE']);
}

/**
 * Delete multiple files according $filter array.
 */
function deleteAll($filter) {
  $toDelete = $this->db->select_one($this->TABLE.':ID', $filter);
  foreach($toDelete as $id) $this->delete($id);
}

/**
 * Delete file with primary key $id.
 */
function delete($id) {
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
function deleteEntity($entity) {
  $this->deleteAll(array('ENTITY_ID' => $entity[0], 'ENTITY_TYPE' => $entity[1]));
}

/**
 * Output file $id to the end-user.
 */
function output($id, $attachment = false) {
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
function findAll($filter) {
  $files = $this->db->select_all($this->TABLE, $filter);
  return $files;
}

/**
 * Return particular file (row from db-table) according used filter.
 * You can use any fields for filtering.
 */
function findOne($filter) {
  if (is_numeric($filter)) $filter = array('ID'=>$filter);
  return $this->db->select($this->TABLE, $filter);
}

/**
 * Return list of all files assigned to $entity.
 * You can use any fields for filtering.
 */
function getAll($entity) {
/*  $files = $this->db->select_all(
    "select * from $this->TABLE where ENTITY_ID='{0}' AND ENTITY_TYPE='{1}' order by FILE_ID", 
    $entity
  );*/
  $files = $this->db->select_all(
    "select * from $this->TABLE where ENTITY_ID='[0]' AND ENTITY_TYPE='[1]' order by FILE_ID", 
    $entity
  );
  return $files;
}

/**
 * Return particular file (row from db-table) for entity $entity.
 */
function getOne($entity, $file_id = null) {
  $filter = array(
    'ENTITY_ID'=>$entity[0],
    'ENTITY_TYPE'=>$entity[1],
  );
  if ($file_id) $filter['FILE_ID'] = $file_id;

  return $this->db->select($this->TABLE, $filter);
}


/**
 * Generate id of the newly added file.
 */
protected function newFileId($entity) {
  $filter = array('ENTITY_ID'=>$entity[0],'ENTITY_TYPE'=>$entity[1]);
  $count = (int)$this->db->field($this->TABLE.':max(FILE_ID)', $filter);
  return ($count+1);
}

/**
 * Return upload error message.
 */
function getError($code) {
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
protected function normalize($filename) {
  return mkident($filename, '_');
}

/**
 * Create and return directory name where file $file will be stored.
 * It is creating and using '/Year/month/' directories by default.
 * Parameters $format and $file are unused in current version.
 */
protected function getDir($format, $file) {
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
protected function getFileName($format, $file) {
  $file['ORIGNAME_NORMALIZED'] = $this->normalize($file['ORIGNAME']);
  $file['EXT'] = pathinfo($file['ORIGNAME'], PATHINFO_EXTENSION);
  $file['HASH'] = randomstr(8);
  return paramstr($format, $file);
}

} //FileStorage

?>