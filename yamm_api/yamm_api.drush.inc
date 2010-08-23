<?php
// $Id: yamm_api.drush.inc,v 1.1 2009/11/05 20:19:36 pounard Exp $

/**
 * @file
 * Yamm server Drush integration
 */

/**
 * Implementation of hook_drush_command().
 */
function yamm_api_drush_command() {
  $items = array();

  // Client handling stuff
  $items['yamm api datasync rebuild'] = array(
    'callback' => 'yamm_api_drush_datasync_rebuild',
    'description' => 'Try to automatically rebuild DataSync configuration to make it works on localhost',
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function yamm_api_drush_help($section) {
  switch ($section) {
    // Client handling stuff
    case 'drush:yamm api datasync rebuild':
      return dt("No help");
  }
}

function yamm_api_drush_datasync_rebuild() {
  yamm_api_rebuild_datasync();
}
