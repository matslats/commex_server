<?php

commex_include('CommexObj', TRUE);

/**
 * Base class for REST endpoints
 *
 * This base class contains valid but hardly functional code. It is intended as
 * starting point for developers .
 * Each endpoint class should extend this class and hence implement CommexRestResourceInterface
 * This class would normally be heavily modified as well.
 */
abstract class CommexRestResourceBase implements CommexRestResourceInterface {

  /**
   * The name of the current resource, or the first part of the path
   *
   * @var string
   */
  protected $resource;

  /**
   * The internal representation of a data object.
   * @var CommexObj
   */
  private $object;

  /**
   * {@inheritdoc}
   */
  public function accessId($method, $account, $entity_id) {
    //might want to load the entity using $this->getEntity($entity_id)
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getObj($values) {
    if (empty($this->object) or $new) {
      $this->object = new CommexObj($this->fields(), static::RESOURCE);
    }
    return $this->object->set($values);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getList(array $params, $offset, $limit);

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    //load native Entity
    $fieldData = array('id' => $id);
    //prepare non-virtual CommexField values from the native entity
    return $fieldData;
  }

  /**
   * {@inheritdoc}
   *
   * you MUST overwrite this
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    //load new or existing entity
    //populate Entity from Commex
    //save Entity
    //$obj->ID has new entity id
  }

  /**
   * {@inheritdoc}
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = FALSE) {
    $fields = $obj->view($fieldnames, $expand);
    //opportunity here to modify the view
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions($entity_id = NULL) {
    if ($entity_id) {
      //you'll need to check here which operations the current user can do to the current entity.
      return array('GET', 'PATCH', 'DELETE');
    }
    else {
      // Again not all users can POST content of every type
      return array('GET', 'POST');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsFields($method) {
    $fields = [];
    if ($method != 'DELETE') {// DELETE requires no fields
      //make an empty commexObj and then interrogate it for this method
      $obj = $this->getObj();
      $fields += $obj->getFieldDefinitions($method);
      foreach ($fields as &$field) {
        if (isset($field['label'])) {
          // Here's an opportunity to translate the field labels
        }
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($entity_id) {
    //Delete the entity on the current resource with the given $entity_id;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, $password) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function fields() {
    $fields = $this->fields;
    if (empty($fields)) {
      throw new Exception('This resource has no fields defined.');
    }
    return $fields;
  }
}

