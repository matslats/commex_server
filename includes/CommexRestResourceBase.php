<?php

/**
 * @file
 *
 * Do NOT Modify!
 */

commex_require('CommexObj', TRUE);

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
   * Check that all the standard endpoints have required fields. It wasn't
   * obvious how to jig the architecture to allow each type to check itself, for
   * example to do more detailed checks, or where to document this!
   */
  function __construct($endpoint) {
    switch($endpoint) {
      case 'member';
        $required = array('name', 'mail', 'pass', 'portrait');
        break;
      case 'offer':
      case 'want':
        // todo: check that user_id ia a references
        $required = array('title', 'description', 'user_id', 'category');
        break;
      case 'transaction':
        // todo: check that payer and payee are references
        $required = array('amount', 'description', 'payer', 'payee');
        break;
      default:
        throw new \Exception('Unsupported endpoint: '.$endpoint);
    }

    if ($missing = array_diff($required, array_keys($this->fields()))) {
      throw new exception('Missing fields on '.get_class($this->resourcePlugin).': '.implode(', ', $missing));
    }

    $this->resource = $endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function getObj(array $vals = array()) {
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
    $methods = array('OPTIONS', 'GET', 'HEAD');
    if ($id) {
      $values = $this->loadCommexFields($id);
      $obj = $this->getObj($values);
      if ($operation && $ops = $this->operations($id)) {
        if (isset($ops[$operation])) {
          $methods[] = 'PUT';
        }
      }
      else {
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
      if ($this->getObj()->creatable) {
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
    $methods = array_intersect($methods, array('GET', 'PATCH', 'POST', 'DELETE', 'PUT'));
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
