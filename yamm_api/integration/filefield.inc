<?php
// $Id$

/**
 * @file
 * Yamm integration on behalf of the filefield module.
 */

/**
 * Implementation of hook_yamm_entity_construct().
 */
function filefield_yamm_entity_construct(Yamm_Entity $entity) {
  if ($entity->getType() == 'node' && ($node = $entity->getObject())) {
    // Introspect node for file fields.
    $files = array();
    foreach (content_fields(NULL, $node->type) as $field_name => $field) {
      if ($field['type'] == 'filefield' && isset($node->{$field_name})) {
        // Found one.
        foreach ($node->{$field_name} as $key => $item) {
          // Sometimes content module will let empty values leave here.
          if (isset($item['fid']) && !empty($item['fid'])) {
            $files[$field_name][$item['fid']] = $entity->addDependency('file', $item['fid']);
          }
        }
      }
    }
    if (!empty($files)) {
      $entity->setData('filefield_files', $files);
    }
    // Bits of debug.
    if (yamm_api_debug_mode()) {
      if (!empty($files)) {
        foreach ($files as $field_name => $_files) {
          yamm_api_debug("Found !count files in field !field for node !nid", array(
            '!count' => count($_files),
            '!field' => $field_name,
            '!nid' => $node->nid,
          ));
        }
      }
      else {
        yamm_api_debug("Found no files for node %nid", array('!nid' => $node->nid));
      }
    }
  }
}

/**
 * Implementation of hook_yamm_entity_presave().
 */
function filefield_yamm_entity_presave(Yamm_Entity $entity) {
  if ($entity->getType() == 'node' && ($node = $entity->getObject())) {
    // Fetch back server data, if any.
    if ($files = $entity->getData('filefield_files', FALSE)) {
      foreach ($files as $field_name => $uuid_list) {
        foreach ($uuid_list as $dist_fid => $uuid) {
          // Some ugly part, attempt to find matching file in current field, we
          // cannot rely on keys because other modules could have altered the
          // field either.
          foreach ($node->{$field_name} as $key => $item) {
            if ($item['fid'] == $dist_fid) {
              // Fid should exits, except if any fatal error happened.
              if ($fid = Yamm_EntityFactory::getIdentifierByUuid($uuid)) {
                $file = db_fetch_array(db_query("SELECT * FROM {files} WHERE fid = %d", $fid));
                $node->{$field_name}[$key] = array_merge($item, $file);
              }
              // We found it, break here.
              break;
            }
          }
        }
      }
    }
  }
}
