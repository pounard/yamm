<?php
// $Id$

/**
 * Entity factory specific implementation.
 */
class Yamm_EntityRegistry extends OoxRegistry
{
  /**
   * This will be used at cache construction time only. It will be able to check
   * for base table conflicts between _checkItem calls.
   * 
   * @var array
   */
  private $__baseTables = array();

  /**
   * (non-PHPdoc)
   * @see OoxRegistry::_checkItem()
   */
  protected function _checkItem($type, &$item, $module) {
    if (!parent::_checkItem($type, $item, $module)) {
      return FALSE;
    }
    // Check for a given base table.
    if (isset($definition['base_table'])) {
      if (isset($this->__baseTables[$definition['base_table']])) {
        watchdog('yamm_api', "Entity '" . $type . "' defined by module '@module' conflicts, it uses an already defined base_table", NULL, WATCHDOG_ALERT);
        continue;
      } 
      else {
        $this->__baseTables[$definition['base_table']] = TRUE;
      }
    }
    return TRUE;
  }

  /**
   * Override of the parent class method that will force the $uuid parameter to
   * be set at instanciation time.
   * 
   * @see OoxRegistry::getItem()
   */
  public function getItem($type, $uuid = NULL) {
    if (!$uuid) {
      throw new Yamm_EntityException("Empty uuid given while attempting to get an '" . $type . "' entity instance.");
    }

    $item = parent::getItem($type);
    $item->_setUuid($uuid);

    return $item;
  }
}