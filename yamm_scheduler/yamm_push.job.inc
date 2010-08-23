<?php
// $Id: yamm_push.job.inc,v 1.3 2009/11/05 20:19:36 pounard Exp $

/**
 * @file
 * Yamm mass push job implementation
 */

/**
 * Custom DataSync job implementation
 */
class yamm_push extends datasync_job {

  public function phase_defs() {
    return array('pushAllClients');
  }

  public function phase_proceed() {
    return TRUE;
  }

  public function postpone_phase() {
  }

  public function fail_phase($method = NULL) {
    return parent::fail_phase($method);
  }

  public function config_defs() {
    $config = parent::config_defs();
    $config['phase_in_transaction'] = array();
    return $config;
  }

  /**
   * One and only phase, content synchronisation
   */
  public function pushAllClients() {
    module_load_include('inc', 'yamm_server', 'yamm_server.network');

    $result = db_query("SELECT c.name FROM {yamm_server_client} c WHERE c.status = %d", YAMM_STATUS_ACTIVATE);

    while ($data = db_fetch_object($result)) {
      $client = yamm_server_client_load($data->name);

      if ($status = yamm_server_network_push($client, 'yamm_sync')) {
        switch ($status) {
          case YAMM_TRANSACTION_STATUS_WAITING:
            watchdog('yamm_push_job', t('New transaction created for \'@name\', waiting for client to finish.'), array('@name' => check_plain($client->name)), WATCHDOG_NOTICE);
            break;
          case YAMM_TRANSACTION_STATUS_CANCELED:
            watchdog('yamm_push_job', t('Client \'@name\' cancelled transaction'), array('@name' => check_plain($client->name)), WATCHDOG_ERROR);
            break;
          default:
            watchdog('yamm_push_job', t('Unknown status returned by client \'@name\''), array('@name' => check_plain($client->name)), WATCHDOG_ERROR);
        }
      }
    }

    return TRUE;
  }
}
