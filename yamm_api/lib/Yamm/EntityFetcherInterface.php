<?php
// $Id: EntityFetcherInterface.php,v 1.1 2010/05/12 16:20:39 pounard Exp $

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
