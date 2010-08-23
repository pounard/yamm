<?php
// $Id: yamm_client.admin.inc,v 1.4 2010/03/25 23:53:11 pounard Exp $

/**
 * @file
 * Yamm client settings forms.
 */

function yamm_client_settings($form_state) {
  $form = array();

  $form['server'] = array(
    '#type' => 'fieldset',
    '#title' => t('Server'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE);

  $form['server'][YAMM_CLIENT_ALLOWED_SERVERS] = array(
    '#type' => 'textarea',
    '#default_value' => variable_get(YAMM_CLIENT_ALLOWED_SERVERS, NULL),
    '#title' => t('Allowed servers'),
    '#description' => t('Enter one URL per line. URLs must be the master website access URL. This is allow client to respond with XML/RPC calls to master Drupal instance.'));

  $form['transactions'] = array(
    '#type' => 'fieldset',
    '#title' => t('Transactions'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE);

  $form['transactions'][YAMM_CLIENT_TRANSACTION_LIFETIME] = array(
    '#type' => 'select',
    '#title' => t('Transaction life time'),
    '#options' => array(
      0     => t('None'),
      3600  => t('1 hour'),
      21600 => t('6 hours'),
      21600 => t('12 hours'),
      86400 => t('1 day')),
    '#default_value' => variable_get(YAMM_CLIENT_TRANSACTION_LIFETIME, 0),
    '#description' => t("At each cron run, transaction older than this will be set as 'timeouted' and corresponding DataSync jobs will be set as failed."));

  return system_settings_form($form);
}
