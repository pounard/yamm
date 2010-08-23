<?php
// $Id$

/**
 * Default argument plugin to extract a node via menu_get_object
 */
class views_plugin_yamm_client_update extends views_plugin_argument_default
{
  function argument_form(&$form, &$form_state) {
  }

  function get_argument() {
  	// Hacky hacky hacky hacky...
  	global $yamm_client_update;
  	if (isset($yamm_client_update)) {
  		return $yamm_client_update;
  	}
  }
}

