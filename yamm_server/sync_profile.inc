<?php
// $Id: sync_profile.inc,v 1.4 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Yamm server profile handling
 */

class SyncProfileException extends Exception {}

/**
 * Represents the server side profile, based on views.
 */
class SyncProfile
{
  /**
   * Get default profile internal name
   *
   * @return string
   */
  public static function getDefaultProfile() {
    return variable_get('sync_profile_default', NULL);
  }

  /**
   * Set default profile internal name
   *
   * @param string $name
   * @return void
   */
  public static function setDefaultProfile($name) {
    return variable_set('sync_profile_default', $name);
  }

  /**
   * Get list of profiles
   *
   * @param int $limit = 0
   *   If you set this parameter to anything else, a pager query will be used
   * @param int $element = 0
   *   Pager element if limit set
   * @return array
   *   Array of string/string key/value
   */
  public static function getList($limit = 0, $element = 0) {
    $ret = array();

    if (! $limit) {
      $result = db_query("SELECT profile, name FROM {yamm_server_profile}");
    }
    else {
      $result = pager_query("SELECT profile, name FROM {yamm_server_profile}", $limit, $element);
    }

    while ($data = db_fetch_object($result)) {
      $ret[$data->profile] = $data->name;
    }

    return $ret;
  }

  /**
   * Load a view from its name.
   *
   * @param string $view_name
   * @return view
   * @throws SyncProfileException
   */
  public static function loadView($view_name) {
    if ($view = views_get_view($view_name)) {
      return $view;
    }
    throw new SyncProfileException("View " . $view_name . " does not exists");
  }

  /**
   * Load a profile by internal name
   *
   * @param string $name
   * @return SyncProfile
   * @throws SyncProfileException
   */
  public static function load($name) {
    $data = db_result(db_query("SELECT data FROM {yamm_server_profile} WHERE profile = '%s'", $name));

    if (empty($data)) {
      throw new SyncProfileException("Profile " . $name . " does not exists in database");
    }

    return unserialize(db_decode_blob($data));
  }

  /**
   * Delete a profile by name
   *
   * @param string $name
   */
  public static function delete($name) {
    db_query("DELETE FROM {yamm_server_profile} WHERE profile = '%s'", $name);
  }

  /**
   * Get the Yamm_Entity internal type linked to a node
   *
   * @param view $view
   * @return string
   * @throws SyncProfileException
   */
  public static function getViewEntityType(view $view) {
    $supported = Yamm_EntityFactory::getSupportedTypes();
    foreach ($supported as $type => &$desc) {
      if ($view->base_table == $desc['base_table']) {
        return $type;
      }
    }
    throw new SyncProfileException("Views does not rely on a known base table");
  }

  /**
   * Human readable name
   * @var string
   */
  protected $_name = NULL;

  public function getName() {
    return $this->_name;
  }

  public function setName($name) {
    $this->_name = $name;
  }

  /**
   * Human readable name
   * @var string
   */
  protected $_internalName = NULL;

  public function getInternalName() {
    return $this->_internalName;
  }

  public function setInternalName($name) {
    $this->_internalName = $name;
  }

  /**
   * Associated views
   * @var array
   *   Array of string, internal view names
   */
  protected $_views = array();

  /**
   * Get all configured views
   *
   * @return array
   *   Array of view instances
   */
  public function getViews() {
    $ret = array();

    foreach ($this->_views as $view_name) {
      $ret[] = self::loadView($view_name);
    }

    return $ret;
  }

  /**
   * Remove all configured views
   */
  public function purgeViews() {
    $this->_views = array();
  }

  /**
   * Add a view to profile
   *
   * @param mixed $view_or_name
   *   Either a view object, or a view name string
   * @return void
   * @throws SyncProfileException
   */
  public function addView($view_or_name) {
    if (is_string ($view_or_name)) {
      $view = self::loadView($view_or_name);
    }
    else if ($view_or_name instanceof view) {
      $view = &$view_or_name;
    }
    else {
      throw new SyncProfileException("Invalid view");
    }

    // Check view is ok
    self::getViewEntityType($view);

    if (! in_array($view->name, $this->_views)) {
      $this->_views[] = $view->name;
    }
  }

  /**
   * Array of Yamm_EntitySettingsAbstract implementations
   * @var array
   */
  private $__settings = array();

  /**
   * Add new settings.
   *
   * Note that only instance of each specialized class can be set. If you set
   * the same class twice, it will overrided.
   *
   * @param Yamm_EntitySettingsAbstract $settings
   * @return void
   */
  public function addSettings(Yamm_EntitySettingsAbstract $settings) {
    $this->__settings[$settings->getType()] = serialize($settings);
  }

  /**
   * Get settings for a certain type.
   *
   * @param string $type
   * @return Yamm_EntitySettingsAbstract
   *   Or null if none saved
   */
  public function getSettingsForType($type) {
    // Let's do some lazzy loading
    if (isset($this->__settings[$type])) {
      Yamm_EntityFactory::findClass($type, Yamm_EntityFactory::CLASS_SETTINGS);
      return unserialize($this->__settings[$type]);
    }
  }

  /**
   * Save or update current object state into database
   *
   * @throws SyncProfileException
   */
  public function save() {
    if (empty($this->_internalName)) {
      throw new SyncProfileException("Profile without internal name can not be saved");
    }

    if (db_result(db_query("SELECT 1 FROM {yamm_server_profile} WHERE name = '%s'", $this->_name))) {
      db_query("UPDATE {yamm_server_profile} SET data = %b, name = '%s' WHERE profile = '%s'", array(
        serialize($this), $this->_name, $this->_internalName));
    }
    else {
      db_query("INSERT INTO {yamm_server_profile} (profile, name, data) VALUES ('%s', '%s', %b)", array(
        $this->_internalName, $this->_name, serialize($this)));
    }
  }
}