<?php
// $Id: yamm_server.network.inc,v 1.5 2010/05/12 16:20:39 pounard Exp $

/**
 * @file
 * Pull/push over xmlrpc implementation.
 */

/**
 * Wrapper method for xmlrpc() to reformat url and include our user/pass.
 *
 * @param object $client
 * @param string $method
 * @param ...
 *   Other parameters.
 * 
 * @return mixed
 *   Array (xmlrpc results) in case of success, FALSE else.
 */
function _yamm_server_client_xmlrpc($client, $method) {
  $args = func_get_args();

  // Shift this method args.
  $client = array_shift($args);
  $method = array_shift($args);

  // Unshift parameters in the right order, starting with user/pass.
  array_unshift($args, check_plain($client->pass));
  array_unshift($args, check_plain($client->username));
  array_unshift($args, $method);
  array_unshift($args, $client->url);

  return call_user_func_array('yamm_api_xmlrpc_call', $args);
}

/**
 * Test if remote client responds.
 *
 * @param object $client
 * 
 * @return boolean
 *   TRUE if remote client responds.
 */
function yamm_server_network_hello($client) {
  return _yamm_server_client_xmlrpc($client, 'yamm.client.authtest');
}

/**
 * Push command to client, to force it pulling the given command.
 * Therefore, it creates a new transaction and push the tid to client.
 *
 * We are doing some ASYNC work, we will just update transaction status
 * to 'waiting' status. In case we already have some transaction with
 * client, we are going to cancel this one.
 *
 * @param object $client
 * @param string $method
 * @param boolean $manual = FALSE
 *   If set to TRUE, transaction will be set to manual mode. This means client
 *   will be able to pull even if its state is suspended.
 * 
 * @return boolean
 *   Transaction status, FALSE in case connection error.
 */
function yamm_server_network_push($client, $method, $manual = FALSE) {
  global $base_url;

  yamm_server_bootstrap_sync();

  $tid = yamm_server_transaction_create($client, SyncProfile::getDefaultProfile(), YAMM_TRANSACTION_STATUS_WAITING, $manual);
  $result = _yamm_server_client_xmlrpc($client, 'yamm.push', $base_url, $tid, $method);

  if ($result === FALSE) {
    $status = YAMM_TRANSACTION_STATUS_HOSTDOWN;
  }
  else {
    $result = $result['data'];
    $status = isset($result['status']) ? $result['status'] : YAMM_TRANSACTION_STATUS_OUTDATED; 
  }

  if ($result['last_update']) {
    yamm_server_transaction_update_status($tid, $status, $result['last_update']);
  }
  else {
    yamm_server_transaction_update_status($tid, $status);
  }

  return $status;
}
