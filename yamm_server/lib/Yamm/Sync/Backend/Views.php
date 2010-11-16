<?php
// $Id$

/**
 * Views module backend for sync profiles.
 */
class Yamm_Sync_Backend_Views extends Yamm_Sync_BackendAbstract
{
  /**
   * Load a view from its name.
   *
   * @param string $view_name
   * 
   * @return view
   * 
   * @throws Yamm_Sync_ProfileException
   */
  public static function loadView($view_name) {
    if ($view = views_get_view($view_name)) {
      return $view;
    }
    throw new Yamm_Sync_ProfileException("View " . $view_name . " does not exists");
  }

  /**
   * Get the Yamm_Entity internal type linked to a node.
   *
   * @param view $view
   * 
   * @return string
   * 
   * @throws Yamm_Sync_ProfileException
   */
  public static function getViewEntityType(view $view) {
    $supported = Yamm_EntityFactory::getSupportedTypes();
    foreach ($supported as $type => &$desc) {
      if (isset($desc['base_table']) && $view->base_table == $desc['base_table']) {
        return $type;
      }
    }
    throw new Yamm_Sync_ProfileException("Views does not rely on a known base table");
  }

  /**
   * Get all configured views
   *
   * @return array
   *   Array of view instances
   */
  public function getViews() {
    $ret = array();

    if (isset($this->_options['views'])) {
      foreach ($this->_options['views'] as $view_name) {
        $ret[] = self::loadView($view_name);
      }
    }

    return $ret;
  }

  /**
   * Add a view to profile
   *
   * @param string|view $view
   *   View name or instance.
   *   
   * @return void
   * 
   * @throws Yamm_Sync_ProfileException
   */
  public function addView($view) {
    $loaded = FALSE;

    if (is_string ($view)) {
      $view = self::loadView($view);
      $loaded = TRUE;
    }
    else if ($view instanceof view) {
      // Nothing to do here.
    }
    else {
      throw new Yamm_Sync_ProfileException("Invalid view");
    }

    // Check view is ok
    self::getViewEntityType($view);

    if (! in_array($view->name, $this->_options['views'])) {
      $this->_options['views'][] = $view->name;
    }

    if ($loaded) {
      // Free some memory.
      $view->destroy();
      unset($view);
    }
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_Sync_Backend_Interface::getPhases()
   */
  public function getPhases() {
    return $this->_options['views'];
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_Sync_Backend_Interface::getEntities()
   */
  public function getEntities($limit = YAMM_SYNC_DEFAULT_LIMIT, $offset = 0, $phase = 'default') {
    $ret = array();

    if (!$view = self::loadView($phase)) {
      throw new Yamm_Sync_ProfileException("View '" . $phase . "' does not exist.");
    }

    try {
      // Prepare view
      $view->set_display(NULL);
      $view->pre_execute();
      // Emulate incremental behavior using page feature
      $view->set_use_pager(TRUE);
      $view->set_items_per_page($limit);
      $view->set_offset($offset);
      // Get results
      $view->execute();

      $count = count($view->result);
      watchdog('yamm', 'View @view exports @count objects', array('@view' => $view->name, '@count' => $count), WATCHDOG_DEBUG);

      $entity_type = Yamm_Sync_Backend_Views::getViewEntityType($view);

      foreach ($view->result as $result) {
        // We hope our user is not stupid, and set only one field which is the
        // internal object id.
        // FIXME: We could use the base field here.
        $result = (array) $result;
        $identifier = array_shift($result);

        try {
          $uuid = Yamm_EntityFactory::getUuidForType($entity_type, $identifier, TRUE);
          $entity = Yamm_Entity::loadByUuid($uuid);
          $ret[] = $entity;
        }
        catch (Yamm_EntityException $e) {
          // Just alert the site admin this entity build failed, but continue
          // with others.
          watchdog('yamm', '@e', array('@e' => $e->getMessage()), WATCHDOG_ERROR);
        }
      }
    }
    catch (Yamm_Sync_ProfileException $e) {
      watchdog('yamm_pull', '@e', array('@e' => $e->getMessage()), WATCHDOG_ERROR);
    }

    // Keep some memory to survive.
    $view->destroy();
    unset($view);

    return $ret;
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::form()
   */
  public function form() {
    yamm_api_bootstrap_entity();

    // Building views list.
    $base_table = $available_views = $selected_views = array();
    $views_list = views_get_all_views();
    $entities = yamm_api_get_entities();

    foreach ($entities as $type => $value) {
      // Getting all base table with entities.
      if (isset($value['base_table'])) {
        $base_table[$value['base_table']] = $value['base_table'];
      }
    }

    foreach ($views_list as $name => $view) {
      // Check if the view has a known base table for entities.
      if (in_array($view->base_table, $base_table)) {
        $available_views[$view->name] = '<strong>' . $view->name . '</strong> - ' . filter_xss_admin($view->description);
        $default_views[$view->name] = 0;
      }
    }

    // Saved selected views.
    $selected_views = $this->getViews();

    // Check all selected views.
    foreach ($selected_views as $view) {
      //  Check if view has not been deleted. 
      if (key_exists($view->name, $default_views)) {
        $default_views[$view->name] = $view->name;
      }
    }

    $form['views'] = array(
      '#type' => 'checkboxes',
      '#options' => $available_views,
      '#default_value' => $default_views,
      '#title' => t('Views that export content'),
      '#description' => t('Select views that export content. Only the default display will be use for content export. All select views will be used at each client synchronization whatever is the entity type they return.'),
      '#required' => TRUE,
      '#multiple' => TRUE,
    );

    return $form;
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::formValidate()
   */
  public function formValidate(&$values) { }

  /**
   * (non-PHPdoc)
   * @see IFormable::formSubmit()
   */
  public function formSubmit(&$values) {
    $this->_options['views'] = array();

    foreach ($values['views'] as $view_name => $enabled) {
      if ($enabled) {
        $this->_options['views'][] = $view_name;
      }
    }
  }
}
