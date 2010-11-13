<?php
// $Id: Term.php,v 1.1 2010/03/24 00:45:43 pounard Exp $

/**
 * @file
 * Term entity for Yamm
 */

/**
 * Term Yamm_EntitySettingsAbstract implementation
 */
class Yamm_Entity_TermSettings extends Yamm_EntitySettingsAbstract {

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::settingsForm()
   */
  public function form() {
    $form = array();

    $form['merge_duplicates'] = array(
      '#type' => 'checkbox',
      '#title' => t('Merge duplicates'),
      '#description' => t('This options will merge client side local terms with incoming server ones if they share the same exact name. Note that terms linked to a UUID will not be merged. There is caveat with this method, to be sure we can handle multiple merge, we use the newly created identifier as reference, which could break existing contrib using term identifiers behavior (i.e. views based on tid).'),
      '#default_value' => $this->get('merge_duplicates', FALSE)
    );

    return $form;
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::formValidate()
   */
  public function formValidate($values) {
    // Nothing to validate.
  }

  /**
   * (non-PHPdoc)
   * @see Yamm_EntitySettingsAbstract::formSubmit)
   */
  public function formSubmit($values) {
    $this->set('merge_duplicates', (bool) $values['merge_duplicates']);
  }
}

/**
 * Simple Term Yamm_Entity implementation
 */
class Yamm_Entity_Term extends Yamm_Entity {

  /**
   * (non-PHPdoc)
   * @see Entity::_objectLoad()
   */
  protected function _objectLoad($tid) {
    return taxonomy_get_term((int) $tid);
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_constructDependencies()
   */
  protected function _constructDependencies($term) {
    // Create vocabulary as dependency
    $vocabulary_uuid = $this->addDependency('vocabulary', $term->vid);
    $this->setData('vocabulary', $vocabulary_uuid);

    // And parents
    $parents = array();
    foreach (taxonomy_get_parents($term->tid) as $_term) {
      $parent_uuid = $this->addDependency('term', $_term->tid);
      $parents[$parent_uuid] = $parent_uuid;
    }
    $this->setData('parents', $parents);

    // And relations
    $related = array();
    foreach (taxonomy_get_related($term->tid) as $_term) {
      $related_uuid = $this->addDependency('term', $_term->tid);
      $related[$related_uuid] = $related_uuid;
    }
    $this->setData('relations', $related);

    // And synonyms
    $this->setData('synonyms', taxonomy_get_synonyms($term->tid));
  }

  /**
   * Internal method shared between _save() and _update() methods.
   *
   * @param object &$node
   * @return void
   */
  private function __restoreData(&$edit) {
    $edit['vid'] = (int) Yamm_EntityFactory::getIdentifierByUuid($this->getData('vocabulary'));
    $edit['parent'] = array();

    // Restore parents
    foreach ($this->getData('parents') as $uuid) {
      $tid = (int) Yamm_EntityFactory::getIdentifierByUuid($uuid);
      $edit['parent'][$tid] = $tid;
    }

    // Restore relations
    foreach ($this->getData('relations') as $uuid) {
      $tid = (int) Yamm_EntityFactory::getIdentifierByUuid($uuid);
      $edit['relations'][$tid] = $tid;
    }

    // Restore synonyms
    $edit['synonyms'] = $this->getData('synonyms');
  }

  /**
   * Find duplicates among terms with no UUID, based on name, and merge them all
   * with new one.
   *
   * Prerequisite is taxonomy have a tid (in case of term save, we have to save
   * it once before editing the $edit array and save it again.
   *
   * @param array $edit
   *   Edit array prepared for node save of the current term being saved
   */
  private function __mergeExisting($edit) {
    // We do this switch because of the non standard MySQL CAST() function types
    global $db_type;
    switch ($db_type) {
      case 'mysql':
      case 'mysqli':
        $result = db_query("SELECT t.tid FROM {term_data} t WHERE t.tid <> %d AND t.vid = %d AND t.name = '%s'" .
          // Do not merge with terms provided by master
          " AND NOT EXISTS (SELECT 1 FROM {yamm_uuid} y WHERE CAST(y.identifier AS UNSIGNED INTEGER) = t.tid)",
          array($edit['tid'], $edit['vid'], $edit['name'])
        );
        break;
      case 'pgsql':
        $result = db_query("SELECT t.tid FROM {term_data} t WHERE t.tid <> %d AND t.vid = %d AND t.name = '%s'" .
          // Do not merge with terms provided by master
          " AND NOT EXISTS (SELECT 1 FROM {yamm_uuid} y WHERE CAST(y.identifier AS INTEGER) = t.tid)",
          array($edit['tid'], $edit['vid'], $edit['name'])
        );
        break;
    }

    // Merge all found terms
    while ($data = db_fetch_object($result)) {
      $tid = $data->tid;

      // Merge relations
      $relations = taxonomy_get_related($tid);
      foreach ($relations as $related_id => &$related_term) {
        $edit['relations'][$related_id] = $related_id;
      }

      // Merge synonyms
      $synonyms = taxonomy_get_synonyms($tid);
      foreach ($synonyms as $name) {
        if (! in_array($name, $edit['synonyms'])) {
          $edit['synonyms'][] = $name;
        }
      }

      // We also have to merge node dependencies
      $_result = db_query("SELECT nid,tid FROM {term_node} WHERE tid = %d", $tid);
      while ($_data = db_fetch_object($_result)) {
        $nid = $_data->nid;
        db_query("DELETE FROM {term_node} WHERE nid = %d AND tid IN (%d, %d)", array($nid, $tid, $edit['tid']));
        db_query("INSERT INTO {term_node} (nid, tid, vid) VALUES (%d, %d, %d)", array($nid, $edit['tid'], $edit['vid']));
      }

      // We do not want to merge parents, because we could have some
      // inconsistencies with synchronized taxonomy, but we should merge childs.
      db_query("UPDATE {term_hierarchy} SET parent = %d WHERE parent = %d", array($edit['tid'], $tid));

      // And finally
      taxonomy_del_term($tid);
    }
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_save()
   */
  protected function _save($term) {
    $edit = (array) $term;
    unset($edit['tid']);
    $this->__restoreData($edit);
    taxonomy_save_term($edit);
    if ($this->getSettings()->get('merge_duplicates', FALSE)) {
      $this->__mergeExisting($edit);
      taxonomy_save_term($edit);
    }
    return $edit['tid'];
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_update()
   */
  protected function _update($term, $tid) {
    $edit = (array) $term;
    $edit['tid'] = (int) $tid;
    $this->__restoreData($edit);
    if ($this->getSettings()->get('merge_duplicates', FALSE)) {
      $this->__mergeExisting($edit);
    }
    taxonomy_save_term($edit);
  }
}
