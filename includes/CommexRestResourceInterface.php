<?php

/**
 * @file
 *
 * Public functions, mostly called from index.php
 */

interface CommexRestResourceInterface {

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
  public static function authenticate($username, $password);


  /**
   * Delete an entity.
   *
   * @param string $entity_id
   *
   * @return boolean
   *   TRUE if the operation succeeded
   */
  public function delete($entity_id);

  /**
   * Declare the fields and metadata of each on this resource type. These are
   * used to to build and describe the CommexObj. The internal ID and the uri
   * will be added automatically.
   *
   * $fields = array(
   *   phone' => array(
   *     fieldtype => CommexFieldText //a class name, assumed to be in includes
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
  public function fields();

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
  public function getList(array $params, $offset, $limit);

  /**
   * Get a (new) commex object, populated with default values.
   *
   * @param array $values
   *   The values with which to populate the object, keyed by fieldname
   *
   * @return CommexObj
   *   Populated commex object
   */
  public function getObj(array $vals = array());


  /**
   * Determines which methods are available to the current user on the current
   * resource.
   *
   * @return string[]
   *   The names of the methods, e.g. [GET, POST], excluding OPTIONS!
   */
  public function getOptions($id = NULL, $operation = NULL);

  /**
   * Determine the structure of the member object for different methods.
   *
   * Typically it builds the POST structure and then modifies it.
   *
   * @param string $method
   *   An HTTP method
   */
  public function getOptionsFields(array $methods);

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
   */
  public function operations($id);

  /**
   * Convert from a native object's data to Commex fields.
   *
   * @param string $id
   *
   * @return array
   *   Field names and values
   */
  public function loadCommexFields($id);

  /**
   * Convert from given commex object fields to native data object.
   *
   * @param CommexObj $obj
   * @param array $errors
   */
  public function saveNativeEntity(CommexObj $obj, &$errors = array());

  /**
   * Virtual field callback
   * @param string $id
   * @param string $operation
   *
   * @return string
   */
  public function uri($id, $operation = NULL);


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
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = FALSE);

}
