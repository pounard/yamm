<?php
// $Id: yamm_server.pull.inc,v 1.4 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Xmlrpc callback that link Yamm_Entity/Profile/Drupal module subsystems together.
 */

/**
 * Pull content implementation
 */
function _yamm_server_client_pull_cb($tid) {
  yamm_server_bootstrap_sync();
  return yamm_server_get_entities_from_profile($tid);
}

/**
 * Pull dependencies implementation
 */
function _yamm_server_client_pull_dependencies($tid, $uuid_array) {
  $ret = array();

  yamm_server_bootstrap_sync();

  $transaction = yamm_server_transaction_load($tid);
  $profile = SyncProfile::load($transaction->profile);

  foreach ($uuid_array as $uuid) {
    try {
      $entity = Yamm_Entity::loadByUuid($uuid);
      if ($settings = $profile->getSettingsForType($entity->getType())) {
        $entity->setSettings($settings);
      }
      $ret[] = Yamm_Entity::serialize($entity);
    }
    catch (Yamm_EntityException $e) {
      // We could not construct this entity, but treat the other.
      watchdog('yamm_pull', '@e',  array('@e' => $e->getMessage()), WATCHDOG_DEBUG);
    }
  }

  return $ret;
}

/**
 * Get a node array from a given view
 *
 * @param SyncProfile $profile
 * @return array
 *   Array of node objects
 *   FALSE if view is invalid
 */
function yamm_server_get_entities_from_profile($tid) {
  try {
    $ret = array();

    yamm_server_bootstrap_sync();

    // Hard coded limit, this will change
    $limit = 300;

    $transaction = yamm_server_transaction_load($tid);
    $profile = SyncProfile::load($transaction->profile);

    // Create empty context at first pull
    if (empty($transaction->state)) {
      $transaction->state = array('offset' => 0, 'finished' => array());
    }

    $views = $profile->getViews();

    // Transaction finished
    if (count($transaction->state['finished']) == count($views)) {
      return FALSE;
    }

    foreach ($views as $view) {

      // View finished
      if (in_array($view->name, $transaction->state['finished'])) {
        continue;
      }

      try {
        // Prepare view
        $view->set_display(NULL);
        $view->pre_execute();
        // Emulate incremental behavior using page feature
        $view->set_use_pager(TRUE);
        $view->set_items_per_page($limit);
        $view->set_offset($transaction->state['offset']);
        // Get results
        $view->execute();

        $count = count($view->result);
        watchdog('yamm_pull', 'View @view exports @count objects', array('@view' => $view->name, '@count' => $count), WATCHDOG_DEBUG);

        $entity_type = SyncProfile::getViewEntityType($view);

        foreach ($view->result as $result) {
          // We hope our user is not stupid, and set only one field which is the
          // internal object id.
          $result = (array) $result;
          $iid = array_shift($result);

          try {
            $uuid = Yamm_EntityFactory::getUuidForType($entity_type, $iid, TRUE);
            $entity = Yamm_Entity::loadByUuid($uuid);
            if ($settings = $profile->getSettingsForType($entity->getType())) {
              $entity->setSettings($settings);
            }
            $ret[] = Yamm_Entity::serialize($entity);
          }
          catch (Yamm_EntityException $e) {
            // We could not construct this entity, but treat the other.
            watchdog('yamm_pull', '@e', array('@e' => $e->getMessage()), WATCHDOG_ERROR);
          }
        }

        // Check we have finished the view results.
        if ($count < $limit) {
          $transaction->state['finished'][] = $view->name;
          $transaction->state['offset'] = 0;
        }
        else {
          $transaction->state['offset'] += $limit;
        }

        yamm_server_transaction_update_state($transaction);
        break;
      }
      catch (SyncProfileException $e) {
        // We could not load this view, but treat others
        $transaction->state['finished'][] = $view->name;
        $transaction->state['offset'] = 0;
        yamm_server_transaction_update_state($transaction);
        watchdog('yamm_pull', '@e', array('@e' => $e->getMessage()), WATCHDOG_ERROR);
      }

      // Keep some memory to survive.
      unset($view);
    }

    return $ret;
  }
  catch (SyncProfileException $e) {
    watchdog('yamm_pull', '@e', array('@e' => $e->getMessage()), WATCHDOG_ERROR);
  }

  // Whatever happens, if we get here, we had an unrecoverable error, return
  // FALSE to notify client something wrong happened.
  return FALSE;
}
