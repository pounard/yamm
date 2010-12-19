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
 * 
 * The Options class gives us the IRegistrable and IOptionnable interfaces
 * implementation, which is fully compatible with what we want here.
 * 
 * Registrable interface will allow us to use the type property standing for
 * entity type, the optionable interface will carry the data we need. 
 */
abstract class Yamm_EntitySettingsAbstract extends Options implements IFormable
{
  public function __construct() {
    $this->__setTypeFromClass();
  }

  private function __setTypeFromClass() {
    //yamm_api_debug("Attempt to get type from " . get_class($this), NULL);
    preg_match('/^Yamm_Entity_(.*)Settings$/', get_class($this), $matches);
    $this->_type = strtolower($matches[1]);
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
abstract class Yamm_Entity extends Registrable
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
      return oox_registry_get('yamm_entity')->getItem($uuid_data->type, $uuid);
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

    $entity = unserialize(base64_decode($data[1]));

    if (!$entity instanceof Yamm_Entity) {
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
      return Yamm_EntityFactory::getEntitySettingsInstance($this->_type);
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
   * Set the current entity parser. Use this only if you are in the parser
   * itself.
   * 
   * @param Yamm_EntityParser $parser
   */
  public function setParser(Yamm_EntityParser $parser) {
    $this->__parser = $parser;
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

  public function getObject() {
    return $this->__object;
  }

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
  public function getData($key) {
    return $this->__data[$key];
  }

  /**
   * Helper to store custom data.
   *
   * @param string $key
   * 
   * @param mixed $value
   */
  public function setData($key, $value) {
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
   * @param int $identifier
   *   Internal id of object.
   * 
   * @return string
   *   Object UUID you can then store as data.
   */
  public function addDependency($type, $identifier) {
    $uuid = Yamm_EntityFactory::getUuidForType($type, $identifier, TRUE);
    $this->__dependencies[$uuid] = $uuid;
    return $uuid;
  }

  /**
   * Differed constructor. This method is being called at instanciation time.
   *
   * @param string $uuid
   * 
   * @return Yamm_Entity
   *   Any class subclassing Yamm_Entity.
   */
  public function _setUuid($uuid)  {
    $this->__uuid = $uuid;
    $uuid_data = yamm_api_uuid_load($uuid);
    $this->__identifier = $uuid_data->identifier;
    if ($parser) {
      $this->__parser = $parser;
    }
    if (! $object = $this->_objectLoad($this->__identifier)) {
      throw new Yamm_Entity_UnableToLoadObjectException("Return object is null for type " . $this->_type . " and identifier " . $this->__identifier . ".");
    }
    $this->__object = $object;
    $this->_constructDependencies($this->__object);
    $this->__hookConstruct();
  }

  /**
   * Execute dependencies hook.
   */
  private function __hookConstruct() {
    $result = Yamm_EntityFactory::executeHook('construct', $this);
  }

  /**
   * Execute presave hook.
   */
  private function __hookPresave() {
    Yamm_EntityFactory::executeHook('presave', $this);
  }

  /**
   * Execute update hook.
   */
  private function __hookUpdate() {
    Yamm_EntityFactory::executeHook('update', $this);
  }

  /**
   * Execute save hook.
   */
  private function __hookSave() {
    Yamm_EntityFactory::executeHook('save', $this);
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
      yamm_api_debug('Update @entity', array('@entity' => $this));

      $this->__hookUpdate();
    }

    // Insert again a deleted object
    catch (Yamm_Entity_DeletedOnClientException $e) {
      yamm_api_uuid_delete($this->__uuid);

      $this->__identifier = $this->_save($this->__object);

      if (! $this->__identifier) {
        yamm_api_debug('Insert after deletion failed for @entity', array('@entity' => $this));
        throw new Yamm_Entity_UnableToSaveObjectException("Object could not be saved (after deletion)");
      }

      yamm_api_uuid_save($this->__uuid, $this->_type, $this->__identifier);
      yamm_api_debug('Insert after deletion for @entity', array('@entity' => $this));

      $this->__hookSave();
    }

    // Save a new object
    catch (Yamm_Entity_UnknownUuidException $e) {
      $this->__identifier = $this->_save($this->__object);

      if (! $this->__identifier) {
        yamm_api_debug('Insert failed for @entity', array('@entity' => $this));
        throw new Yamm_Entity_UnableToSaveObjectException("Object could not be saved");
      }

      yamm_api_uuid_save($this->__uuid, $this->_type, $this->__identifier);
      yamm_api_debug('Insert @entity', array('@entity' => $this));

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
