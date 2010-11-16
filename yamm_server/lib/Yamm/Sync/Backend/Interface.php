<?php
// $Id$

/**
 * Sync profile backend interface.
 * 
 * The IFormable interface will allow the developer to describe a specific form
 * for the implementors objets configuration.
 */
interface Yamm_Sync_Backend_Interface extends IFormable, IOptionable
{
  /**
   * Get an array of phases. Each phase provides its own content.
   * 
   * @return array
   *   Array of string, phase names.
   */
  public function getPhases();

  /**
   * Fetch entities using the current profile.
   * 
   * @param int $limit = YAMM_SYNC_DEFAULT_LIMIT
   *   (optional) How much entities should it fetch.
   * @param int $offset = 0
   *   (optional) Starting offset.
   * @param string $phase = 'default'
   *   (optional) Current phase the transaction is.
   * 
   * @return array
   *   Array of Yamm_Entity instances.
   */
  public function getEntities($limit = YAMM_SYNC_DEFAULT_LIMIT, $offset = 0, $phase = 'default');
}

/**
 * Stub implementation that will allow the developer to skip the phase
 * attributes and parameters.
 */
abstract class Yamm_Sync_BackendAbstract extends Options implements Yamm_Sync_Backend_Interface
{
  /**
   * (non-PHPdoc)
   * @see Yamm_Sync_Backend_Interface::getPhases()
   */
  public function getPhases() {
    return array('default');
  }
}
