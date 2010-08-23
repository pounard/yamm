<?php
// $Id: yamm_server.drush.inc,v 1.3 2009/11/05 20:19:36 pounard Exp $

/**
 * @file
 * Yamm server Drush integration
 */

/**
 * Implementation of hook_drush_command().
 */
function yamm_server_drush_command() {
  $items = array();

  // Client handling stuff
  $items['yamm server client exists'] = array(
    'callback' => 'yamm_server_drush_client_exists',
    'description' => 'Tells if Yamm client exists',
    'arguments' => array(
      'client' => 'client name')
  );
  $items['yamm server client list'] = array(
    'callback' => 'yamm_server_drush_client_list',
    'description' => 'List all clients'
  );
  $items['yamm server client remove'] = array(
    'callback' => 'yamm_server_drush_client_remove',
    'description' => 'Remove a client',
    'arguments' => array(
      'client' => 'client name')
  );
  $items['yamm server client push'] = array(
    'callback' => 'yamm_server_drush_client_push',
    'description' => 'Push synchronization order to client',
    'arguments' => array(
      'client' => 'client name')
  );
  $items['yamm server client suspend'] = array(
    'callback' => 'yamm_server_drush_client_suspend',
    'description' => 'Suspend client(s)',
    'arguments' => array(
      'clients' => 'space delimited client names')
  );
  $items['yamm server client activate'] = array(
    'callback' => 'yamm_server_drush_client_activate',
    'description' => 'Activate client(s)',
    'arguments' => array(
      'clients' => 'space delimited client names')
  );
  $items['yamm server client add'] = array(
    'callback' => 'yamm_server_drush_client_add',
    'description' => 'Add or update client',
    'arguments' => array(
      'name' => 'client name',
      'url' => 'client base url',
      'username' => 'admin username',
      'pass' => 'admin password')
  );

  // Transaction handling stuff
  $items['yamm server transaction list'] = array(
    'callback' => 'yamm_server_drush_transaction_list',
    'description' => 'List all transactions (both client and server)'
  );
  $items['yamm server transaction remove'] = array(
    'callback' => 'yamm_server_drush_transaction_remove',
    'description' => 'Remove transaction)',
    'arguments' => array(
      'transactions' => 'space delimited list of transaction identifiers')
  );
  $items['yamm server transaction purge'] = array(
    'callback' => 'yamm_server_drush_transaction_purge',
    'description' => 'Purge all transaction'
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function yamm_server_drush_help($section) {
  switch ($section) {
    // Client handling stuff
    case 'drush:yamm server client exists':
      return dt("Returns 1 if client exists, 0 else");
    case 'drush:yamm server client list':
      return dt("List all known clients and status");
    case 'drush:yamm server client remove':
      return dt("Remove a client from database");
    case 'drush:yamm server client push':
      return dt("Push synchronization order to client");
    case 'drush:yamm server client suspend':
      return dt("Suspend synchronization scheduling for all given clients");
    case 'drush:yamm server client activate':
      return dt("Activate synchronization scheduling for all given clients");
    case 'drush:yamm server client add':
      return dt("Add new client to database");
    // Transaction handling stuff
  }
}

/**
 * Check Yamm server is a valid installation
 *
 * @return boolean
 */
function _yamm_server_drush_check_install() {
  $success = TRUE;

  $success = module_exists('yamm_server');

  if (! $success) {
    drush_die('the yamm_server module does not seems to be present or activated.');
  }
}

/**
 * Check for valid client name
 *
 * @param string $name
 * @return boolean
 */
function _yamm_server_drush_validate_client_name($name) {
  return preg_match('/^[a-zA-Z0-9\-_]+$/', $name);
}

function yamm_server_drush_client_exists($name) {
  _yamm_server_drush_check_install();

  if (! _yamm_server_drush_validate_client_name($name)) {
    drush_set_error('ERROR', dt("name '@name' is invalid", array('@name' => $name)));
  }
  else if (! $client = yamm_server_client_load($name)) {
    drush_print("0");
  }
  else {
    drush_print("1");
  }
}

function yamm_server_drush_client_list() {
  _yamm_server_drush_check_install();

  $clients = yamm_server_get_clients();

  if (empty($clients)) {
    drush_print(dt('no clients configured', array('@name' => $name)));
  }

  else {
    $rows = array();
    $rows[] = array(dt('name'), dt('url'), dt('status'));

    foreach ($clients as &$client) {
      switch ($client->status) {
        case YAMM_STATUS_ACTIVATE:
          $status = dt('activated');
          break;
        case YAMM_STATUS_SUSPEND:
          $status = dt('suspended');
          break;
        default:
          $status = dt('unknown');
      }

      $rows[] = array($client->name, $client->url, $status);
    }

    drush_print_table($rows, TRUE);
  }
}

function yamm_server_drush_client_remove($name) {
  _yamm_server_drush_check_install();

  if (! _yamm_server_drush_validate_client_name($name)) {
    drush_set_error('ERROR', dt("name '@name' is invalid", array('@name' => $name)));
  }
  else if (! $client = yamm_server_client_load($name)) {
    drush_set_error('ERROR', dt("client '@name' does not exists", array('@name' => $name)));
  }
  else {
    yamm_server_client_remove($client);
    drush_print(dt("client '@name' removed", array('@name' => $name)));
  }
}

function yamm_server_drush_client_push($name) {
  _yamm_server_drush_check_install();

  if (! _yamm_server_drush_validate_client_name($name)) {
    drush_set_error('ERROR', dt("name '@name' is invalid", array('@name' => $name)));
  }
  else if (! $client = yamm_server_client_load($name)) {
    drush_set_error('ERROR', dt("client '@name' does not exists", array('@name' => $name)));
  }
  else {
    module_load_include('inc', 'yamm_server', 'yamm_server.network');

    if ($status = yamm_server_network_push($client, 'yamm_sync', TRUE)) {
      switch ($status) {
        case YAMM_TRANSACTION_STATUS_WAITING:
          drush_print(dt("client '@name' accepted transaction", array('@name' => $name)));
          break;
        case YAMM_TRANSACTION_STATUS_CANCELED:
          drush_set_error('ERROR', dt("client '@name' aborted transaction with status '@errno'", array('@name' => $name, '@errno' => $status)));
          break;
        default:
          drush_set_error('ERROR', dt("client '@name' aborted with unknown status '@errno'", array('@name' => $name, '@errno' => $status)));
      }
    }
    else {
      drush_set_error('ERROR', dt('xmlrpc connection failed'));
    }
  }
}

function yamm_server_drush_client_suspend() {
  _yamm_server_drush_check_install();
  $command = drush_get_command();
  return _yamm_server_drush_client_change_status($command['arguments'], YAMM_STATUS_SUSPEND);
}

function yamm_server_drush_client_activate() {
  _yamm_server_drush_check_install();
  $command = drush_get_command();
  return _yamm_server_drush_client_change_status($command['arguments'], YAMM_STATUS_ACTIVATE);
}

function _yamm_server_drush_client_change_status($names, $status) {
  foreach ($names as $name) {
    $client = NULL;

    if ($status == YAMM_STATUS_ACTIVATE) {
      $status_str = dt('activated');
    }
    else if ($status == YAMM_STATUS_SUSPEND) {
      $status_str = dt('suspended');
    }

    if (! _yamm_server_drush_validate_client_name($name)) {
      drush_set_error('ERROR', dt("name '@name' is invalid", array('@name' => $name)));
    }
    else if (! $client = yamm_server_client_load($name)) {
      drush_set_error('ERROR', dt("client '@name' does not exists", array('@name' => $name)));
    }
    else if ($client->status != $status) {
      $client->status = $status;
      yamm_server_client_save($client);
      drush_print(dt("client '@name' @status", array('@name' => $name, '@status' => $status_str)));
    }
    else {
      drush_print(dt("client '@name' unchanged", array('@name' => $name)));
    }
  }
}

function yamm_server_drush_client_add($name, $url, $username, $pass) {
  _yamm_server_drush_check_install();

  $validate = TRUE;

  // Check parameters
  if (! _yamm_server_drush_validate_client_name($name)) {
    drush_set_error('ERROR', dt("name '@name' is invalid", array('@name' => $name)));
    $validate = FALSE;
  }
  $url = preg_replace('/^http:\/\/(.*)$/', '$1', $url);
  if (! drupal_valid_http_host($url)) {
    drush_set_error('ERROR', dt("url '!url' is invalid", array('!url' => $url)));
    $validate = FALSE;
  }
  if (empty($username)) {
    drush_set_error('ERROR', dt('user cannot be empty'));
    $validate = FALSE;
  }
  if (empty($pass)) {
    drush_set_error('ERROR', dt('pass cannot be empty'));
    $validate = FALSE;
  }

  if (! $validate) {
    drush_set_error('ERROR', dt('validation failed, creation aborted'));
    return;
  }

  if ($existing = yamm_server_client_load($name)) {
    if (! drush_confirm(dt("Client '@name' exists, do you want to update it?", array('@name' => $name)))) {
      drush_print(dt('user abort'));
      return;
    }
  }

  // Do our job here
  $client = array('name' => $name, 'url' => $url, 'username' => $username, 'pass' => md5($pass), 'status' => ($existing ? $existing->status : YAMM_STATUS_SUSPEND));
  yamm_server_client_save($client);
  drush_print(dt("client '@name create' (status set to suspended)", array('@name' => $name)));
}

function yamm_server_drush_transaction_list() {
  _yamm_server_drush_check_install();

  $transactions = array();

  $result = db_query("SELECT tid FROM {yamm_server_transaction}");
  while ($data = db_fetch_object($result)) {
    $transactions[$data->tid] = yamm_server_transaction_load($data->tid);
  }

  if (empty($transactions)) {
    drush_print(dt('no transactions in database'));
  }

  else {
    $rows = array();
    $rows[] = array(dt('tid'), dt('client'), dt('profile'), dt('status'), dt('update'));

    foreach ($transactions as $tid => &$transaction) {
      $rows[] = array(
        $tid,
        $transaction->client,
        $transaction->profile,
        yamm_api_get_status_localized_string($transaction->status),
        $transaction->updated ? date('d/m/Y H:i', $transaction->updated) : dt('N/A'));
    }

    drush_print_table($rows, TRUE);
  }
}

function yamm_server_drush_transaction_remove() {
  drush_print(dt('Not implemented yet.'));
}

function yamm_server_drush_transaction_purge() {
  drush_print(dt('Not implemented yet.'));
}
