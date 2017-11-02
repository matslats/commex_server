<?php

/**
 * @file
 * Defines the member/ commex resource
 */
class proposition extends CommexRestResource {

  protected $entityTypeId = 'node';

  /**
   * The structure of the proposition, not translated.
   */
  function fields() {
    return array(
      'title' => array(
        'label' => 'Headline',
        'fieldtype' => 'CommexFieldText',
        'required' => TRUE,
        'filter' => TRUE,
        'edit access' => 'ownerOrAdmin'
      ),
      'description' => array(
        'label' => 'Full description',
        'fieldtype' => 'CommexFieldText',
        'long' => TRUE,
        'required' => FALSE,
        'filter' => TRUE,
        'edit access' => 'ownerOrAdmin'
      ),
      'user_id' => array(
        'label' => 'Owner',
        'fieldtype' => 'CommexFieldReference',
        'default' => 'currentUserId',
        'required' => FALSE,
        'resource' => 'member',
        'query' => 'fields=name&fragment=',
        'filter' => TRUE,
        'edit access' => 'isAdmin',
        '_comment' => 'defaults to the current user',
      ),
      'expires' => array(
        'label' => 'Display until',
        'fieldtype' => 'CommexFieldDate',//can be html 5 or a js component
        'default' => 'defaultExpiryDate',
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year',
        'required' => FALSE,
        'sortable' => TRUE,
        'edit access' => 'ownerOrAdmin'
      ),
      'category' => array(
        'label' => 'Category',
        'fieldtype' => 'CommexFieldCategory',
        'options_callback' => 'getCategoryOptions',
        'required' => TRUE,
        'filter' => 'getCategoryOptions',
        'edit access' => 'ownerOrAdmin'
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    // Populate the commexObj with default values
    if (empty($obj->user_id)) {
      $obj->user_id = $GLOBALS['user']->uid;
    }
    if (empty($obj->expires)) {
      $obj->expires = strtotime(variable_get('offers_wants_default_expiry', '+1 year'));
    }
    //copy the commexObj values over to the node
    if ($obj->id) {
      $node = node_load($obj->id);
    }
    else {
      $node = new stdClass();
      $node->type = 'proposition';
      node_object_prepare($node);
    }

    $node->title = $obj->title;
    $node->body[LANGUAGE_NONE][0]['value'] = $obj->description;
    $node->offers_wants_categories[LANGUAGE_NONE][0]['tid'] = $obj->category;
    $node->want = $this->resource == 'want';
    $node->end = $obj->expires;
    $node->uid = $obj->user_id;
    if ($obj->image) {
      $node->image[LANGUAGE_NONE][0]['value'] = $obj->image;
    }
    node_save($node);
    $obj->id = $node->nid;

    return $obj->view();
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    $node = node_load($id);
    $fieldData = parent::loadCommexFields($id) + array(
      'title' => $node->title,
      'description' => $node->body[LANGUAGE_NONE][0]['value'],//when do we sanitise this
      'user_id' => $node->uid,//
      'expires' => $node->end,
      'category' => $node->offers_wants_categories[LANGUAGE_NONE][0]['tid'], // Just the first
    );
    if ($imageItem = $node->image[LANGUAGE_NONE][0]) {
      $fieldData['image'] = file_create_url($imageItem['uri']);
    }
    // Drupal's user doesn't have a lastModified time - somehow.
    $this->lastModified = max(array($this->lastModified, $node->changed));
    //prepare non-virtual CommexField values from the native entity
    return $fieldData;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsFields(array $methods) {
    $fields = parent::getOptionsFields($methods);
    // Prevent showing mail on GET
    if ($method == 'POST' or $method == 'PATCH') {
      unset($fields['user_id']);
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getObj(array $vals = array()) {
    parent::getObj($vals);
    //Set the commex permissions
    $this->object->viewable = TRUE;
    $this->object->creatable = TRUE;
    $this->object->deletable = $this->ownerOrAdmin($this->object->id);
    //editable is handled field by field
    return $this->object;
  }


  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = db_select('node', 'n')->fields('n', array('nid'))
      ->condition('n.uid', 0, '>')
      ->condition('n.status', 1)
      ->condition('n.type', 'proposition')
      ->range($offset, $limit);
    $query->join('offers_wants', 'ow', 'ow.nid = n.nid AND ow.want = '. (int)($this->resource == 'want'));


    // Filter by name or part-name.
    if (!empty($params['fragment'])) {
      $query->condition('n.title', $params['fragment'].'%', 'LIKE');
    }
    // Filter by locality of the owner
    if (!empty($params['locality'])) {
      $query->join('users', 'u', 'u.uid = n.uid');
      $query->join('field_data_profile_address', 'a', "a.entity_id = u.uid AND a.entity_type = 'user'");
      $query->condition('a.profile_address_dependent_locality', $params['locality']);
    }

    // @todo Could this be move to the base class?
    if (!empty($params['cat_id'])) {
      // How do we know the name of the category field on the given entity type.
      // For now we assume it!
      if (array_key_exists('category', $this->fields())) {
        \Drupal::logger("commex $fieldname => $setting_name")->debug(print_r($params['cat_id'], 1));
        $query->condition($fieldname, implode(',', $params['cat_id']), 'IN');
      }
    }

    //sort (optional, string) ... Sort according to 'proximity' (default), 'pos' or 'neg' shows them in order of balances
    if (!empty($params['sort'])) {
      list($field, $dir) = explode(',', $params['sort'].',DESC');
      switch ($field) {
        case 'created':
          $query->orderby('n.created', $dir);
          break;
        case 'changed':
        default:
          $query->orderby('n.changed', $dir);
      }
    }

    return $query->execute()->fetchCol();
  }


  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $proposition = node_load($id);
    $operations = [];
    if (node_access('update', $node)) {
      if ($smallad->scope->value) {
        $operations['unpublish'] = 'Unpublish';
      }
      else {
        $operations['publish'] = 'Publish';
      }
    }
    return $operations;
  }

  /**
   * show or hide the ad.
   */
  function operate($id, $operation) {
    $proposition = node_load($id);
    switch ($operation) {
      case 'unpublish':
        $node->status = 0;
        break;
      case 'publish':
        $node->status = 1;
        break;
    }
    node_save($node);
  }

  /**
   * Field access callback
   */
  function ownerOrAdmin($nid) {
    $node = node_load($nid);
    return $this->isAdmin() or $node->uid = $GLOBALS['user']->uid;
  }

  /**
   * {@inheritdoc}
   */
  function isAdmin() {
    return !empty($GLOBALS['user']->roles[RID_COMMITTEE]);
  }

  function getCategoryOptions() {
    commex_require('Category', FALSE);
    return Category::getCategories();
  }

  /**
   * Field default value callback
   */
  function currentUserId() {
    return $GLOBALS['user']->uid;
  }


  /**
   * Field default callback
   */
  function defaultExpiryDate() {
    return strtotime(variable_get('offers_wants_default_expiry', '+1 year'));
  }

}
