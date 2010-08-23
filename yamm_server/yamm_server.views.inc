<?php

function yamm_server_views_data() {
  $data = array();

  $data['views_view']['table'] = array(
    'group' => 'Yamm specials',
    'base' => array(
      'field' => 'vid',
      'title' => t('Views'),
    ),
  );

  $data['views_view']['vid'] = array(
    'title' => t('VID'),
    'help' => t('The view ID of the field, defined by the database.'), 
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    // Information for accepting a nid as an argument
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
      'numeric' => TRUE,
    ),
    // Information for accepting a nid as a filter
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
    ),
    // Information for sorting on a nid.
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['views_view']['name'] = array(
    'title' => t('Name'),
    'help' => t('The unique name of the view. This is the primary field views are loaded from.'), 
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    // Information for accepting a nid as an argument
    'argument' => array(
      'handler' => 'views_handler_argument_string',
      'numeric' => FALSE,
    ),
    // Information for accepting a nid as a filter
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    // Information for sorting on a nid.
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  $data['views_view']['tag'] = array(
    'title' => t('Tag'),
    'help' => t('A tag used to group/sort views in the admin interface.'), 
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    // Information for accepting a nid as an argument
    'argument' => array(
      'handler' => 'views_handler_argument_string',
      'numeric' => FALSE,
    ),
    // Information for accepting a nid as a filter
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    // Information for sorting on a nid.
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );

  return $data;
}
