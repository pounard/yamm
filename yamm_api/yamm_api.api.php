<?php
// $Id$

/**
 * @file
 * Yamm API hooks documentation.
 */

/**
 * Hook run when registering an entity on server side at pull time. You may use
 * this hook in order to register new entities. You also may use this hook in
 * order to store custom data that you will get back on the client side.
 * 
 * @param Yamm_Entity $entity
 *   Entity currently being constructed.
 */
function hook_yamm_entity_construct(Yamm_Entity $entity) {
  // FIXME: Sample code to do.
}

/**
 * Write this.
 * 
 * @param Yamm_Entity $entity
 *   Entity currently being constructed.
 */
function hook_yamm_entity_presave(Yamm_Entity $entity) {
  // FIXME: Sample code to do.
}

/**
 * Write this.
 * 
 * @param Yamm_Entity $entity
 *   Entity currently being constructed.
 */
function hook_yamm_entity_update(Yamm_Entity $entity) {
  // FIXME: Sample code to do.
}

/**
 * Write this.
 * 
 * @param Yamm_Entity $entity
 *   Entity currently being constructed.
 */
function hook_yamm_entity_save(Yamm_Entity $entity) {
  // FIXME: Sample code to do.
}
