<?php
// $Id$

/**
 * @file
 * Yamm server profile handling
 */

class Yamm_Sync_ProfileException extends Exception {}

/**
 * Represents the server side profile, based on views.
 */
class Yamm_Sync_Profile extends XoxoExportableObject implements IFormable
{
  /**
   * Delete a profile by name
   *
   * @param string $name
   */
  public static function delete($name) {
    db_query("DELETE FROM {yamm_server_profile} WHERE profile = '%s'", $name);
  }

  /**
   * Backend static cache.
   * 
   * @var Yamm_Sync_Backend_Interface
   */
  protected $_backend;

  /**
   * Get backend type.
   * 
   * @return string
   */
  public function getBackendType() {
    return $this->_options['backend'];
  }

  /**
   * Set backend type.
   * 
   * @param string $type
   *   Backend type.
   */
  public function setBackendType($type) {
    if ($this->_options['backend'] != $type) {
      // Clean up eventually existing static cache.
      unset($this->_backend);
      // Finally, set the backend type.
      $this->_options['backend'] = $type;
    }
  }

  /**
   * Get backend instance.
   * 
   * @return Yamm_Sync_Backend_Interface
   *   Fully loaded instance, with options.
   * 
   * @throws Yamm_Sync_Backend_Interface
   *   If backend is not set.
   */
  public function getBackend() {
    if (!isset($this->_options['backend'])) {
      throw new Yamm_Sync_ProfileException("No backend set for this profile");
    }

    if (!isset($this->_backend)) {
      $this->_backend = oox_registry_get('yamm_sync_backend')->getItem($this->_options['backend']);
      if (isset($this->_options['backend_options'])) {
        $this->_backend->setOptions($this->_options['backend_options']);
      }
    }

    return $this->_backend;
  }

  /**
   * Set settings for the given entity settings type. This will erase already
   * existing settings if already set.
   * 
   * @param Yamm_EntitySettingsAbstract $settings
   *   Settings instance.
   */
  public function setSettings(Yamm_EntitySettingsAbstract $settings) {
    $this->_options['entity_' . $settings->getType()] = $settings->getOptions();
  }

  /**
   * Get settings for a certain type.
   *
   * @param string $type
   * 
   * @return Yamm_EntitySettingsAbstract
   *   A fresh new instance, non linked to this. NULL if none saved.
   */
  public function getSettingsForType($type) {
    $className = Yamm_EntityFactory::findClass($type, Yamm_EntityFactory::CLASS_SETTINGS);
    $settings = new $className();
    if ($this->hasOption('entity_' . $type)) {
      $settings->setOptions($this->getOption('entity_' . $type));
    }
    return $settings;
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::form()
   */
  public function form() {
    $form = array();

    yamm_api_bootstrap_entity();

    // Backend selector.
    $form['backend'] = array(
      '#type' => 'fieldset',
      '#title' => t("Backend options"),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['backend']['backend_type'] = oox_registry_item_selector('yamm_sync_backend');
    $form['backend']['backend_type']['#title'] = t("Backend");

    try {
      $backend = $this->getBackend();
      $form['backend']['backend_type']['#default_value'] = $this->_options['backend'];
      $form['backend']['backend_form'] = $backend->form();
    }
    catch (Yamm_Sync_ProfileException $e) {
      $form['backend']['backend_type']['#description'] = t("No value is set, specific options form will appear after you save this profile with a backend set.");
    }

    // Entities options.
    foreach (Yamm_EntityFactory::getSupportedTypes() as $type => $desc) {
      try {
        $settings = $this->getSettingsForType($type);
        if (!$settings instanceof Yamm_EntitySettingsAbstract) {
          $settings = Yamm_EntityFactory::getEntitySettingsInstance($type);
        }
        $form['entity_' . $type] = array(
          '#type' => 'fieldset',
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
          '#title' => t($desc['name']),
        );
        $form['entity_' . $type] += $settings->form();  
        $types[] = $type;
      }
      catch (Yamm_Entity_ClassNotFoundException $e) {
        // Silent error, it means our Yamm_Entity does not implement the
        // Yamm_EntitySettingsAbstract class.
      }
    }
    $form['types'] = array(
      '#type'  => 'value',
      '#value' => $types,
    );

    // Vertical tab usage.
    if (module_exists('vertical_tabs')) {
      vertical_tabs_add_vertical_tabs($form);
    }

    return $form;
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::formValidate()
   */
  public function formValidate(&$values) {
    try {
      $backend = $this->getBackend();
      $backend->formValidate($values['backend']['backend_form']);
    }
    catch (Yamm_Sync_ProfileException $e) { /* Silent error. */ }

    // Validate entity settings.
    $errors = array();
    foreach ($values['types'] as $type) {
      try {
        Yamm_EntityFactory::getEntitySettingsInstance($type)->formValidate($values['entity_' . $type]);
      }
      catch (OoxFormValidationException $e) {
        $errors += $e->getErrors();
      }
    }
    if (!empty($errors)) {
      throw new OoxFormValidationException($errors);
    }
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::formSubmit()
   */
  public function formSubmit(&$values) {
    $this->_options['backend'] = $values['backend']['backend_type'];
    try {
      $backend = $this->getBackend();
      $backend->formSubmit($values['backend']['backend_form']);
    }
    catch (Yamm_Sync_ProfileException $e) { /* Silent error. */ }

    foreach ($values['types'] as $type) {
      $settings = Yamm_EntityFactory::getEntitySettingsInstance($type);
      $settings->formSubmit($values['entity_' . $type]);
      // Do not save empty options.
      if ($settings->getOptionsCount() > 0) {
        $this->_options['entity_' . $type] = $settings->getOptions();
      }
    }
  }

  /**
   * @see XoxoObject::clean()
   * 
   * Remove the backend object static cache.
   */
  public function clean() {
    parent::clean();
    unset($this->_backend);
  }

  /**
   * @see Options::getOptions()
   * 
   * Merge the backend options with the others.
   */
  public function getOptions() {
    try {
      $backend = $this->getBackend();
      $this->_options['backend_options'] = $backend->getOptions();
    }
    catch (Yamm_Sync_ProfileException $e) {
      unset($this->_options['backend_options']);
    }
    return parent::getOptions();
  }
}