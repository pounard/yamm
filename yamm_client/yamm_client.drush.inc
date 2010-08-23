<?php
// $Id: yamm_client.drush.inc,v 1.3 2009/11/05 20:19:36 pounard Exp $

/**
 * @file
 * Yamm client Drush integration
 */

/**
 * Implementation of hook_drush_command().
 */
function yamm_client_drush_command() {
  $items = array();

  // Transaction handling stuff
  $items['yamm client transaction list'] = array(
    'callback' => 'yamm_client_drush_transaction_list',
    'description' => 'List all transactions'
  );
  $items['yamm client transaction remove'] = array(
    'callback' => 'yamm_client_drush_transaction_remove',
    'description' => 'Remove transaction)',
    'arguments' => array(
      'transactions' => 'space delimited list of transaction identifiers')
  );
  $items['yamm client transaction purge'] = array(
    'callback' => 'yamm_client_drush_transaction_purge',
    'description' => 'Purge all transaction'
  );
  $items['yamm client server'] = array(
    'callback' => 'yamm_client_drush_server',
    'description' => 'Display/update master information',
    'arguments' => array(
      'op' => "operation to do with, can be 'set'",
      'url' => "if operation is 'set', this parameter must be the url")
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function yamm_client_drush_help($section) {
  switch ($section) {
    /*
    // Client handling stuff
    case 'drush:yamm client exists':
      return dt("Returns 1 if client exists, 0 else");
    case 'drush:yamm client list':
      return dt("List all known clients and status");
    case 'drush:yamm client remove':
      return dt("Remove a client from database");
    case 'drush:yamm client push':
      return dt("Push synchronization order to client");
    case 'drush:yamm client suspend':
      return dt("Suspend synchronization scheduling for all given clients");
    case 'drush:yamm client activate':
      return dt("Activate synchronization scheduling for all given clients");
    case 'drush:yamm client add':
      return dt("Add new client to database");
    // Transaction handling stuff
     */
  }
}

/**
 * Check Yamm server is a valid installation
 *
 * @return boolean
 */
function _yamm_client_drush_check_install() {
  $success = TRUE;

  $success = module_exists('yamm_client');

  if (! $success) {
    drush_die('the yamm_client module does not seems to be present or activated.');
  }
}

function yamm_client_drush_transaction_list() {
  _yamm_client_drush_check_install();

  $transactions = array();

  $result = db_query("SELECT tid FROM {yamm_client_transaction}");
  while ($data = db_fetch_object($result)) {
    $transactions[$data->tid] = yamm_client_transaction_load($data->tid);
  }

  if (empty($transactions)) {
    drush_print(dt('no transactions in database'));
  }

  else {
    $rows = array();
    $rows[] = array(dt('tid'), dt('status'), dt('update'));

    foreach ($transactions as $tid => &$transaction) {
      $rows[] = array(
        $tid,
        yamm_api_get_status_localized_string($transaction->status),
        $transaction->updated ? date('d/m/Y H:i', $transaction->updated) : dt('N/A'));
    }

    drush_print_table($rows, TRUE);
  }
}

function yamm_client_drush_transaction_remove() {
  drush_print(dt('Not implemented yet.'));
}

function yamm_client_drush_transaction_purge() {
  drush_print(dt('Not implemented yet.'));
}

function yamm_client_drush_server($op = 'get', $url = NULL) {
  switch ($op) {
    case 'set':
      if (! $url) {
        drush_set_error(dt('invalid or null url'));
        return;
      }

      variable_set(YAMM_CLIENT_SERVER_URL, $url);
      break;

    case 'get':
    default:
      $server = variable_get(YAMM_CLIENT_SERVER_URL, '__DRUSH_YAMM_SERVER_NOT_SET');

      if ($server == '__DRUSH_YAMM_SERVER_NOT_SET') {
        drush_set_error(dt('yamm server not set'));
        return;
      }

      drush_print($server);
  }
}
