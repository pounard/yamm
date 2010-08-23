<?php
// $Id: Entity.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * Default exception for Yamm.
 */
class Yamm_EntityException extends Exception {}

/**
 * Exception thrown when Yamm core attempt to load a non existing object
 * implementation.
 */
class Yamm_Entity_ClassNotFoundException extends Yamm_EntityException {}

/**
 * Exception throw when attempting to load an entity using an non
 * existing UUID.
 */
class Yamm_Entity_UnknownUuidException extends Yamm_EntityException {}

/**
 * Exception throw when trying to unserialize wrong data.
 */
class Yamm_Entity_UnableToUnserializeException extends Yamm_EntityException {}

/**
 * Exception thrown when trying to load a non existing object.
 */
class Yamm_Entity_UnableToLoadObjectException extends Yamm_EntityException {}

/**
 * Exception thrown when an object save had errors.
 */
class Yamm_Entity_UnableToSaveObjectException extends Yamm_EntityException {}

/**
 * Exception thrown when a piece of content does not exists on client side
 * anymore (mostly user accidental deletion).
 */
class Yamm_Entity_DeletedOnClientException extends Yamm_EntityException {}

/**
 * Store server entities configuration.
 */
abstract class Yamm_EntitySettingsAbstract {

  public function __construct() {
    $this->__setTypeFromClass();
  }

  private function __setTypeFromClass() {
    preg_match('/^Entity([a-zA-Z0-9]+)Settings$/', get_class($this), $matches);
    $this->__type = strtolower($matches[1]);
  }

  /**
   * Object type.
   * 
   * @var string
   */
  private $__type = 'void';

  /**
   * Get type.
   * 
   * @return string
   */
  public function getType() {
    return $this->__type;
  }

  /**
   * Variable cache.
   * 
   * @var array
   */
  private $__variables = array();

  /**
   * Save or update a variable.
   *
   * @param string $name
   * @param mixed $value
   * 
   * @return void
   */
  public function set($name, $value) {
    $this->__variables[$name] = $value;
  }

  /**
   * Get a variable.
   *
   * @param string $name
   * @param mixed $default
   *   Default value returned if variable not found.
   * 
   * @return mixed
   *   Variable value.
   */
  public function get($name, $default) {
    return isset($this->__variables[$name]) ? $this->__variables[$name] : $default;
  }

  /**
   * This is a normal form handler, construct your subform here. This settings
   * will be encapsuled into a global form, in with #tree to TRUE, so don't
   * forget it when you'll validate and submit your settings.
   *
   * @return array
   *   Sub form (drupal form)
   */
  public abstract function form();

  /**
   * Validate your settings.
   *
   * @param array $values
   *   Values corresponding to form elements you gave into the settingsForm()
   *   method. Remember that #tree is set to TRUE.
   * 
   * @return array
   *   Array of localized errors. Keys are field name, values are localized
   *   error string. Return an empty array if no error happened.
   */
  public abstract function formValidate($values);

  /**
   * Submit your settings.
   *
   * Here you can user set() method to set new variables into this object.
   * The object will be saved by the system and restored when parsing the
   * entities at pull time.
   *
   * @param array $values
   *   Values corresponding to form elements you gave into the settingsForm()
   *   method. Remember that #tree is set to TRUE.
   */
  public abstract function formSubmit($values);
}

/**
 * Factory class for entities
 *
 * This class should contain all Drupal core interaction, to let the Yamm_Entity
 * class as neutral as possible.
 */
class Yamm_EntityFactory
{
  const CLASS_ENTITY = 1;
  const CLASS_SETTINGS = 2;

  /**
   * Get UUID for object.
   *
   * @param string $type
   * @param int|string $identifier
   * 
   * @return string
   */
  public static function getUuidForType($type, $identifier, $generate = FALSE) {
    if (! $uuid = yamm_api_uuid_get($type, $identifier)) {
      if ($generate) {
        $uuid = yamm_api_uuid_create();
        yamm_api_uuid_save($uuid, $type, $identifier);
      }
      else {
        throw new Yamm_Entity_UnknownUuidException(
          "Unable to fetch UUID for type " . $type . ", identifier " . $identifier . ".");
      }
    }
    return $uuid;
  }

  /**
   * Get internal identifier of an object, using its UUID.
   *
   * @param string $uuid
   * 
   * @return int|string
   *   Internal identifier. For objects that are awaiting for integer, don't
   *   forget to cast NULL in case of failure.
   */
  public static function getIdentifierByUuid($uuid) {
    if ($uuid_data = yamm_api_uuid_load($uuid)) {
      return $uuid_data->identifier;
    }
    throw new Yamm_Entity_UnknownUuidException("UUID " . $uuid . " does not exists in database.");
  }

  /**
   * Return all known Yamm_Entity types.
   *
   * @return array
   */
  public static function getSupportedTypes() {
    return yamm_api_get_entities();
  }

  /**
   * Return settings class for given type.
   *
   * @param string $type
   * 
   * @return Yamm_EntitySettingsAbstract
   *   Specialized Yamm_EntitySettingsAbstract instance.
   */
  public static function getEntitySettingsInstance($type) {
    $class = self::findClass($type, Yamm_EntityFactory::CLASS_SETTINGS);
    return new $class();
  }

  /**
   * Execute given hook.
   *
   * @param string $hook
   *   Hook name
   * @param ..
   *   Parameters to gave to hooks
   * 
   * @return mixed
   *   Keyed array, keys are module name, values are hook return.
   */
  public static function executeHook($hook) {
    // Fetch hook parameters.
    $args = func_get_args();
    // Remove hook name from parameters.
    array_shift($args);
    // Real hook name, $hook parameter is the $op parameter
    array_unshift($args, 'yamm_entity_' . $hook);
    // Call it!
    return call_user_func_array('module_invoke_all', $args);
  }

  /**
   * Already loaded classes
   * 
   * @var array
   */
  private static $__classes = array();

  /**
   * Get class name by type.
   * 
   * @param string $type
   * @param int $class = Yamm_EntityFactory::CLASS_ENTITY
   *   One of the Yamm_EntityFactory::CLASS_* constants, determine which defined
   *   class it will return.
   * 
   * @return string
   *   Class name.
   */
  public static function getClassNameByType($type, $class = Yamm_EntityFactory::CLASS_ENTITY) {
    $className = NULL;

    switch ($class) {
      case Yamm_EntityFactory::CLASS_ENTITY:
        $className = 'Yamm_Entity_' . ucfirst(strtolower($type));
        break;
  
      case Yamm_EntityFactory::CLASS_SETTINGS:
        $className = 'Yamm_Entity_' . ucfirst(strtolower($type)) . 'Settings';
        break;
  
      default:
        throw new Yamm_Entity_ClassNotFoundException("Asked a wrong class type");
    }

    return $className;
  }

  /**
   * Find specialized entity class.
   *
   * @param string $type
   * @param int $class = Yamm_EntityFactory::CLASS_ENTITY
   *   One of the Yamm_EntityFactory::CLASS_* constants, determine which defined
   *   class it will return.
   * 
   * @return string
   *   Class name, or NULL in case of failure.
   */
  public static function findClass($type, $class = Yamm_EntityFactory::CLASS_ENTITY) {
    if (isset(self::$__classes[$class][$type])) {
      return self::$__classes[$class][$type];
    }

    $types = self::getSupportedTypes();

    if (! isset($types[$type])) {
      throw new Yamm_Entity_ClassNotFoundException("Unsupported entity type " . $type . " (not defined by a module).");
    }

    if (isset($types[$type]['file'])) {
      require_once($types[$type]['file']);
    }

    $className = self::getClassNameByType($type, $class);

    if (class_exists($className)) {
      self::$__classes[$class][$type] = $className;
      return $className;
    }

    throw new Yamm_Entity_ClassNotFoundException("Class " . $className . " is missing. Check your classes definition.");
  }
}

/**
 * This class represent a migrated object.
 * 
 * Remember that those objects are highly volatile. An Entity exists only the
 * short amount of time the transaction between client and server is running.
 * 
 * On the client side, you will have some misc. informations such as the
 * current parser instance running the transaction, which will allow you to
 * get back some information, such as file fetcher, that can be usefull for
 * file copy.
 */
abstract class Yamm_Entity
{
  /**
   * Create an instance by UUID.
   *
   * @param string $uuid
   * 
   * @return Yamm_Entity
   *   NULL in case of failure.
   */
  public static function loadByUuid($uuid) {
    if ($uuid_data = yamm_api_uuid_load($uuid)) {
      $class = Yamm_EntityFactory::findClass($uuid_data->type);
      return new $class($uuid, $uuid_data->identifier);
    }
    throw new Yamm_Entity_UnknownUuidException("UUID " . $uuid . " does not exists in database.");
  }

  /**
   * Serialize and entity to get through XML/RPC.
   *
   * @param Yamm_Entity $entity
   * 
   * @return string
   */
  public static function serialize(Yamm_Entity $entity) {
    return $entity->getType() . ':' . base64_encode(serialize($entity));
  }

  /**
   * Unserialize an Yamm_Entity instance.
   *
   * @param string $serializedEntity
   * 
   * @return Yamm_Entity
   *   Yamm_Entity sub class, or NULL if class definition not found.
   */
  public static function unserialize($serializedEntity) {
    $data = explode(':', $serializedEntity, 2);

    Yamm_EntityFactory::findClass($data[0]);
    $entity = unserialize(base64_decode($data[1]));

    if (! $entity instanceof Yamm_Entity) {
      throw new Yamm_Entity_UnableToUnserializeException("Unable to unserialize object.");
    }

    return $entity;
  }

  /**
   * Settings for this element.
   * 
   * @var Yamm_EntitySettingsAbstract
   */
  private $__settings = NULL;

  /**
   * Set settings.
   *
   * @param $settings
   * @return void
   */
  public function setSettings(Yamm_EntitySettingsAbstract $settings) {
    $this->__settings = $settings;
  }

  /**
   * Get settings.
   *
   * @return Yamm_EntitySettingsAbstract
   *   Return NULL if settings class does not exists or a new instance with
   *   defaults if none set.
   */
  public function getSettings() {
    if ($this->__settings) {
      return $this->__settings;
    }

    try {
      $class = Yamm_EntityFactory::findClass($this->__type, Yamm_EntityFactory::CLASS_SETTINGS);
      return new $class();
    }
    catch (Yamm_Entity_ClassNotFoundException $e) {
      return NULL;
    }
  }

  /**
   * @var Yamm_EntityParser
   */
  private $__parser;

  /**
   * Get current entity parser, in case we are manipulating the entity in
   * the client side of the pulling process context.
   * 
   * @return Yamm_EntityParser
   * 
   * @throws Yamm_Exception
   *   If entity is not in the client side pulling process.
   */
  public function getParser() {
    if (!isset($this->__parser)) {
      throw new Yamm_EntityException("Not in client side pulling context");
    }
    return $this->__parser;
  }

  /**
   * Internal type.
   * 
   * @var string
   */
  private $__type = 'void';

  /**
   * Get entity internal type.
   *
   * @return string
   */
  public function getType() {
    return $this->__type;
  }

  /**
   * Internal identifier.
   * 
   * @var int|string
   */
  private $__identifier = NULL;

  /**
   * Get internal identifier.
   *
   * @return int|string
   */
  public function getIdentifier() {
    return $this->__identifier;
  }

  /**
   * Main object to migrate.
   * 
   * @var mixed
   */
  private $__object;

  /**
   * UUID.
   * 
   * @var string
   */
  private $__uuid = NULL;

  /**
   * Get object UUID.
   *
   * @return string
   */
  public function getUuid() {
    return $this->__uuid;
  }

  /**
   * Specific data.
   * 
   * @var array
   */
  private $__data = array();

  /**
   * Helper to retrieve stored custom data.
   *
   * @param string $key
   * 
   * @return mixed
   */
  protected function _getData($key) {
    return $this->__data[$key];
  }

  /**
   * Helper to store custom data.
   *
   * @param string $key
   * 
   * @param mixed $value
   */
  protected function _setData($key, $value) {
    $this->__data[$key] = $value;
  }

  /**
   * Array of dependencies UUID.
   * 
   * @var array
   */
  private $__dependencies = array();

  /**
   * Get internal type.
   *
   * @return array
   *   UUID string array.
   */
  public function getDependencies() {
    return $this->__dependencies;
  }

  /**
   * Add a dependency into cache.
   *
   * If object does not have an UUID it will be created, then stored into the
   * local database.
   *
   * This method returns the object UUID, you can use this variable to store
   * it for further usage, in save() for example.
   *
   * @param string $type
   *   Object type to add as dependency.
   * @param int $idenfier
   *   Internal id of object.
   * 
   * @return string
   *   Object UUID you can then store as data.
   */
  protected function _addDependency($type, $idenfier) {
    $uuid = Yamm_EntityFactory::getUuidForType($type, $idenfier, TRUE);
    $this->__dependencies[$uuid] = $uuid;
    return $uuid;
  }

  /**
   * Main constructor.
   *
   * @param string $uuid
   * 
   * @return Yamm_Entity
   *   Any class subclassing Yamm_Entity.
   */
  protected function __construct($uuid, Yamm_EntityParser $parser = NULL) {
    $this->__uuid = $uuid;
    $uuid_data = yamm_api_uuid_load($uuid);
    $this->__identifier = $uuid_data->identifier;
    $this->__setTypeFromClass();
    if ($parser) {
      $this->__parser = $parser;
    }
    if (! $object = $this->_objectLoad($this->__identifier)) {
      throw new Yamm_Entity_UnableToLoadObjectException("Return object is null for type " . $this->__type . " and identifier " . $this->__identifier . ".");
    }
    $this->__object = $object;
    $this->_constructDependencies($this->__object);
    $this->__hookDependencies();
    $this->__hookData();
  }

  /**
   * Execute dependencies hook.
   */
  private function __hookDependencies() {
    $result = Yamm_EntityFactory::executeHook('data', $this->__type, $this->__identifier, $this->__object);
    // Add dependencies.
    if (! empty($result)) {
      foreach (result as $type => $identifierList) {
        foreach ($identifierList as $identifier) {
          $this->_addDependency($type, $identifier);
        }
      }
    }
  }

  /**
   * Execute data hook.
   */
  private function __hookData() {
    $result = Yamm_EntityFactory::executeHook('data', $this->__type, $this->__identifier, $this->__object);
    if (! empty($result)) {
      $this->_setData('_external_module_data', $result);
    }
  }

  /**
   * Execute presave hook.
   */
  private function __hookPresave() {
    Yamm_EntityFactory::executeHook('presave', $this->__type, $this->__object, $this->_getData('_external_module_data'));
  }

  /**
   * Execute update hook.
   */
  private function __hookUpdate() {
    Yamm_EntityFactory::executeHook('update', $this->__type, $this->__identifier, $this->__object, $this->_getData('_external_module_data'));
  }

  /**
   * Execute save hook.
   */
  private function __hookSave() {
    Yamm_EntityFactory::executeHook('save', $this->__type, $this->__identifier, $this->__object, $this->_getData('_external_module_data'));
  }

  private function __setTypeFromClass() {
    preg_match('/^Yamm_Entity_([a-zA-Z0-9]+)$/', get_class($this), $matches);
    $this->__type = strtolower($matches[1]);
  }

  /**
   * Save object on local Drupal. This should be only call within the
   * Yamm_EntityParser implementation.
   */
  public function save() {
    $this->__hookPresave();

    // Update object
    try {
      $this->__identifier = Yamm_EntityFactory::getIdentifierByUuid($this->__uuid);

      // Check object has not been deleted on client
      if (! $this->_objectExists()) {
        throw new Yamm_Entity_DeletedOnClientException("Object has been deleted");
      }

      $this->_update($this->__object, $this->__identifier);
      yamm_api_debug('Update ' . $this->__type . ' ' . $this->__identifier);

      $this->__hookUpdate();
    }

    // Insert again a deleted object
    catch (Yamm_Entity_DeletedOnClientException $e) {
      yamm_api_uuid_delete($this->__uuid);

      $this->__identifier = $this->_save($this->__object);

      if (! $this->__identifier) {
        yamm_api_debug('Insert (after deletion) failed ' . $this->__type);
        throw new Yamm_Entity_UnableToSaveObjectException("Object could not be saved (after deletion)");
      }

      yamm_api_uuid_save($this->__uuid, $this->__type, $this->__identifier);
      yamm_api_debug('Insert ' . $this->__type . ' ' . $this->__identifier);

      $this->__hookSave();
    }

    // Save a new object
    catch (Yamm_Entity_UnknownUuidException $e) {
      $this->__identifier = $this->_save($this->__object);

      if (! $this->__identifier) {
        yamm_api_debug('Insert failed ' . $this->__type);
        throw new Yamm_Entity_UnableToSaveObjectException("Object could not be saved");
      }

      yamm_api_uuid_save($this->__uuid, $this->__type, $this->__identifier);
      yamm_api_debug('Insert ' . $this->__type . ' ' . $this->__identifier);

      $this->__hookSave();
    }
  }

  /**
   * This method will only be ran to determine weither or not this object has
   * been deleted.
   * 
   * This is a default implementation, which will be a performance bottleneck,
   * but it will ensure a correct fallback for object that does not implement
   * it.
   * 
   * @return boolean
   *   True if object exists.
   */
  protected function _objectExists() {
    if ($this->_objectLoad($this->__identifier)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * This method must implement the correct load method for the targeted object
   * This object will be stored and passed from the server to the client.
   *
   * This method is only call once, in the constructor. Once stored, it will
   * passed as the $object parameter to all methods.
   *
   * @param string $identifier
   *   Internal identifier.
   * 
   * @return mixed
   *   Business object.
   */
  protected abstract function _objectLoad($identifier);

  /**
   * Override this method to load data from Drupal.
   * Note that in this method you also have to write all dependencies.
   *
   * This method is called by the constructor.
   *
   * Remember this method is only executed in the server, during dependency tree
   * construction.
   *
   * @param mixed $object
   *   The real object, loaded with _objectLoad() method.
   */
  protected abstract function _constructDependencies($object);

  /**
   * Save the object in the current Drupal database.
   * This method is called AFTER all the dependencies have been built.
   *
   * Remember that the given $object parameter is what you construct on server,
   * it should carry the server identifier, not the client one, so you'll have
   * to remove this identifier from the object before saving it.
   *
   * @param mixed $object
   *   The stored object during construct phase.
   * 
   * @return int|string
   *   New internal identifier on local site.
   */
  protected abstract function _save($object);

  /**
   * Update the object in the current Drupal database.
   * This method is called AFTER all the dependencies have been built.
   *
   * Remember that the given $object parameter is what you construct on server,
   * it should carry the server identifier, not the client one, so you'll have
   * to replace this identifier using the $identifier parameter before saving
   * it.
   *
   * The $identifier parameter is the object's internal identifier on client
   * site, that the API got back using the UUID mapping.
   *
   * @param mixed $object
   *   The stored object during construct phase.
   * @param int $identifier
   *   Internal identifier on local site.
   */
  protected abstract function _update($object, $identifier);
}
