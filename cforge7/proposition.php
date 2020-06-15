<?php

/**
 * @file
 * Defines the member/ commex resource
 */
abstract class proposition extends CommexRestResource {

  protected $entityTypeId = 'node';

  /**
   * The structure of the proposition, not translated.
   */
  function fields() {
    $fields = array(
      'title' => array(
        'label' => 'One line description',// from offers_wants module
        'fieldtype' => 'CommexFieldText',
        'required' => TRUE,
        'filter' => TRUE,
      ),
      'description' => array(
        'label' => field_read_instance('node', 'body', 'proposition')['label'],
        'fieldtype' => 'CommexFieldText',
        'lines' => 4,
        'required' => FALSE,
        'filter' => TRUE,
      ),
      'user_id' => array(
        'label' => t('Owner'),
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.name',
        'default_callback' => 'currentUserId',
        'required' => FALSE,
        'filter' => TRUE,
        'edit_access' => 'isAdmin',
        '_comment' => 'defaults to the current user',
      ),
      'expires' => array(
        'label' => 'Display until',// from offers_wants module
        'fieldtype' => 'CommexFieldDate',//can be html 5 or a js component
        'default_callback' => 'defaultExpiryDate',
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year',
        'required' => FALSE,
        'sortable' => TRUE,
      ),
      'category' => array(
        'label' => 'Category',
        'fieldtype' => 'CommexFieldCategory',
        'options_callback' => 'getCategoryOptions',
        'required' => TRUE,
        'filter' => 'getCategoryOptions',
      )
    );
    $vid = db_query("SELECT vid FROM {taxonomy_vocabulary} WHERE machine_name = 'offers_wants_types'")->fetchField();
    if (count(taxonomy_get_tree($vid)) > 1) {
      $fields['type'] = array(
        'label' => 'Type',
        'fieldtype' => 'CommexFieldEnum',
        'required' => TRUE,
        'filter' => 'getTypeOptions',
      );
      foreach (taxonomy_get_tree($vid) as $term) {
        // Hope there are no translation issues....
        $fields['type']['options'][$term->tid] = $term->name;
      }
    }
    return $fields;
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
      unset($node->menu);
    }
    global $language;
    $node->language = $language->language;
    $node->title = $obj->title;
    $node->body[LANGUAGE_NONE][0] = array(
      'value' => $obj->description,
      'format' => 'editor_filtered_html'
    );
    $node->offers_wants_categories[LANGUAGE_NONE][0]['tid'] = $obj->category;
    if ($obj->has('type')) {
      $node->offers_wants_types[LANGUAGE_NONE][0]['tid'] = $obj->type;
    }
    $node->want = $this->resource == 'want';
    $node->end = $obj->expires;
    $node->uid = $obj->user_id ?: $GLOBALS['user']->uid;
    if ($obj->has('image') and $img = $obj->image) {
      $node->image[LANGUAGE_NONE][0]['value'] = $img;
    }
    node_save($node);
    $obj->id = $node->nid;

    return $obj->view();
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    if ($node = node_load($id)) {
      $body = field_view_field('node', $node, 'body');
      $fieldData = parent::loadCommexFields($id) + array(
        'title' => $node->title,
        'description' => !empty($body)? $body[0]['#markup'] : '',
        'user_id' => $node->uid,//
        'expires' => $node->end,
        'category' => $node->offers_wants_categories[LANGUAGE_NONE][0]['tid'], // Just the first
      );
      if (!empty($node->offers_wants_types)) {
        $fieldData['type'] = $node->offers_wants_types[LANGUAGE_NONE][0]['tid'];
      }
      if ($imageItem = isset($node->image[LANGUAGE_NONE]) ? $node->image[LANGUAGE_NONE][0] : 0) {
        $fieldData['image'] = file_create_url($imageItem['uri']);
      }
      // Drupal's user doesn't have a lastModified time - somehow.
      $this->lastModified = max(array($this->lastModified, $node->changed));
      //prepare non-virtual CommexField values from the native entity
      return $fieldData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsFields(array $methods) {
    $fields = parent::getOptionsFields($methods);
    unset($fields['POST']['user_id']);
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
    $this->object->deletable = $this->ownerOrAdmin();
    //editable is handled field by field
    return $this->object;
  }

  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = db_select('node', 'n')->fields('n', array('nid'))
      ->condition('n.type', 'proposition')
      ->range($offset, $limit);
    $query->join('offers_wants', 'ow', 'ow.nid = n.nid AND ow.want = '. (int)($this->resource == 'want'));

    // Filter by name or part-name.
    if (!empty($params['fragment'])) {
      $query->join('field_data_body', 'b', 'b.revision_id = n.vid');
      $condition = db_or()
        ->condition('n.title', '%'.$params['fragment'].'%', 'LIKE')
        ->condition('b.body_value', '%'.$params['fragment'].'%', 'LIKE');;
      $query->condition($condition);
    }
    // Filter by locality of the owner
    if (!empty($params['locality'])) {
      $query->join('users', 'u', 'u.uid = n.uid');
      $query->join('field_data_profile_address', 'a', "a.entity_id = u.uid AND a.entity_type = 'user'");
      $query->condition('a.profile_address_dependent_locality', $params['locality']);
    }
    if (!empty($params['user_id'])) {
      $query->condition('n.uid', $params['user_id']);
    }
    else {
      $query->condition('n.uid', 0, '>');
    }
    if (empty($params['user_id']) or !$this->isAdmin()) {
      $query->condition('n.status', 1);
    }

    // @todo Could this be move to the base class?
    if (!empty($params['category'])) {
      $query->join('field_data_offers_wants_categories', 'c', "c.entity_id = n.nid AND c.entity_type = 'node'");
      $category = (array)$params['category'];
      $query->condition('c.offers_wants_categories_tid', $category, 'IN');
    }

    $params += array('sort' => 'changed');
    // Sort (optional, string) ... Sort according to 'proximity' (default), 'pos' or 'neg' shows them in order of balances
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
    $operations = array();
    if (node_access('update', $proposition)) {
      if ($proposition->status) {
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
        $proposition->end = time() -1;
        break;
      case 'publish':
        $proposition->end = strtotime('+1 month');
        break;
    }
    node_save($proposition);
  }

  /**
   * Field access callback
   */
  public function ownerOrAdmin() {
    return $this->isAdmin() or $this->object->user_id = $GLOBALS['user']->uid;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdmin() {
    return user_access('edit propositions');
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function delete($nid) {
    node_delete($nid);
    return !node_load($nid);
  }

}
