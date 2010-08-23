<?php
// $Id: User.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * User entity for Yamm
 */

/**
 * Simple user Yamm_EntitySettingsAbstract implementation
 */
class Yamm_Entity_UserSettings extends Yamm_EntitySettingsAbstract {

  const DUPLICATE_IGNORE = 1;
  const DUPLICATE_OVERRIDE = 2;
  const DUPLICATE_OVERRIDENOPASS = 3;
  const DUPLICATE_PASSONLY = 4;

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Yamm_EntitySettingsAbstract#settingsForm()
   */
  public function form() {
    $form = array();

    $options = array(
      Yamm_Entity_UserSettings::DUPLICATE_IGNORE => t('Ignore new user definition (leave original as-is)'),
      Yamm_Entity_UserSettings::DUPLICATE_OVERRIDE => t('Update all information'),
      Yamm_Entity_UserSettings::DUPLICATE_OVERRIDENOPASS => t('Update all information except password'),
      Yamm_Entity_UserSettings::DUPLICATE_PASSONLY => t('Update password only'));

    $form['duplicate_behavior'] = array(
      '#type' => 'radios',
      '#title' => t('Duplicates handling'),
      '#options' => $options,
      '#description' => t('Define what behavior the the entity should adopt when username conflicts with an already existing one.'),
      '#default_value' => $this->get('duplicate_behavior', Yamm_Entity_UserSettings::DUPLICATE_IGNORE));

    $form['admin_override'] = array(
      '#type' => 'radios',
      '#title' => t('Admin override'),
      '#options' => $options,
      '#description' => t('Define what behavior the the entity should for site administrator.'),
      '#default_value' => $this->get('admin_override', Yamm_Entity_UserSettings::DUPLICATE_IGNORE));

    return $form;
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Yamm_EntitySettingsAbstract#formValidate()
   */
  public function formValidate($values) {
    // Nothing to validate
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Yamm_EntitySettingsAbstract#formSubmit($values)
   */
  public function formSubmit($values) {
    $this->set('duplicate_behavior', (int) $values['duplicate_behavior']);
    $this->set('admin_override', (int) $values['admin_override']);
  }
}

/**
 * Simple user Yamm_Entity implementation
 */
class Yamm_Entity_User extends Yamm_Entity {

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_objectLoad($identifier)
   */
  protected function _objectLoad($uid) {
    return user_load((int) $uid);
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_constructDependencies($object)
   */
  protected function _constructDependencies($account) {
    // No dependencies
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_save($object)
   */
  protected function _save($account) {
    // Handle duplicates
    if ($duplicate = user_load(array('name' => $account->name))) {
      $this->_update($account, $duplicate->uid);
      return $duplicate->uid;
    }

    unset($account->uid);
    $pass = $account->pass;

    $edit = (array) $account;
    unset($account);
    $account = user_save(NULL, $edit);

    // Restore password
    db_query("UPDATE {users} SET pass = '%s' WHERE uid = %d", array($pass, $account->uid));

    return $account->uid;
  }

  /**
   * (non-PHPdoc)
   * @see www/sites/all/modules/custom/yamm/yamm_api/Entity#_update($object, $identifier)
   */
  protected function _update($account, $uid) {
    $account->uid = (int) $uid;
    $pass = $account->pass;

    $settings = $this->getSettings();

    if ($uid == 1) {
      $behavior = $settings->get('admin_override', Yamm_Entity_UserSettings::DUPLICATE_IGNORE);
    }
    else {
      $behavior = $settings->get('duplicate_behavior', Yamm_Entity_UserSettings::DUPLICATE_IGNORE);
    }

    switch ($behavior) {
      case Yamm_Entity_UserSettings::DUPLICATE_IGNORE:
        break;

      case Yamm_Entity_UserSettings::DUPLICATE_OVERRIDE:
        user_save($account);
        db_query("UPDATE {users} SET pass = '%s' WHERE uid = %d", array($pass, $account->uid));
        break;

      case Yamm_Entity_UserSettings::DUPLICATE_OVERRIDENOPASS:
        user_save($account);
        break;

      case Yamm_Entity_UserSettings::DUPLICATE_PASSONLY:
        db_query("UPDATE {users} SET pass = '%s' WHERE uid = %d", array($pass, $account->uid));
        break;
    }
  }
}
