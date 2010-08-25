<?php
// $Id: Content.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * File settings implementation.
 */
class Yamm_Entity_FileSettings extends Yamm_EntitySettingsAbstract
{
  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::settingsForm()
   */
  public function form() {
    $form = array();

    // FIXME: Choose fetcher implementation.

    return $form;
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::formValidate()
   */
  public function formValidate($values) {
    // Nothing to validate
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::formSubmit()
   */
  public function formSubmit($values) {
    $this->set('contentRevision', (bool) $values['contentRevision']);
    $this->set('contentBehavior', (int) $values['contentBehavior']);
  }
}

/**
 * File entity implementation.
 * This implementation is able to copy files known by drupal core only.
 */
class Yamm_Entity_File extends Yamm_Entity
{
  /**
   * (non-PHPdoc)
   * @see Entity::_objectLoad()
   */
  protected function _objectLoad($identifier) {
    // Return file definition from files table.
    $file = db_fetch_object(db_query('SELECT f.* FROM {files} f WHERE f.fid = %d', $identifier));
    $file->md5sum = md5_file($file->filepath);
    return $file;
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_constructDependencies()
   */
  protected function _constructDependencies($object) {
    if ($object->uid) {
    	$this->addDependency('user', $object->uid);
    }
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_save()
   */
  protected function _save($object) {
    unset($object->fid);
    $this->getParser()->getFileFetcher()->fetchDrupalFile($object, 0, FALSE);
    $object->status = FILE_STATUS_PERMANENT;
    $object->timestamp = time();
    $object->filemime = file_get_mimetype($filename);
    drupal_write_record('files', $object);
    return $object->fid;
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_update()
   */
  protected function _update($object, $identifier) {
  	// Check file checksum and size, update if changed.
  	$localFile = db_fetch_object(db_query('SELECT f.* FROM {files} f WHERE f.fid = %d', $identifier));
  	if (md5_file($localFile->filepath) != $object->md5sum) {
  		// Download the file and save it.
  		$this->getParser()->getFileFetcher()->fetchDrupalFile($object, 0, TRUE);
  	}
  }
}
