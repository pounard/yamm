<?php
// $Id: EntityFetcherAbstract.php,v 1.2 2010/05/12 16:20:39 pounard Exp $

/**
 * Default fetcher exception.
 */
class Yamm_EntityFetcherException extends Yamm_EntityException {}

/**
 * Get dependencies abstraction.
 */
interface Yamm_EntityFetcherInterface
{
  /**
   * Do initial pull.
   *
   * @return mixed
   *   Array of entities. FALSE when no entities from server.
   * 
   * @throws Yamm_EntityFetcherException
   *   In case of network error.
   */
  public function pull();

  /**
   * Fetch all entity dependencies.
   *
   * @param array $dependencies
   *   Entities UUID array to fetch. 
   * 
   * @return array
   *   Array of Yamm_Entity instances.
   * 
   * @throws Yamm_EntityFetcherException
   *   In case of network error.
   */
  public function fetchDependencies(array $dependencies);
}

/**
 * Get dependencies abstraction. This minor implementation will allow
 * extending classes not reinvent the wheel.
 */
abstract class Yamm_EntityFetcher implements Yamm_EntityFetcherInterface
{
  /**
   * (non-PHPdoc)
   * @see Yamm_EntityFetcherInterface::pull()
   */
  public function pull() {
    $entities = $this->_pull();

    if (! is_array($entities)) {
      throw new Yamm_EntityFetcherException("No result from fetcher");
    }

    return $entities;
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntityFetcherInterface::fetchDependencies()
   */
  public function fetchDependencies(array $dependencies) {
    // Shortcut to avoid fetcher implementation try to fetch empty data
    if (empty($dependencies)) {
      return array();
    }

    $entities = $this->_fetchDependencies($dependencies);

    if (! is_array($entities)) {
      throw new Yamm_EntityFetcherException("No result from fetcher");
    }

    return $entities;
  }

  /**
   * Override this method with your business stuff.
   *
   * @return array
   *   Array of entities.
   */
  protected abstract function _pull();

  /**
   * Override this method with your business stuff.
   *
   * @param array &$uuid_array
   *   Array of UUID string.
   * 
   * @return array
   *   Array of entities.
   */
  protected abstract function _fetchDependencies(array &$uuid_array);
}
