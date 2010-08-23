<?php
// $Id: EntityParser.php,v 1.3 2010/05/12 16:20:39 pounard Exp $

class Yamm_EntityParserNoFetcherException extends Yamm_EntityException {}

/**
 * Dependecy parser and object saving engine.
 */
class Yamm_EntityParser
{
  /**
   * Fetcher instance.
   * 
   * @var Yamm_EntityFetcherAbstract
   */
  protected $_fetcher = NULL;

  /**
   * Main constructor.
   *
   * @param Yamm_EntityFetcherAbstract $fetcher
   *   Fetcher to use for this parsgin.
   */
  public function __construct(Yamm_EntityFetcherInterface $fetcher) {
    $this->_fetcher = $fetcher;
  }

  /**
   * Parse entities, fetch dependencies, and save them.
   *
   * @param array $entities
   *   Array of Yamm_Entity instances.
   */
  public function parse() {
    while ($entities = $this->_fetcher->pull()) {
      foreach ($entities as $entity) {
        if (! $this->_alreadyBuilt($entity)) {
          $this->_buildDependencies($entity);
        }
      }
    }
  }

  /**
   * Circular dependencies break.
   * 
   * @var array
   */
  protected $_built = array();

  /**
   * Check Yamm_Entity has already been built and mark it built if not.
   *
   * @param Yamm_Entity $entity
   *   Entity that potentially already have been saved.
   * 
   * @return boolean
   */
  protected function _alreadyBuilt(Yamm_Entity $entity) {
    $uuid = $entity->getUuid();
    // array_key_exists performance is way better than any other method
    if (! array_key_exists($uuid, $this->_built)) {
      $this->_built[$uuid] = TRUE;
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * Remove all entities that already have been built from dependency
   * array.
   */
  protected function _pruneDependencies(array &$uuid_array) {
    foreach ($uuid_array as $key => $uuid) {
      if (array_key_exists($uuid, $this->_built)) {
        unset($uuid_array[$key]);
      }
    }
  }

  /**
   * Build a full tree of dependencies.
   *
   * @param Yamm_Entity $entity
   *   Entity for which to fetch dependencies.
   */
  protected function _buildDependencies(Yamm_Entity $entity) {
    $dependencies = $entity->getDependencies();
    $this->_pruneDependencies($dependencies);
    yamm_api_debug("Entity parser got @count dependencies", array('@count' => count($dependencies)));

    // Go and unpack them
    foreach ($this->_fetcher->fetchDependencies($dependencies) as $depEntity) {

      // Check for already builded ones (circular dependencies)
      if (! $this->_alreadyBuilt($depEntity)) {

        // And build it
        $this->_buildDependencies($depEntity);
      }
    }

    $entity->save();
  }
}
