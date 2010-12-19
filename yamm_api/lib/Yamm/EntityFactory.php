<?php
// $Id$

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
    if (!$uuid = yamm_api_uuid_get($type, $identifier)) {
      if ($generate) {
        $uuid = yamm_api_uuid_create();
        yamm_api_uuid_save($uuid, $type, $identifier);
      }
      else {
        throw new Yamm_Entity_UnknownUuidException("Unable to fetch UUID for type " . $type . ", identifier " . $identifier . ".");
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
   * Return settings class for given type.
   *
   * FIXME: This should be refactored.
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
   * Find specialized entity class.
   *
   * FIXME: This needs refactor, and a better pattern for settings objects.
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
    $types = oox_registry_get('yamm_entity')->getItemCache();

    if (!isset($types[$type])) {
      throw new Yamm_Entity_ClassNotFoundException("Unsupported entity type " . $type . " (not defined by a module).");
    }

    // This particularly should be refactored, this is ugly.
    $className = $types[$type]['class'];
    if ($class == Yamm_EntityFactory::CLASS_SETTINGS) {
      $className .= 'Settings';
    }

    if (class_exists($className)) {
      return $className;
    }

    throw new Yamm_Entity_ClassNotFoundException("Class " . $className . " is missing. Check your classes definition.");
  }
}
