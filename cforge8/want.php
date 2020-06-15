<?php

use Drupal\smallads\Entity\Smallad;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * @file
 *
 * Defines the want/ commex resource
 */
class CommexWant extends CommexRestResource {

  protected $entityTypeId = 'smallad';
  protected $bundle = 'want';

  /**
   * The structure of the want, not translated.
   */
  function fields() {
    $fields = [
      'title' => [
        'label' => 'Headline',
        'fieldtype' => 'CommexFieldText',
        'required' => TRUE
      ],
      'description' => [
        'label' => 'Full description',
        'fieldtype' => 'CommexFieldText',
        'long' => TRUE,
        'required' => FALSE
      ],
      'user_id' => [
        'label' => 'Owner',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.name',
        'required' => FALSE,
        'default_callback' => 'currentUserId',
        'edit_access' => 'adminOnly',
        '_comment' => 'defaults to the current user'
      ],
      'expires' => [
        'label' => 'Display until',
        'fieldtype' => 'CommexFieldDate',//client decides whether this is html5 or a js component
        'default_callback' => 'defaultExpiryDate',
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year',
        'required' => FALSE,
        'sortable' => TRUE,
      ],
      'changed' => [
        'label' => 'Last edited',
        'fieldtype' => 'CommexFieldVirtual',
        'callback' => 'changedDate',
        'sortable' => TRUE,
      ],
      'category' => array(
        'label' => 'Category',
        'fieldtype' => 'CommexFieldCategory',
        'required' => TRUE
      )
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    if ($smallad = Smallad::load($id)) {
      $values = parent::loadCommexFields($id) + [
        'title' => $smallad->label(),
        'description' => $smallad->body->value,//how do we make this safe?
        'user_id' => $smallad->getOwnerId(),
        'expires' => $smallad->expires->value,
      ];
      foreach ($smallad->categories->getValue() as $val) {
        $values['category'][] = $val['target_id'];
      }
      if (empty($this->fields()['category']['multiple'])) {
        $values['category'] = reset($values['category']);
      }
      return $values;
    }
    throw new Exception('Unknown want ID: '.$id);
  }


  /**
   * Copy the values from the commex object to the entity
   *
   * @param \Drupal\commex_rest\Plugin\CommexRestResource\CommexObj $obj
   * @param ContentEntityInterface $smallad
   */
  protected function translateToEntity(CommexObj $obj, ContentEntityInterface $smallad) {
    parent::translateToEntity($obj, $smallad);
    $smallad->title = $obj->title;
    $smallad->type = 'want';
    $smallad->body = $obj->description;
    $smallad->setOwnerId($obj->user_id);
    $smallad->expires->value = $obj->expires;
    $smallad->categories->setValue($obj->category);
  }

  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = $this->getListQuery($params, $offset, $limit);
    $query->condition('type', 'want');
    if (!empty($params['fragment'])) {
      $group = $query->orConditionGroup()
        ->condition('title', '%'.$params['fragment'].'%', 'LIKE')
        ->condition('body__value', '%'.$params['fragment'].'%', 'LIKE');
      $query->condition($group);
    }
    if (!empty($params['category'])) {
      $query->condition('categories', explode(',', $params['category']), 'IN');
    }
    if (!empty($params['user_id'])) {
      $query->condition('uid', $params['user_id']);
    }
    // Unless we are filtering by the current user, show all the items
    if (empty($params['user_id']) or !$this->adminOnly()) {
      $query->condition('scope', 0, '>');
    }
    $params += ['sort' => 'changed'];
    //sort (optional, string) ... Sort according to 'proximity' (default), 'pos' or 'neg' shows them in order of balances
    list($field, $dir) = explode(',', $params['sort'].',DESC');
    switch ($field) {
      case 'expires':
        $query->sort('expires', $dir);
        break;
      case 'changed':
        $query->sort('changed', 'DESC');
        break;
      default:
        trigger_error('Cannot sort by wants by field: '.$field, E_USER_ERROR);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $smallad = Smallad::load($id);
    $operations = [];
    if ($smallad->access('update')) {
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
    $smallad = Smallad::load($id);
    switch ($operation) {
      case 'unpublish':
        $smallad->scope->value = 0;
        $smallad->expires->value = \Drupal::time()->getRequestTime() -1;
        break;
      case 'publish':
        $smallad->scope->value = 2;
        $smallad->expires->value = \Drupal::time()->getRequestTime() + 3600*24*28;
        break;
    }
    $smallad->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getObj(array $vals = array()) {
    parent::getObj($vals);
    //Set the commex permissions
    $this->object->viewable = TRUE;
    $this->object->creatable = TRUE;
    $this->object->deletable = $this->ownerOrAdmin($this->object);
    // Convert the user name to the id
    if (isset($vals['user_id']) && !is_numeric($vals['user_id'])) {
      if ($uids = \Drupal::entityQuery('user')->condition('name', $vals['user_id'])->execute()) {
        $this->object->user_id = reset($uids);
      }
      else {
        throw new \Exception('Cannot identify user: '.$vals['user_id']);
      }
    }
    return $this->object;
  }

 /**
   * Field access callback
   */
  public function adminOnly($id = 0) {
    return \Drupal::currentUser()->hasPermission('edit all smallads');
  }

  /**
   * Field default callback
   */
  function currentUserTarget() {
    return ['target_id' => \Drupal::currentUser()->id()];
  }
  /**
   * Field default callback
   */
  function currentUserId() {
    return \Drupal::currentUser()->id();
  }

  /**
   * Field default callback
   */
  function defaultExpiryDate() {
    return strtotime(\Drupal::config('smallads.settings')->get('default_expiry'));
  }

  /**
   * Field default callback
   */
  function changedDate() {
    return date('d-M-Y', strtotime(\Drupal::config('smallads.settings')->get('default_expiry')));
  }

  /**
   * Field access callback
   *
   * Determine whether a field on a populated commex Object is editable by the current user
   *
   * @param string $id
   *   The id of the want, if applicable
   *
   * @return bool
   *   TRUE if acces is granted
   */
  public function ownerOrAdmin() {
    static $result = NULL;
    if (is_null($result)) {
      $account = \Drupal::currentUser();
      if ($account->hasPermission('edit all smallads')) {
        $result = TRUE;
      }
      else {// PATCH
        $result = $this->object->user_id == $account->id();
      }
    }
    return $result;
  }

}
