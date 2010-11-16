<?php
// $Id: yamm_server.admin.inc,v 1.4 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Yamm server admin pages and forms.
 */

/**
 * Profile overview page.
 */
function yamm_server_profile_overview() {
  $form = array(
    '#tree' => TRUE,
    '#theme' => 'yamm_server_profile_overview',
  );

  $form['profiles'] = array();
  $element = 0;
  $factory = xoxo_factory_get('yamm_sync_profile');
  $profiles = $factory->loadAll(10, TRUE, $element);
  $radios = array();

  // Display all known profile
  foreach ($profiles as $profile) {
    $name = $profile->getName();
    $description = $profile->getDescription();
    $radios[$name] = '';
    $form['profiles']['profile'][$name] = array('#value' => $description ? $description : $name);
  }

  $form['profiles']['radios'] = array(
    '#type' => 'radios',
    '#options' => isset($radios) ? $radios : array(),
    '#default_value' => $factory->getDefaultProfile()
  );

  $form['footer'] = array('#type' => 'markup', '#value' => theme('pager', $element));

  // Change default button.
  $form['profile_change'] = array(
    '#type' => "submit",
    '#value' => t("Set selected as default"),
    '#submit' => array('yamm_server_profile_overview_change_default'),
  );
  // New profile link.
  $form['profile_add'] = array(
    '#type' => "markup",
    '#value' => l(t("Add a new profile"), 'admin/objects/yamm_sync_profile/add', array('query' => array('destination' => $_GET['q']))),
  );

  return $form;
}

/**
 * Submit handler profile overview.
 */
function yamm_server_profile_overview_change_default($form, &$form_state) {
  if (! empty($form_state['values']['profiles']['radios'])) {
    xoxo_factory_get('yamm_sync_profile')->setDefaultProfile($form_state['values']['profiles']['radios']);
    drupal_set_message(t('Default profile updated.'));
  }
}

/**
 * Handler to back to profile list.
 */
function _yamm_profile_back_form_submit($form, &$form_state) {
  $form_state['redirect'] = 'admin/yamm/profile';
}

function yamm_server_profile_settings_submit($form, &$form_state) {
  yamm_server_bootstrap_sync();

  $profile = Yamm_Sync_Profile::load($form_state['values']['profile']);

  foreach ($form_state['values']['types'] as $type) {
    $settings = Yamm_EntityFactory::getEntitySettingsInstance($type);
    $settings->formSubmit($form_state['values'][$type]);

    $profile->addSettings($settings);
  }

  $profile->save();
}

/**
 * Multistep handling for client list.
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
 * Multistep confirm callback for client list.
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
 * Client list page.
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
 * Submit handler client list page.
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
 * Add client form.
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
 * Client hello test page.
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
 * Client overview page.
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
 * Client operation form.
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


/**
 * Theme the operation form on client overview page.
 * 
 * @return string
 *   (x)html output
 */
function theme_yamm_server_client_operations_form($form) {
  $output .= '<div class="container-inline">' . drupal_render($form['operations']) . '</div>';
  $output .= drupal_render($form);
  return $output;
}

/**
 * Theme handler client list page
 * 
 * @param array $form
 * @return string
 *   (x)html output
 */
function theme_yamm_server_client_list($form) {
  $output .= '<div class="container-inline">' . drupal_render($form['options']) . '</div>';

  if (isset($form['clients']['name']) && is_array($form['clients']['name'])) {
    foreach (element_children($form['clients']['name']) as $key) {
      $row = array();
      $row[] = drupal_render($form['clients']['checkboxes'][$key]);
      $row[] = drupal_render($form['clients']['name'][$key]);
      $row[] = drupal_render($form['clients']['url'][$key]);
      $row[] = drupal_render($form['clients']['username'][$key]);
      $row[] = drupal_render($form['clients']['status'][$key]);
      $row[] = drupal_render($form['clients']['operations'][$key]);
      $rows[] = $row;
    }
  }
  else {
    $rows[] = array(array('data' => t('No clients available.'), 'colspan' => '5'));
  }

  $output .= theme('table', $form['clients']['header']['#value'], $rows);
  $output .= drupal_render($form);

  return $output;
}

/**
 * Theme handler client list page (client operations).
 * 
 * @param object $client
 * @param string $destination = NULL
 *   (optional) destination path where to return to.
 * 
 * @return string
 *   (x)html output.
 */
function theme_yamm_server_client_operations($client, $destination = NULL) {
  $links = array(
    array(
      'title' => t('Overview'),
      'href' => 'admin/yamm/client/' . $client->name . '/view',    
      /*'query' => array('destination' => $destination),*/
    ),
    array(
      'title' => t('Edit'),
      'href' => 'admin/yamm/client/' . $client->name .'/edit',
      'query' => array('destination' => $destination),
    ),
    array(
      'title' => t('Test'),
      'href' => 'admin/yamm/client/' . $client->name . '/hello',
      'query' => array('destination' => $destination,
    ),
  ));
  return theme('links', $links);
}

/**
 * Theme handler for profile overview page.
 * 
 * @param array $form
 *   Form being rendered.
 * 
 * @return string
 *   (x)html output.
 */
function theme_yamm_server_profile_overview($form) {
  $output .= '<div class="container-inline">' . drupal_render($form['options']) . '</div>';

  $headers = array(t('Default'), t('Description'), t("Operations"));
  $rows = array();

  if (isset($form['profiles']['profile']) && is_array($form['profiles']['profile'])) {
    foreach (element_children($form['profiles']['profile']) as $key) {
      $row = array();
      $row[] = drupal_render($form['profiles']['radios'][$key]);
      $row[] = drupal_render($form['profiles']['profile'][$key]);
      $row[] = theme('links', array(
        array(
          'title' => t("edit"),
          'href' => 'admin/objects/yamm_sync_all/' . $key . '/edit',
        ),
      ));
      $rows[] = $row;
    }
  }
  else {
    $rows[] = array(array('data' => t('No Profile available.'), 'colspan' => '5'));
  }

  $output .= theme('table', $headers, $rows);
  $output .= drupal_render($form);

  return $output;
}
