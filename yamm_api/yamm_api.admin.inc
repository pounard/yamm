<?php
// $Id: yamm_api.admin.inc,v 1.2 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Yamm api settings forms.
 */

function yamm_api_settings($form_state) {
  $form = array();

  $datasync_enabled = module_exists('datasync');

  if (! $datasync_enabled) {
    drupal_set_message(t('DataSync is disabled, its options are not available'), 'error');
  }

  $form['clean'] = array(
    '#type' => 'fieldset',
    '#title' => t('Cleaning tasks (advanced users)'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE);

  $form['clean']['text_rebuild'] = array(
    '#type' => 'markup',
    '#value' => '<p>' . t('Rebuild option will try to fix DataSync module configuration to make it work without any further configuration. Note that this might not work on all environments. Clean state option will clean up the current state, and delete all current running DataSync Yamm jobs') . '</p>');

  /*
  $form['clean']['datasync_rebuild_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Rebuild DataSync configuration'),
    '#submit' => array('yamm_api_rebuild_datasync_submit'),
    '#disabled' => ! $datasync_enabled);
   */

  $form['clean']['clean_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Clean state'),
    '#submit' => array('yamm_api_settings_clean_submit'));

  $form['misc'] = array(
    '#type' => 'fieldset',
    '#title' => t('Yammm global options'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE);

  $form['misc'][YAMM_OPT_DEBUG] = array(
    '#type' => 'checkbox',
    '#default_value' => variable_get(YAMM_OPT_DEBUG, NULL),
    '#title' => t('Debug mode'),
    '#description' => t("Check this and you will get A LOT of debug messages in watchdog entries"));

  $form = system_settings_form($form);

  return $form;
}

function yamm_api_settings_clean_submit($form, &$form_state) {
  yamm_api_clean();
  drupal_set_message(t('Clean operation ran successfully'));
}

function yamm_api_rebuild_datasync_submit($form, &$form_state) {
  yamm_api_rebuild_datasync(array('yamm_sync'));
  drupal_set_message(t('DataSync configuration rebuilt'));
}
