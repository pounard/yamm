<?php
// $Id$

/**
 * @file
 * Yamm API hooks documentation.
 */

/**
 * Hook run when registering an entity on server side at pull time. You may use
 * this hook in order to register new entities.
 * 
 * @param string $type
 *   Entity type.
 * @param int|string $identifier
 *   Entity identifier.
 * @param mixed $object
 *   Entity object.
 * 
 * @return array
 *   Key/value pairs. Keys are dependencies types, values are arrays of
 *   identifier values for the associated type key.
 */
function hook_yamm_entity_dependencies($type, $identifier, $object) {
  // FIXME: Sample code to do.
}

/**
 * Hook run when registering an entity on server side at pull time. You may use
 * this hook in order to store custom data that you will get back on the client
 * side.
 * 
 * @param string $type
 *   Entity type.
 * @param int|string $identifier
 *   Entity identifier.
 * @param mixed $object
 *   Entity object.
 * 
 * @return array
 *   Key/value pairs. Keys are string (data identifier) and values are mixed
 *   variables (which must be serializable).
 */
function hook_yamm_entity_data($type, $identifier, $object) {
  // FIXME: Sample code to do.
}

/**
 * Write this.
 * 
 * @param string $type
 *   Entity type.
 * @param int|string $identifier
 *   Entity identifier.
 * @param mixed $objecty
 *   Entity object.
 * @param array $data = array()
 *   Custom data set by the hook_yamm_entity_data() hook.
 */
function hook_yamm_entity_presave($type, $object, $data = array()) {
  // FIXME: Sample code to do.
}

/**
 * Write this.
 * 
 * @param string $type
 *   Entity type.
 * @param int|string $identifier
 *   Entity identifier.
 * @param mixed $object
 *   Entity object.
 * @param array $data = array()
 *   Custom data set by the hook_yamm_entity_data() hook.
 */
function hook_yamm_entity_update($type, $identifier, $object, $data = array()) {
  // FIXME: Sample code to do.
}

/**
 * Write this.
 * 
 * @param string $type
 *   Entity type.
 * @param int|string $identifier
 *   Entity identifier.
 * @param mixed $object
 *   Entity object.
 * @param array $data = array()
 *   Custom data set by the hook_yamm_entity_data() hook.
 */
function hook_yamm_entity_save($type, $identifier, $object, $data = array()) {
  // FIXME: Sample code to do.
}
