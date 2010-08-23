<?php
// $Id: View.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Node entity for Yamm
 */

/**
 * Simple Yamm_Entity node implementation
 */
class Yamm_Entity_View extends Yamm_Entity {

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_objectLoad($identifier)
   */
  protected function _objectLoad($identifier) {
    views_include('view');
    return views_get_view($identifier, TRUE)->export();
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_constructDependencies($object)
   */
  protected function _constructDependencies($object) {
    // TODO For later use, maybe modules enable should be a good thing?
    // Don't known, what else than module can be a dependency?
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_save($object)
   */
  protected function _save($object) {
    // TODO we should check for all dependencies which are not data, such as
    // handlers and modules (this is done by views_export module).
    views_include('view');
    // Should give us the '$view' variable
    eval($object);
    $view->save();
    views_ui_cache_set($view);
    menu_rebuild();
    cache_clear_all('*', 'cache_views');
    cache_clear_all();
    return $view->name;
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_update($object, $identifier)
   */
  protected function _update($object, $identifier) {
    // TODO we should check for all dependencies which are not data, such as
    // handlers and modules (this is done by views_export module).
    views_include('view');
    // Should give us the '$view' variable
    eval($object);
    $view->save();
    views_ui_cache_set($view);
    menu_rebuild();
    cache_clear_all('*', 'cache_views');
    cache_clear_all();
  }
}
