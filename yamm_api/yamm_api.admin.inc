<?php
// $Id: yamm_api.admin.inc,v 1.2 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Yamm api settings forms.
 */

function yamm_api_settings($form_state) {
  $form = array();

  $form['clean'] = array(
    '#type' => 'fieldset',
    '#title' => t('Cleaning tasks (advanced users)'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE);

  $form['clean']['text_rebuild'] = array(
    '#type' => 'markup',
    '#value' => '<p>' . t('Clean state option will remove outdated transactions and do some maintenance work.') . '</p>',
  );

  $form['clean']['clean_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Clean state'),
    '#submit' => array('yamm_api_settings_clean_submit'),
  );

  $form['misc'] = array(
    '#type' => 'fieldset',
    '#title' => t('Yammm global options'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  $form['misc'][YAMM_OPT_DEBUG] = array(
    '#type' => 'checkbox',
    '#default_value' => variable_get(YAMM_OPT_DEBUG, NULL),
    '#title' => t('Debug mode'),
    '#description' => t("Check this and you will get A LOT of debug messages in watchdog entries"),
  );

  $form = system_settings_form($form);

  return $form;
}

function yamm_api_settings_clean_submit($form, &$form_state) {
  yamm_api_clean();
  drupal_set_message(t('Clean operation ran successfully'));
}
