<?php

/**
 * @file
 *
 * Do NOT Modify!
 */

abstract class CommexRestResourceBase {

  /**
   * The name of the current resource, or the first part of the path
   *
   * @var string
   */
  protected $resource;

  /**
   * The last modified date of the last modified entity loaded
   *
   * @var int
   */
  public $lastModified;

  /**
   * The Commex Obj in hand
   *
   * @var CommexObj
   */
  protected $object;

  /**
   * Text for delete confirmation modal (translated)
   * @var string
   */
  protected $deleteConfirm = "Are you sure you want to delete this item?";

  /**
   * {@inheritdoc}
   */
  public function getObj(array $vals = array()) {
    commex_require('CommexObj', TRUE);
    $this->object = new CommexObj($this);
    if ($vals) {
      $this->object->set($vals);
    }
    return $this->object;
  }

  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $operations = array();
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions($id = NULL, $operation = NULL) {
    //we can read the commex object to know about view and edit access.
    $methods = array('OPTIONS');
    $obj = $this->getObj();
    if ($id) {
      if ($operation && $ops = $this->operations($id)) {
        if (in_array($operation, $ops)) {
          $methods[] = 'PUT';
        }
      }
      else {
        $values = $this->loadCommexFields($id);
        $obj->set($values);
        if ($obj->editable) {
          $methods[] = 'PATCH';
        }
        if (!empty($obj->deletable)) {
          $methods[] = 'DELETE';
        }
      }
    }
    else {
      $methods[] = 'GET';
      $methods[] = 'HEAD';
      if ($obj->creatable) {
        $methods[] = 'POST';
      }
    }
    return $methods;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsFields(array $methods) {
    $info = array();
    $methods = array_intersect($methods, array('GET', 'PATCH', 'POST', 'DELETE'));
    foreach ($methods as $method) {
      if ($method == 'DELETE') {
        $info[$method]['confirm'] = $this->deleteConfirm;
      }
      else {
        $info[$method] = $this->object->getFieldDefinitions($method);
      }
    }
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    return array('id' => $id);
  }

  /**
   * {@inheritdoc}
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $id = $obj->id;
    $result = $obj->view($fieldnames, $expand);
    // Add HATEOAS links where fieldnames haven't been specified.
    if (empty($fieldnames)) {
      $result['_links'][] = array(
        'rel' => 'self',
        'href' => $this->uri($id),
      );
      if ($operations = $this->operations($id)) {
        foreach ($operations as $op => $label) {
          $result['_links'][] = array(
            'label' => (string)$label,//Translate this.
            'rel' => $op,
            'href' => $this->uri($id, $op),
            'confirm' => 'Are you sure?', // This must be overridden translated.
            '_comment' => 'all operations use PUT'
          );
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  function uri($id, $operation = NULL) {
    if (!$id) {
      throw  new \Exception('Cannot form uri with empty ID');
    }
    $parts = array($this->resource);
    $parts[] = $id;
    if ($operation) {
      $parts[] = $operation;
    }
    return implode('/', $parts);
  }



}
