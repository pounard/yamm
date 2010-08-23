<?php
// $Id: Content.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * Content settings implementation.
 */
class Yamm_Entity_ContentSettings extends Yamm_EntitySettingsAbstract
{
  const MODULE_IGNORE = 1;
  const MODULE_TRYENABLE = 2;
  const MODULE_OVERRIDEFALLBACK = 3;
  const MODULE_OVERRIDE = 4;

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::settingsForm()
   */
  public function form() {
    $form = array();

    $form['contentRevision'] = array(
      '#type' => 'checkbox',
      '#title' => 'EXPERIMENTAL ' . t('Content type revisions'),
      '#description' => t('If you check this option, modified content types will be copied with a new name, time based. All node associated to old content type will be moved to the content type revision. Note that it will break your views which are content_type based, and could break some other modules behavior.'),
      '#default_value' => $this->get('contentRevision', FALSE),
    );

    $form['contentBehavior'] = array(
      '#type' => 'radios',
      '#title' => t('Module content types behavior'),
      '#options' => array(
        self::MODULE_IGNORE => t('Ignore content type'),
        self::MODULE_TRYENABLE => t('Try to enable module, ignore in case of fail'),
        self::MODULE_OVERRIDEFALLBACK => t('If module exists update content type, else try to enable module'),
        self::MODULE_OVERRIDE => t('Whatever happens, save the content type'),
      ),
      '#description' => t('Behavior to adopt when updating or inserting a module defined content type.'),
      '#default_value' => $this->get('contentBehavior', self::MODULE_OVERRIDE));

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
 * Content entity implementation.
 */
class Yamm_Entity_Content extends Yamm_Entity
{
  /**
   * (non-PHPdoc)
   * @see Entity::_objectLoad()
   */
  protected function _objectLoad($type_name) {
    // Thanks to deploy module author for this codebase

    if (module_exists('fieldgroup')) {
      $groups = array_keys(fieldgroup_groups($type_name));
    }

    $fields = array_values(content_copy_fields($type_name));

    $values = array(
      'type_name' => $type_name,
      'groups' => $groups,
      'fields' => $fields,
    );

    $export = content_copy_export($values);

    return $export;
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_constructDependencies()
   */
  protected function _constructDependencies($export) {
    // No dependencies
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_save()
   */
  protected function _save($export) {
    // Get back content type
    preg_match('/\'type\'[ ]+=>[ ]+\'([a-zA-Z0-9-_]+)\'/', $export, $matches);
    $type_name = $matches[1];

    // Same comment as upper, you totally rocks guys
    $values = array('type_name' => '<create>', 'macro' => $export, 'op' => 'Submit');
    $form_state['values'] = $values;
    drupal_execute('content_copy_import_form', $form_state);

    if ($errors = form_get_errors()) {
      foreach ($errors as $error) {
        $msg .= "$error ";
      }
      watchdog('entity_content', "Error during content import @msg", array("@msg" => $msg));
    }

    // Aptempt to clear form cache
    cache_clear_all(NULL, 'cache_form');
    form_set_error(NULL, '', TRUE);

    // This will store content type latest definition
    cache_set('content_entity_' . $type_name, md5($export), $table = 'yamm_data_store', $expire = CACHE_PERMANENT);

    return $type_name;
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_update()
   */
  protected function _update($export, $type_name) {
    if ($this->getSettings()->get('contentRevision', FALSE)) {

      // As all migrated nodes will have the new type, we don't have to worry
      // about other data to import.
      $node_count = (int) db_result(db_query("SELECT COUNT(*) FROM {node} WHERE type = '%s'", $type_name));

      if ($node_count > 0) {
        $this->__revisionContent($export, $type_name);
        return;
      }
    }

    $values = array('type_name' => $type_name, 'macro' => $export, 'op' => 'Submit');
    $form_state['values'] = $values;
    drupal_execute('content_copy_import_form', $form_state);

    if ($errors = form_get_errors()) {
      foreach ($errors as $error) {
        $msg .= "$error ";
      }
      watchdog('entity_content', "Error during content import @msg", array("@msg" => $msg));
    }

    // Aptempt to clear form cache
    cache_clear_all(NULL, 'cache_form');
    form_set_error(NULL, '', TRUE);

    // This will store content type latest definition
    cache_set('content_entity_' . $type_name, md5($export), $table = 'yamm_data_store', $expire = CACHE_PERMANENT);
  }

  /**
   * Create a content type revision, before saving new one
   *
   * This is kind goret (french word), what will be done here:
   *  * Copy old content type to a new name.
   *  * As the old content type has a new name, create a UUID for it
   *  * Change all node with content type name to old content type name (they
   *    should keep their old fields etc).
   *  * Alter the old content type with new one coming from network, and TADA
   *    we have multiple content types, each node keeping it content type.
   */
  private function __revisionContent($new_export, $type_name) {

    // Compare both content types
    $data = cache_get('content_entity_' . $type_name, $table = 'yamm_data_store');
    $old_hash = $data->data;
    $new_hash = md5($new_export);
/*
    switch ($this->getSettings()->get('contentBehavior')) {
      case Yamm_Entity_ContentSettings::MODULE_IGNORE:
      	return;

      case Yamm_Entity_ContentSettings::MODULE_OVERRIDE:
      	// FIXME: Todo.
      	break;

      case Yamm_Entity_ContentSettings::MODULE_OVERRIDEFALLBACK:
      	// FIXME: Todo. 
      	break;

      case Yamm_Entity_ContentSettings::MODULE_TRYENABLE:
      default:
      	// FIXME: Todo.
      	break;
    } */

    if ($old_hash != $new_hash) {
      // Duplicate content, change its name
      $revision = time();
      $new_name = $type_name . '-' . $revision;
      db_query("UPDATE {node_type} SET type = '%s', name = CONCAT(name, ' Revision %s') WHERE type = '%s'", array($new_name, $revision, $type_name));

      // Generate and save new UUID for old type
      Yamm_EntityFactory::getUuidForType('content', $new_name, TRUE);

      // Update all nodes
      db_query("UPDATE {node} SET type = '%s' WHERE type = '%s'", array($new_name, $type_name));

      // Save won't rely on cache
      content_clear_type_cache();

      $this->_save($new_export);
    }
  }
}
