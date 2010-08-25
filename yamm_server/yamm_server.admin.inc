<?php
// $Id: yamm_server.admin.inc,v 1.4 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Yamm server admin pages and forms.
 */

/**
 * Multistep handling for profile overview
 */
function yamm_server_profile_overview($form_state) {
  if (! empty($form_state) && isset($form_state['op']) && ! empty($form_state['profiles'])) {
    // Multistep
    return _yamm_server_profile_overview_confirm($form_state);
  }
  else {
    return _yamm_server_profile_overview();
  }
}

/**
 * Profile overview page
 */
function _yamm_server_profile_overview() {
  yamm_server_bootstrap_sync();

  $form = array();
  $form['#tree'] = TRUE;

  $form['options'] = array(
    '#type'        => 'fieldset',
    '#title'       => t('Operations'),
    '#collapsible' => FALSE,
  );
  $form['options']['op'] = array(
    '#type'          => 'select',
    '#default_value' => 'default',
    '#options'       => array(
      'default' => t('Set selected as default'),
      'delete'  => t('Delete selected'),
    ),    
  );
  $form['options']['submit'] = array(
    '#type'   => 'submit',
    '#value'  => t('Update'),
    '#submit' => array('yamm_server_profile_overview_update'),
  );

  $form['profiles'] = array();

  $headers = array(
    array('data' => t('Default')),
    array('data' => t('Profile')),
    array('data' => t('Name')),
  );

  $element = 0;
  $profiles = SyncProfile::getList(10, $element);

  array_unshift($headers, theme('table_select_header_cell'));
  $form['profiles']['header'] = array('#type' => 'value', '#value' => &$headers);

  $checkboxes = array();
  $radios = array();

  // Display all known profile
  foreach ($profiles as $profile => $name) {
    $checkboxes[$profile] = '';
    $radios[$profile]     = '';
    
    $form['profiles']['profile'][$profile] = array('#value' => l($profile, "admin/yamm/profile/edit/{$profile}"));
    $form['profiles']['name'][$profile]    = array('#value' => check_plain($name));
  }

  $form['profiles']['checkboxes'] = array('#type' => 'checkboxes', '#options' => isset($checkboxes) ? $checkboxes : array());
  $form['profiles']['radios']     = array('#type' => 'radios', '#options' => isset($radios) ? $radios : array(), '#default_value' => SyncProfile::getDefaultProfile());
  
  $form['#theme'] = 'yamm_server_profile_overview';
  $form['footer'] = array('#type' => 'markup', '#value' => theme('pager', $element));
  
  // Adding the add profile button
  $form['profile_add'] = array(
    '#type'   => "submit",
    '#value'  => t("Add a new profile"),
    '#submit' => array('_yamm_server_profile_create_submit'),
  );

  return $form;
}

/**
 * Handler submit to create profile
 * @param $form
 * @param $form_state
 * @return unknown_type
 */
function _yamm_server_profile_create_submit($form, &$form_state) {
  $form_state['redirect'] = "admin/yamm/profile/add";
}

/**
 * Multistep confirm callback for profile overview
 */
function _yamm_server_profile_overview_confirm($form_state) {
  $form = array();

  $form['profiles'] = array(
    '#type'  => 'value',
    '#value' => serialize($form_state['profiles']),
  );

  switch ($form_state['op']) {
    case 'delete':
      $question    = t('Are you sure you want to delete these profiles?');
      $description = t('They will be definitely removed. You won\'t be able to restore them.');
      $submit      = '_yamm_server_profile_list_remove_submit';
      break;
  }

  $form = confirm_form($form, $question, ($form_state['redirect'] ? $form_state['redirect'] : $_GET['q']), $description);
  $form['#submit'][] = $submit;

  return $form;
}

function _yamm_server_profile_list_remove_submit($form, &$form_state) {
  yamm_server_bootstrap_sync();

  $profiles = unserialize($form_state['values']['profiles']);

  foreach ($profiles as $profile) {
    SyncProfile::delete($profile);
    drupal_set_message(t('Profile !profile_name deleted.', array('!profile_name' => $profile)));
  }
}

/**
 * Submit handler profile overview
 */
function yamm_server_profile_overview_update($form, &$form_state) {
  yamm_server_bootstrap_sync();
  $op = $form_state['values']['options']['op'];

  switch ($op) {
    case 'default':
      if (! empty($form_state['values']['profiles']['radios'])) {
        drupal_set_message(t('Default profile updated.'));
        SyncProfile::setDefaultProfile($form_state['values']['profiles']['radios']);
      }
      break;

    case 'delete':
      // Multistep handling
      $form_state['rebuild']  = TRUE;
      $form_state['op']       = $op;
      $form_state['profiles'] = $form_state['values']['profiles']['checkboxes'];
      break;

    default:
      drupal_set_message(t('Not implemented yet'));
      break;
  }
}

/**
 * Edit/create profile form
 */
function yamm_server_profile($form_state, $profile = NULL) {
  yamm_server_bootstrap_sync();

  $form = array();

  if (! $profile) {
    $update = FALSE;
  }
  else {
    drupal_set_title(t('Edit profile'));
    $update = $profile->getInternalName();
  }

  $form['update'] = array('#type' => 'value', '#value' => $update);

  $form['name'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Profile name'),
    '#required'      => TRUE,
    '#default_value' => ($profile instanceof SyncProfile ? $profile->getName() : '')
  );

  $form['internal_name'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Internal name'),
    '#required'      => TRUE,
    '#default_value' => ($profile instanceof SyncProfile ? $profile->getInternalName() : ''),
    '#description'   => t('Must contains only alphanumeric caracters or _')
  );
  
  // Building views list
  $base_table          = array();
  $available_views     = array();
  $selected_views      = array();
  $views_list          = views_get_all_views();
  $entities            = yamm_api_get_entities();
  
  foreach ($entities as $type => $value) {
    // Getting all base table with entities
    if (isset($value['base_table'])) {
      $base_table[$value['base_table']] = $value['base_table'];
    }
  }
  
  foreach ($views_list as $name => $view) {
    // Check if the view has a known base table for entities
    if (in_array($view->base_table, $base_table)) {
      $available_views[$view->name] = $view->name;
      $default_views[$view->name] = 0;
    }
  }
  
  // Enable saved checkboxes
  if ($profile instanceof SyncProfile) {
    // Saved selected views
    $selected_views = $profile->getViews();
    
    // Check all selected views
    foreach ($selected_views as $view) {
      //  Check if view has not been deleted 
      if (key_exists($view->name, $default_views)) {
        $default_views[$view->name] = $view->name;
      }
    }
  }

  $form['views'] = array(
    '#type'          => 'checkboxes',
    '#options'       => $available_views,
    '#default_value' => $default_views,
    '#title'         => t('Views that export content'),
    '#description'   => t('Select views that export content'),
    '#required'      => TRUE,
    '#multiple'      => TRUE,
  );

  $form['submit'] = array(
    '#type'  => 'submit',
    '#value' => $update ? t('Update') : t('Save')
  );
  
  if ($update) {
    $form['delete-profile'] = array(
      '#type'   => 'submit',
      '#value'  => t('Delete profile'),
      '#submit' => array("_yamm_profile_delete_profile_form_submit"),
    );
    
    $form['cancel'] = array(
      '#type'   => 'submit',
      '#value'  => t('Back to list'),
      '#submit' => array("_yamm_profile_back_form_submit"),
    );
  }

  return $form;
}

function yamm_server_profile_validate($form, &$form_state) {
  // Check internal name
  if (preg_match('/[^a-zA-Z0-9_]/', trim($form_state['values']['internal_name']))) {
    form_set_error('internal_name', t('View name must be alphanumeric or underscores only.'));
  }
}

function yamm_server_profile_submit($form, &$form_state) {
  yamm_server_bootstrap_sync();

  $internal = trim($form_state['values']['internal_name']);
  $name = trim($form_state['values']['name']);

  if ($form_state['values']['update']) {
    $profile = SyncProfile::load($form_state['values']['update']);
  }
  else {
    $profile = new SyncProfile();
  }

  $profile->setInternalName($internal);
  $profile->setName($name);

  $profile->purgeViews();
  
  foreach ($form_state['values']['views'] as $key => $view_name) {
    if ($key == (string)$view_name) {
      $profile->addView($view_name);
    }  
  }

  $profile->save();

  if ($form_state['values']['profiles']) {
    drupal_set_message(t('Profile @profile updated', array('@profile' => check_plain($profile->getName()))));
  }
  else {
    drupal_set_message(t('Profile @profile created', array('@profile' => check_plain($profile->getName()))));
  }

  $form_state['redirect'] = 'admin/yamm/profile';
}

/**
 * Handler to redirect on confirmation form
 * @param $form
 * @param $form_state
 * @return unknown_type
 */
function _yamm_profile_delete_profile_form_submit($form, &$form_state) {
  $form_state['redirect'] = "admin/yamm/profile/delete/{$form_state['values']['internal_name']}";
}

/**
 * 
 * @param $form_state
 * @param $internal_name
 * @return unknown_type
 */
function yamm_profile_delete_confirm($form_state, $internal_name) {
  $form = array();
  
  $form['internal_name'] = array(
    '#type'  => 'hidden',
    '#value' => $internal_name,
  );
  
  return confirm_form($form, t('Are you sure you want to delete profile !profile_name ?', array('!profile_name' => $internal_name)), "admin/yamm/profile/edit/{$internal_name}");
}

/**
 * Handler to delete profile
 * @return unknown_type
 */
function yamm_profile_delete_confirm_submit($form, &$form_state) {
  yamm_server_bootstrap_sync();
  
  // Deleting the selected profile
  SyncProfile::delete($form_state['values']['internal_name']);
  
  drupal_set_message(t('Profile !profile_name deleted.', array("!profile_name" => $form_state['values']['internal_name'])));
  
  // Redirect to profile list
  $form_state['redirect'] = 'admin/yamm/profile';
}

/**
 * Handler to back to profile list
 * @param $form
 * @param $form_state
 * @return unknown_type
 */
function _yamm_profile_back_form_submit($form, &$form_state) {
  $form_state['redirect'] = 'admin/yamm/profile';
}

/**
 * Edit entity settings for known types for profile form
 */
function yamm_server_profile_settings($form_state, $profile) {
  $form = array('#tree' => TRUE);

  $types = array();

  foreach (Yamm_EntityFactory::getSupportedTypes() as $type => $desc) {
    try {
      $settings = $profile->getSettingsForType($type);

      if (! $settings instanceof Yamm_EntitySettingsAbstract) {
        $settings = Yamm_EntityFactory::getEntitySettingsInstance($type);
      }

      $form[$type] = array(
        '#type'        => 'fieldset',
        '#collapsible' => FALSE,
        '#title'       => $desc['name'],
      );

      $form[$type] += $settings->form();

      $types[] = $type;
    }
    catch (Yamm_Entity_ClassNotFoundException $e) {
      // Silent error, it means our Yamm_Entity does not implement the
      // Yamm_EntitySettingsAbstract class
    }
  }

  $form['profile'] = array(
    '#type'  => 'value',
    '#value' => $profile->getInternalName());

  $form['types'] = array(
    '#type'  => 'value',
    '#value' => $types);

  $form['submit'] = array(
    '#type'  => 'submit',
    '#value' => t('Submit'));

  return $form;
}

function yamm_server_profile_settings_validate($form, &$form_state) {
  yamm_server_bootstrap_sync();

  foreach ($form_state['values']['types'] as $type) {
    $errors = Yamm_EntityFactory::getEntitySettingsInstance($type)->formValidate($form_state['values'][$type]);

    if (! empty($errors)) {
      foreach ($errors as $field => $error) {
        form_set_error($type . '][' . $field, $error);
      }
    }
  }
}

function yamm_server_profile_settings_submit($form, &$form_state) {
  yamm_server_bootstrap_sync();

  $profile = SyncProfile::load($form_state['values']['profile']);

  foreach ($form_state['values']['types'] as $type) {
    $settings = Yamm_EntityFactory::getEntitySettingsInstance($type);
    $settings->formSubmit($form_state['values'][$type]);

    $profile->addSettings($settings);
  }

  $profile->save();
}

/**
 * Multistep handling for client list
 */
function yamm_server_client_list($form_state) {
  if (! empty($form_state) && isset($form_state['op']) && ! empty($form_state['clients'])) {
    // Multistep
    return _yamm_server_client_list_confirm($form_state);
  }
  else {
    return _yamm_server_client_list_page($node);
  }
}

/**
 * Multistep confirm callback for client list
 */
function _yamm_server_client_list_confirm($form_state) {
  $form = array();

  $form['clients'] = array(
    '#type'  => 'value',
    '#value' => serialize($form_state['clients']));

  switch ($form_state['op']) {
    case 'remove':
      $question    = t('Are you sure you want to delete these clients?');
      $description = t('They will be definitely removed. You won\'t be able to restore them.');
      $submit      = '_yamm_server_client_list_remove_submit';
      break;

    case 'suspend':
      $question    = t('Are you sure you want to suspend these clients?');
      $description = t('These clients won\'t be synchronized anymore. You will be able to activate them back whenever you want.');
      $submit      = '_yamm_server_client_list_suspend_submit';
      break;

    case 'activate':
      $question    = t('Are you sure you want to activate these clients?');
      $description = t('These clients will be synchronized. You will be able to suspend them whenever you want.');
      $submit      = '_yamm_server_client_list_activate_submit';
      break;
  }

  $form = confirm_form($form, $question, ($form_state['redirect'] ? $form_state['redirect'] : $_GET['q']), $description);
  $form['#submit'][] = $submit;

  return $form;
}

function _yamm_server_build_in_statement($clients) {
  $args = array();
  $in = array();

  foreach ($clients as $name) {
    $in[]   = "'%s'";
    $args[] = $name;
  }

  $where[] = "name IN (" . implode(",", $in) . ")";

  return array(implode(" AND ", $where), $args);
}

function _yamm_server_client_list_remove_submit($form, &$form_state) {
  $clients = unserialize($form_state['values']['clients']);
  list($where, $args) = _yamm_server_build_in_statement($clients);
  db_query("DELETE FROM {yamm_server_client} WHERE " . $where, $args);
}

function _yamm_server_client_list_suspend_submit($form, &$form_state) {
  $clients = unserialize($form_state['values']['clients']);
  list($where, $args) = _yamm_server_build_in_statement($clients);
  array_unshift($args, YAMM_STATUS_SUSPEND);
  db_query("UPDATE {yamm_server_client} SET status = %d WHERE " . $where, $args);
}

function _yamm_server_client_list_activate_submit($form, &$form_state) {
  $clients = unserialize($form_state['values']['clients']);
  list($where, $args) = _yamm_server_build_in_statement($clients);
  array_unshift($args, YAMM_STATUS_ACTIVATE);
  db_query("UPDATE {yamm_server_client} SET status = %d WHERE " . $where, $args);
}

/**
 * Client list page
 */
function _yamm_server_client_list_page() {
  $form = array();
  $form['#tree'] = TRUE;

  $form['nid'] = array(
    '#type' => 'value',
    '#value' => $node->nid,
  );

  $form['options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Operations'),
    '#collapsible' => FALSE,
  );
  $form['options']['op'] = array(
    '#type' => 'select',
    '#options' => array(
      'add' => t('Add new item'),
      'remove' => t('Remove selected'),
      'suspend' => t('Suspend selected'),
      'activate' => t('Activate selected'),
    ),
    '#default_value' => 'add',
  );
  $form['options']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update'),
    '#submit' => array('yamm_server_client_list_update'),
  );

  $form['clients'] = array();

  $headers = array(
    array(
      'field' => 'name',
      'data' => t('Name'),
    ),
    array('data' => t('Url')),
    array(
      'field' => 'username',
      'data' => t('Username'),
    ),
    array(
      'field' => 'status',
      'data' => t('Status'),
    ),
    array('data' => t('Operations'))
  );

  $element = 0;
  $ts = tablesort_init($headers);
  $clients = yamm_server_get_clients(10, $element, $ts['sql'], $ts['sort']);

  array_unshift($headers, theme('table_select_header_cell'));
  $form['clients']['header'] = array('#type' => 'value', '#value' => &$headers);

  $checkclients = array();

  foreach ($clients as &$client) {
    $checkclients[$client->name] = '';
    $form['clients']['name'][$client->name] = array('#value' => check_plain($client->name));
    $form['clients']['url'][$client->name] = array('#value' => check_plain($client->url));
    $form['clients']['username'][$client->name] = array('#value' => check_plain($client->username));
    $form['clients']['status'][$client->name] = array('#value' => theme('yamm_server_status', $client->status));
    $form['clients']['operations'][$client->name] = array('#value' => theme('yamm_server_client_operations', $client, $_GET['q']));
  }

  $form['clients']['checkboxes'] = array('#type' => 'checkboxes', '#options' => isset($checkclients) ? $checkclients : array());
  $form['#theme'] = 'yamm_server_client_list';
  $form['footer'] = array('#type' => 'markup', '#value' => theme('pager', $element));

  return $form;
}

/**
 * Submit handler client list page
 */
function yamm_server_client_list_update($form, &$form_state) {
  $op = $form_state['values']['options']['op'];

  switch ($op) {
    case 'add':
      $path = 'admin/yamm/client/add';
      $query = array('destination' => $_GET['q']);
      drupal_goto($path, $query);
      break;

    case 'remove':
    case 'suspend':
    case 'activate':
      // Multistep handling
      $form_state['rebuild'] = TRUE;
      $form_state['op'] = $op;
      $form_state['clients'] = $form_state['values']['clients']['checkboxes'];
      break;

    default:
      drupal_set_message(t('Not implemented yet'));
      break;
  }
}

/**
 * Add client form
 */
function yamm_server_client_edit_form($form_state, $client = NULL) {
  $form = array();

  if (! $client) {
    $client = new stdClass();
    $update = FALSE;
  }
  else {
    drupal_set_title('Edit client');
    $update = TRUE;
  }

  $form['update'] = array('#type' => 'value', '#value' => $update);

  if ($update) {
    $form['display_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Internal name'),
      '#default_value' => $client->name,
      '#disabled' => $update,
    );
    $form['name'] = array('#type' => 'value', '#value' => $client->name);
  }
  else {
    $form['name'] = array(
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Internal name'),
      '#description' => t('Only letters, numbers and _ or - are allowed.'),
      '#default_value' => $client->name,
    );
  }

  $form['url'] = array(
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Client base url'),
    '#default_value' => $client->url,
  );

  $form['credentials']['username'] = array(
    '#type' => 'textfield',
    '#title' => t('Username'),
    '#description' => t('Maintenance username for remote client. It must have the "launch sync jobs" permission.'),
    '#default_value' => $client->username,
  );
  $form['credentials']['pass'] = array(
    '#type' => 'password',
    '#title' => t('Password'),
    '#description' => t('You have to type here the remote user password. Leave empty if you do not want to modify it.'),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => $update ? t('Update') : t('Save'),
  );

  return $form;
}

function yamm_server_client_edit_form_validate($form, &$form_state) {
  if (! preg_match('/^[a-zA-Z0-9\-_]+$/', $form_state['values']['name'])) {
    form_set_error('name', t('Invalid name'));
  }

  $exists = yamm_server_client_exists($form_state['values']['name']);
  $update = $form_state['values']['update'];

  if ($update) {
    if (! $exists) {
      form_set_error('name', t('Client does not exists'));
    }
  }

  else {
    if ($exists) {
      form_set_error('name', t('Client name already taken'));
    }
    if (empty($form_state['values']['pass'])) {
      form_set_error('pass', t('You must set a password'));
    }
  }

  if (empty($form_state['values']['username'])) {
    form_set_error('username', t('You must set credentials'));
  }
}

function yamm_server_client_edit_form_submit($form, &$form_state) {
  $update = $form_state['values']['update'];
  $pass = $form_state['values']['pass'];
  $name = $form_state['values']['name'];

  if ($update && empty($pass)) {
    $client = yamm_server_client_load($name);
    $pass = $client->pass;
  }
  else {
    $pass = md5($pass);
  }

  $client = array(
    'name' => $name,
    'url' => $form_state['values']['url'],
    'username' => $form_state['values']['username'],
    'pass' => $pass,
  );

  yamm_server_client_save($client);
}


/**
 * Client hello test page
 */
function yamm_server_hello_page($client) {
  $output = '';

  module_load_include('inc', 'yamm_server', 'yamm_server.network');

  if (yamm_server_network_hello($client)) {
    drupal_set_message(t('Communication OK'));
    $output .= t('Server is up');
  }
  else {
    drupal_set_message(t('Unable to reach client'), 'error');
    $output .= t('Server seems to be down');
  }

  $output .= theme('yamm_server_page_back');

  return $output;
}

/**
 * Client overview page
 */
function yamm_server_client_overview($client) {
  $output = '';

  drupal_set_title(t('@name overview', array('@name' => check_plain($client->name))));

  $output .= drupal_get_form('yamm_server_client_operations_form', $client);
  $output .= theme('yamm_server_client_overwiew', $client);
  $output .= theme('yamm_server_page_back');

  return $output;
}

/**
 * Client operation form
 */
function yamm_server_client_operations_form($form_state, $client) {
  $form = array();

  $form['client'] = array('#type' => 'value', '#value' => check_plain($client->name));

  $form['operations'] = array(
    '#type' => 'fieldset',
    '#title' => t('Push method'),
    '#collapsible' => FALSE,
  );

  $methods = yamm_api_push_methods();

  $form['operations']['oper'] = array(
    '#type' => 'select',
    '#options' => $methods,
    '#default_value' => array_shift(array_keys(yamm_api_push_methods())),
  );

  $form['operations']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Push'),
  );

  $form['#theme'] = 'yamm_server_client_operations_form';

  return $form;
}

function yamm_server_client_operations_form_submit($form, &$form_state) {
  $client = yamm_server_client_load($form_state['values']['client']);
  $op = $form_state['values']['oper'];

  if (yamm_api_push_method_exists($op)) {
    module_load_include('inc', 'yamm_server', 'yamm_server.network');

    if ($status = yamm_server_network_push($client, $op, TRUE)) {
      switch ($status) {
        case YAMM_TRANSACTION_STATUS_WAITING:
          drupal_set_message(t('New transaction created, waiting for client to finish.'));
          break;
        case YAMM_TRANSACTION_STATUS_CANCELED:
          drupal_set_message(t('Client canceled transaction'), 'error');
          break;
        default:
          drupal_set_message(t('Unknown status returned by client (@id: @status)', array('@id' => $status, '@status' => yamm_api_get_status_localized_string($status))), 'error');
      }
    }
    else {
      drupal_set_message(t('Client not responding'), 'error');
    }
  }
  else {
    drupal_set_message(t('Unknown method, push not send'), 'error');
  }
}
