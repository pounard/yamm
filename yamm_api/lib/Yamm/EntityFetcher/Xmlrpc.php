<?php
// $Id

/**
 * XML/RPC custom fetcher.
 */
class Yamm_EntityFetcher_Xmlrpc extends Yamm_EntityFetcher
{
  /**
   * Transaction identifier.
   * 
   * @var string
   */
  protected $_transactionId = NULL;

  /**
   * Main constructor.
   *
   * @param string $serverUrl
   *   Master URL.
   * @param string $tid
   *   Transaction identifier.
   */
  public function __construct($tid) {
    $this->_transactionId = $tid;
  }

  /**
   * Get entities from a xmlrpc method call.
   *
   * @param string $method
   *   Method to call.
   * @param ..
   *   Params to send to server.
   * 
   * @return mixed
   *   Method result, or FALSE in case of network error.
   */
  private function &__getEntities($method) {
    $ret = array();

    if (! $this->_server) {
      throw new Yamm_EntityFetcherException("No server configured");
    }

    $args = func_get_args();
    $method = array_shift($args);
    array_unshift($args, $this->_transactionId);
    array_unshift($args, $method);
    array_unshift($args, $this->_server->getUrl());

    $result = call_user_func_array('yamm_api_xmlrpc_call', $args);

    if ($result === FALSE) {
      throw new Yamm_EntityFetcherException("Unable to reach server");
    }

    // Result can be empty (no dependencies)
    if (! empty($result['data'])) {
      foreach ($result['data'] as $serializedEntity) {
        $ret[] = Yamm_Entity::unserialize($serializedEntity);
      }
    }

    unset($result);
    return $ret;
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntityFetcher::_pull()
   */
  protected function _pull() {
    return $this->__getEntities('yamm.client.pull');
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntityFetcher::_fetchDependencies()
   */
  protected function _fetchDependencies(array &$uuid_array) {
    return $this->__getEntities('yamm.client.pull.dependencies', $uuid_array);
  }
}
