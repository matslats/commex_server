<?php

/**
 * @file
 *
 * Public functions, mostly called from index.php
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
   * Get a commex object for this resource type, optionally populated with the given values.
   *
   * @param array $vals
   */
  public function getObj(array $vals = array()) {
    if (empty($this->object)) {
      commex_require('CommexObj', TRUE);
      $this->object = new CommexObj($this);
    }
    $this->object->set($vals);
    return $this->object;
  }

  /**
   * Get the operations the current user can do to the entity with the given ID
   *
   * Operations transform the entity without going through an edit form.
   *
   * @param type $id
   *   The id of a resource.
   *
   * @return string[]
   *   an array of operation labels, keyed by a string identifier
   *
   */
  function operations($id) {
    $operations = [];
    return $operations;
  }

  /**
   * Determines which methods are available on the current resources.
   */
  public function getOptions($id = NULL, $operation = NULL) {
    //we can read the commex object to know about view and edit access.
    $methods = ['OPTIONS'];
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
      if ($obj->creatable) {
        $methods[] = 'POST';
      }
    }
    if ($obj && $obj->viewable) {
      $methods[] = 'GET';
      $methods[] = 'HEAD';
    }
    return $methods;
  }

  /**
   * Show which fields are expected for each of the given http methods
   */
  public function getOptionsFields(array $methods) {
    $info = [];
    $methods = array_intersect($methods, ['GET', 'PATCH', 'POST', 'DELETE']);
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
   * get the values to be injected into the commex object fields
   * @param string $id
   *
   * @note This should be extended every time!
   */
  function loadCommexFields($id) {
    return array('id' => $id);
  }

  /**
   * Prepare the Commex object for viewing with the client, including the HATEOAS links
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $id = $obj->id;
    $result = $obj->view($fieldnames, $expand);
    // Add HATEOAS links where fieldnames haven't been specified.
    if (empty($fieldnames)) {
      $result['_links'][] = [
        'rel' => 'self',
        'href' => $this->uri($id),
        '_comment' => 'this is here for experimentation..'
      ];
      if ($operations = $this->operations($id)) {
        foreach ($operations as $op => $label) {
          $result['_links'][] = [
            'label' => (string)$label,//Translate this.
            'rel' => $op,
            'href' => $this->uri($id, $op),
            'confirm' => 'Are you sure?', // This must be overridden translated.
            '_comment' => 'all operations use PUT'
          ];
        }
      }
    }
    return $result;
  }

  /**
   * Virtual field callback
   */
  function uri($id, $operation = NULL) {
    if (!$id) {
      throw  new \Exception('Cannot form uri with empty ID');
    }
    $parts = [$this->resource];
    $parts[] = $id;
    if ($operation) {
      $parts[] = $operation;
    }
    return implode('/', $parts);
  }



}