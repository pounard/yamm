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
  public function formValidate(&$values) {
    // Nothing to validate
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::formSubmit()
   */
  public function formSubmit(&$values) {
//    $this->set('contentRevision', (bool) $values['contentRevision']);
//    $this->set('contentBehavior', (int) $values['contentBehavior']);
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
    // Load file definition from files table.
    $file = db_fetch_object(db_query('SELECT f.* FROM {files} f WHERE f.fid = %d', $identifier));

    // Generate a md5 summary for client side to be able to determinate if file
    // download really is necessary or not.
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
    // Clean new object primary identifier.
    unset($object->fid);

    // Fetch the file, this is where the magic happens.
    $this->getParser()->getFileFetcher()->fetchDrupalFile($object, 0, FALSE);

    // Set some usefull data to file, such as time and mime type.
    $object->status = FILE_STATUS_PERMANENT;
    $object->timestamp = time();
    yamm_api_file_get_mime($object, TRUE);

    // Save new object and return its new primary identifier.
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

    // Proceed to file download only if current md5 summary is different. File
    // could have been modified on client side, we need to do compute the new
    // md5 summary each time.
    if (md5_file($localFile->filepath) != $object->md5sum) {

      // Download the file and save it.
      $this->getParser()->getFileFetcher()->fetchDrupalFile($object, 0, TRUE);

      // Reset file properties.
      $object->status = FILE_STATUS_PERMANENT;
      $object->timestamp = time();
      yamm_api_file_get_mime($object, TRUE);

      // Save the modified 'files table record.
      drupal_write_record('files', $object, array('fid'));
    }
  }
}
