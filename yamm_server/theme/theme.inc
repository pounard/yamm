<?php
// $Id: yamm_server.theme,v 1.3 2009/11/05 20:19:36 pounard Exp $

/**
 * @file
 * Yamm server theme() functions implementation.
 */

/**
 * Theme a return to back page link in case we have the destination parameter.
 * 
 * @return string
 *   (x)html output
 */
function theme_yamm_server_page_back() {
  // I really do not like the $_REQUEST variable
  if (isset($_REQUEST['destination']) && $destination = $_REQUEST['destination']) {
    return '<p>' . l(t('Back to previous page'), $destination) . '</p>';
  }
}

/**
 * Theme status human readable name
 * 
 * @param int $status
 *   One of the YAMM_STATUS_* constant
 * @return string
 *   (x)html output
 */
function theme_yamm_server_status($status) {
  $output = '';
  switch ($status) {
    case YAMM_STATUS_ACTIVATE:
      $output .= t('Activated');
      break;
    case YAMM_STATUS_SUSPEND:
      $output .= t('Suspended');
      break;
    default:
      $output .= t('Unknown');
  }
  return $output;
}
