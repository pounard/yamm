<?php
// $Id: Vocabulary.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * Vocabulary entity settings implementation.
 */
class Yamm_Entity_VocabularySettings extends Yamm_EntitySettingsAbstract
{
  const DUPLICATE_IGNORE = 1;
  const DUPLICATE_OVERRIDE = 2;

  /**
   * (non-PHPdoc)
   * @see IFormable::form()
   */
  public function form() {
    $form = array();

    $options = array(
      Yamm_Entity_VocabularySettings::DUPLICATE_IGNORE => t('Create new vocabulary'),
      Yamm_Entity_VocabularySettings::DUPLICATE_OVERRIDE => t('Update existing'),
    );

    $form['duplicate_behavior'] = array(
      '#type' => 'radios',
      '#title' => t('Duplicates handling'),
      '#options' => $options,
      '#description' => t('Define what behavior the the entity should adopt when vocabulary name conflicts with an existing one. Beware, if more than one duplicate is found, the algorithm won\'t merge but will create a new one instead.'),
      '#default_value' => $this->getOption('duplicate_behavior', Yamm_Entity_VocabularySettings::DUPLICATE_IGNORE),
    );

    return $form;
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::formValidate()
   */
  public function formValidate(&$values) {
    // Nothing to validate
  }

  /**
   * (non-PHPdoc)
   * @see IFormable::formSubmit()
   */
  public function formSubmit(&$values) {
    $this->setOption('duplicate_behavior', (int) $values['duplicate_behavior']);
  }
}

/**
 * Vocabulary entity implementation.
 */
class Yamm_Entity_Vocabulary extends Yamm_Entity
{
  /**
   * (non-PHPdoc)
   * @see Yamm_EntityAbstract::_objectLoad()
   */
  protected function _objectLoad($vid) {
    return taxonomy_vocabulary_load((int) $vid);
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntityAbstract::_constructDependencies()
   */
  protected function _constructDependencies($vocabulary) {
    // No dependencies.
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntityAbstract::_save()
   */
  protected function _save($vocabulary) {
    $edit = (array) $vocabulary;
    unset($edit['vid']);
    if ($this->getSettings()->getOption('merge_duplicates', FALSE)) {
      $this->__mergeWithExisting($edit);
    }
    taxonomy_save_vocabulary($edit);
    return $edit['vid'];
  }

  private function __mergeWithExisting(&$edit) {
    if (1 != db_result(db_query("SELECT COUNT(*) FROM {vocabulary} WHERE name = '%s'", $edit['name']))) {
      return;
    }
    $edit['vid'] = db_result(db_query("SELECT vid FROM {vocabulary} WHERE name = '%s'", $edit['name']));
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntityAbstract::_update()
   */
  protected function _update($vocabulary, $vid) {
    $edit = (array) $vocabulary;
    $edit['vid'] = (int) $vid;
    taxonomy_save_vocabulary($edit);
  }
}
