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
   * The last modified date of the last modified entity loaded
   * @var int
   * @deprecated
   */
  public $lastModified;

  /**
   * The internal representation of a data object.
   * @var CommexObj
   */
  private $object;

  /**
   * Determine access to the main resource route.
   *
   * @param type $method
   *   GET, HEAD, POST
   * @param $account
   *
   * @return bool
   */
  public function access($method, $account) {
    return TRUE;
  }

  /**
   * Determine access to a specific entity.
   *
   * @param string $method
   *   GET, HEAD, PATCH, DELETE
   * @param $account
   * @param int $entity_id
   *
   * @return Bool
   */
  public function accessId($method, $account, $entity_id) {
    //might want to load the entity using $this->getEntity($entity_id)
    return TRUE;
  }

  /**
   * Get a (new) commex object, populated with default values.
   *
   * @param array $vals
   *   The values with which to populate the object, keyed by fieldname

   * @return CommexObj
   *   Populated commex object
   */
  public function getObj($values) {
    if (empty($this->object) or $new) {
      $this->object = new CommexObj($this->fields(), static::RESOURCE);
    }
    return $this->object->set($values);
  }

  /**
   * Build a query listing the entities according to the passed parameters.
   *
   * @param array $params
   * @param int $offset
   * @param int $limit
   *
   * @return array
   *   The entity ids.
   */
  abstract public function getList(array $params, $offset, $limit);

  /**
   * Convert from a native object's data to Commex fields

   * @param string $id

   * @return type
   *
   * NB you MUST extend this, and either set the id, or call this as the parent
   */
  function loadCommexFields($id) {
    //load native Entity
    $fieldData = array('id' => $id);
    //prepare non-virtual CommexField values from the native entity
    return $fieldData;
  }

  /**
   * Convert from given commex object fields to native data object.
   *
   * @param CommexObj $obj
   * @param type $errors
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
   * Render, and if $fieldnames is supplied, filter and order the fields.
   *
   * @param CommexObj $obj
   *   The json object being built
   * @param array $fieldnames
   *   the fieldnames to filter by
   * @param bool $expand
   *   TRUE to expand the references
   *
   * @return array
   *   field values, keyed by field_names, in accordance with structure(GET)
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = FALSE) {
    $fields = $obj->view($fieldnames, $expand);
    //opportunity here to modify the view
    return $fields;
  }

  /**
   * Get the HTTP methods available to the current user.
   *
   * @return string[]
   *   The names of the methods, e.g. [GET, POST], excluding OPTIONS!
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
   * Determine the structure of the member object for different methods.
   *
   * Typically it builds the POST structure and then modifies it.
   *
   * @param string $method
   *   An HTTP method
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
   * Delete an entity.
   *
   * @param type $entity_id
   *
   * @return boolean|int
   *   TRUE if the operation succeeded
   */
  public function delete($entity_id) {
    //Delete the entity on the current resource with the given $entity_id;
    return TRUE;
  }

  /**
   * Check whether the given username & password are valid. You probably want to set
   * a global variable $user
   *
   * @param string $username
   * @param string $password
   *
   * @return boolean
   *   TRUE if the credentials are correct
   */
  public function authenticate($username, $password) {
    return TRUE;
  }


  /**
   * Declare the fields and metadata of each on this resource type. These are
   * used to to build and describe the CommexObj. The internal ID and the uri
   * will be added automatically.
   *
   * $fields = array(
   *   phone' => array(
   *     fieldtype => CommexFieldText //a class name, assumed to be in commex_lib
   *     label => Phone //untranslated. If it doesn't appear the field is invisible
   *     required => FALSE //required to create a new entity
   *     default => A public method in this class which returns the default value.
   *     sortable => FALSE // click sort is supported in getList method
   *     _comment => for validation consider https://github.com/googlei18n/libphonenumber,
   *     //other optional or required properties according to the fieldtype
   *   )
   * )
   * @return array
   *   Field info, keyed by field name
   */
  protected function fields() {
    $fields = $this->fields;
    if (empty($fields)) {
      throw new Exception('This resource has no fields defined.');
    }
    return $fields;
  }
}

