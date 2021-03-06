<?php
// $Id: Node.php,v 1.2 2010/05/12 15:38:43 pounard Exp $

/**
 * Simple Yamm_Entity node implementation
 */
class Yamm_Entity_Node extends Yamm_Entity
{
  /**
   * (non-PHPdoc)
   * @see Entity::_objectLoad()
   */
  protected function _objectLoad($nid) {
    return node_load((int) $nid);
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_constructDependencies()
   */
  protected function _constructDependencies($node) {
    // Dependency on content type.
    $this->addDependency('content', $node->type);

    // Dependency on user, only if exists.
    if ($node->uid && ($user = user_load($node->uid))) {
      $user_uuid = $this->addDependency('user', $user->uid);
      $this->setData('user', $user_uuid);
    }

    // Dependency on terms.
    $terms = array();
    foreach ($node->taxonomy as $term) {
      $term_uuid = $this->addDependency('term', $term->tid);
      $terms[] = $term_uuid;
    }
    $this->setData('terms', $terms);

    // Handle node reference fields.
    $nodes = array();
    foreach ($node as $field_name => &$value) {
      if (substr($field_name, 0, 6) == 'field_' && isset($value[0]['nid'])) {
        $nodes[$field_name] = array();
        foreach ($value as $index => $referenced) {
          if (! empty($referenced['nid'])) {
            $node_uuid = $this->addDependency('node', $referenced['nid']);
            $nodes[$field_name][$index] = $node_uuid;
          }
        }
      }
    }
    $this->setData('nodes', $nodes);

    // TODO handle file and media fields, for this, we need abstract file
    // fetching through our entity parser.
  }

  /**
   * Internal method shared between _save() and _update() methods.
   *
   * @param object &$node
   */
  private function __restoreData($node) {
    // Be sure to update current revision
    unset($node->revision);

    // Restore owner
    if ($user_uuid = $this->getData('user')) {
      $node->uid = (int) Yamm_EntityFactory::getIdentifierByUuid($user_uuid);
    }

    // Restore terms
    $node->taxonomy = array();
    foreach ($this->getData('terms') as $term_uuid) {
      $tid = (int) Yamm_EntityFactory::getIdentifierByUuid($term_uuid);
      $node->taxonomy[$tid] = $tid;
    }

    // Restore node referenced nodes
    foreach ($this->getData('nodes') as $field_name => $value) {
      foreach ($value as $index => $node_uuid) {
        $referenced = (int) Yamm_EntityFactory::getIdentifierByUuid($node_uuid);
        $node->{$field_name}[$index] = array('nid' => $referenced);
      }
    }
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_save()
   */
  protected function _save($node) {
    unset($node->nid);
    unset($node->vid);
    $this->__restoreData($node);
    node_save($node);
    return $node->nid;
  }

  /**
   * (non-PHPdoc)
   * @see Entity::_update()
   */
  protected function _update($node, $nid) {
    $node->nid = (int) $nid;
    // The node_load() call costs a lot, but ensure we get the right revision
    // (this is usefull when you use revisioning module or such).
    $current = node_load($nid);
    $node->vid = $current->vid;
    $this->__restoreData($node);
    node_save($node);
  }
}
