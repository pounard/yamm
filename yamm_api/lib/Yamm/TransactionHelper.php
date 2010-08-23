<?php
// $Id: TransactionHelper.php,v 1.1 2010/05/12 16:20:39 pounard Exp $

/**
 * Yamm client synchronization job implementation. This no longer relies on
 * DataSync module.
 */
class Yamm_TransactionHelper
{
  /**
   * @var Yamm_Server
   */
  protected $_server;

  /**
   * Transaction identifier with server.
   * 
   * @var Yamm_Transaction
   */
  protected $_tid;

  /**
   * Current transaction parser, if any.
   * 
   * @var Yamm_EntityParser
   */
  protected $_parser;

  /**
   * Default constructor.
   * 
   * @param Yamm_Server $server
   *   Server to pull.
   */
  public function __construct($tid, Yamm_Server $server) {
  	$this->_tid = $tid;
  	$this->_server = $server;
  }

  /**
   * Run the current transaction.
   * 
   * @return boolean
   *   TRUE when success, FALSE else.
   */
  public function run() {
    // Check we have a transaction in "running" state
    yamm_client_transaction_update_status($this->_yammTid, YAMM_TRANSACTION_STATUS_RUNNING);
    return $this->syncAllContent();
  }

  /**
   * Mark the transaction as canceled and advertise server.
   */
  public function cancel() {
    $this->sendStatus(YAMM_TRANSACTION_STATUS_CANCELED);
  }

  /**
   * Mark the job as failed and advertise server.
   * 
   * @param string $method = NULL
   */
  public function fail($method = NULL) {
    $this->sendStatus(YAMM_TRANSACTION_STATUS_CANCELED);
  }

  /**
   * Advertise new transaction status to server. This will save the new status
   * locally in client database.
   * 
   * @param int $status
   *   One of the YAMM_TRANSACTION_STATUS_* constants.
   */
  public function sendStatus($status) {
    // Close transaction on client
    yamm_client_transaction_update_status($this->_tid, $status);
    // Notify server in case we have its url
    $this->sendCall('yamm.client.status', $status);
  }

  /**
   * Do an xmlrpc call to server.
   *
   * @see yamm_api_xmlrpc_call()
   *
   * @param string $method
   *   Method to cal
   * @param ...
   *   Arbitrary parameters you want to send to.
   * 
   * @return mixed
   *   Same result as yamm_api_xmlrpc_call().
   */
  public function sendCall($method) {
    $args = func_get_args();
    $method = array_shift($args);
    array_unshift($args, $this->_tid);
    array_unshift($args, $method);
    array_unshift($args, $this->_server->getUrl());
    return call_user_func_array('yamm_api_xmlrpc_call', $args);
  }

  /**
   * One and only phase, content synchronisation.
   */
  public function syncAllContent() {
    yamm_api_debug("Transaction " . $this->_tid . " running");

    try {
      $this->_entityFetcher = new Yamm_EntityFetcher_Xmlrpc($this->_server->getUrl(), $this->_tid);
      $this->_entityParser = new Yamm_EntityParser($this->_entityFetcher);
      $this->_entityParser->parse();

      module_invoke_all('yamm_sync_finished');
      // FIXME: This might be to heavy.
      drupal_flush_all_caches();

      $this->sendStatus(YAMM_TRANSACTION_STATUS_FINISHED);
      return TRUE;
    }
    catch (Yamm_EntityFetcherException $e) {
      yamm_api_debug($e->getMessage());
      yamm_api_debug($e->getTraceAsString());
      // If we dont change status here, no other transanctions this client wont
      // be able to run any more transaction.
      $this->cancel();
    }
    catch (Yamm_EntityException $e) {
      yamm_api_debug($e->getMessage());
      yamm_api_debug($e->getTraceAsString());
      // If we dont change status here, no other transanctions this client wont
      // be able to run any more transaction.
      $this->fail();
    }

    return FALSE;
  }
}
