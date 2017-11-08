<?php

/**
 * A commex object is an object served by a resource e.g. an offer, a member, a
 * transaction
 *
 * This class works with the Types class to translate the data in the object
 * from the client to your platform's native object.
 *
 * Create a CommexObj thus:
 * Declare the fields on the object's type, exluding the id.
 *
 * $field_definitions = array(
 *   'name' => array(
 *     'type' => 'textfield',
 *     'label' => 'First name & last name',
 *     'required' => TRUE
 *   ),
 *   'phone' => array(
 *     'type' => 'tel',
 *     'label' => 'Phone',
 *   ],
 *   etc.
 * )
 *
 * Then
 * $obj = new CommexObj($field_definitions);
 *
 * Now you have a unfied interface for:
 * getting field values to display e.g.
 * $json = $obj->view()
 *
 * setting values from your internal objects or incoming form submissions e.g.
 * $obj->set($values, $errors)
 *
 * Now you can concentrate on reading data from your platform's native objects
 * into and out of a very standard CommexObject.
 */

/**
 * The generic Commex Object for all contentTypes
 * At the moment there is no need to override this;
 */
final class CommexObj {

  /**
   * @var array
   */
  private $fields;

  /**
   * The unique ID of the Obj
   * @var string
   */
  public $id;
  /**
   * The unique ID of the Obj
   * @var string
   */
  //public $uri;

  /**
   * The resource plugin, or endPoint
   * @var CommexRestResourceInterface
   */
  public $resourcePlugin;

  /**
   * TRUE if the current user can view this object
   * @var bool
   */
  public $viewable;

  /**
   * TRUE if the current user can edit this object
   * @var bool
   */
  public $editable;

  /**
   * TRUE if the current user can create this object. May be set from outside.
   * @var bool
   */
  public $creatable;

  function __construct($resource_plugin) {
    $this->resourcePlugin = $resource_plugin;
    // Initiate the fields
    foreach ($resource_plugin->fields() as $name => $def) {
      $classname = commex_get_field_class($def['fieldtype']);
      $this->fields[$name] = new $classname($def, $this);
    }
  }

  /**
   * Set any given values
   */
  function set($values, &$errors = array()) {
    if (isset($values['id'])) {
      $this->id = $values['id'];
    }
    foreach ($this->getFields() as $name => $field) {
      if (array_key_exists($name, $values)) {
        try {
          $field->value = $values[$name];
        }
        catch (Exception $e) {
          commex_json_deliver(400, $e->getMessage());
        }
      }
    }
    foreach ($this->getFields() as $name => $field) {
      // If any one field is editable, this object is editable.
      if (!$this->editable && $field->editable()) {
        $this->editable = TRUE;
      }
      if ($callback = @$definition['view access']) {
        $field->viewable = $this->resourcePlugin->$callback($this);
      }
      else $field->viewable = TRUE;
      if ($field->viewable) {
        $this->viewable = TRUE;
      }
    }
    $field->deletable = FALSE;//unless changed in the resource plugin ->getObj
    return $this;
  }

  /**
   *  Convert the object into an array for sending as json
   */
  function view(array $fieldnames = array(), $expand = FALSE) {
    $show_fields = array_keys($this->getFieldDefinitions('GET', $fieldnames));
    $show_fields[] = 'id';
    if ($fieldnames) {
      $show_fields = array_intersect($fieldnames, $show_fields);
    }
    $output = array();
    foreach ($show_fields as $field_name) {
      if($field_name == 'id') {
        $output['id'] = $this->id;
      }
      else {
        $field = $this->fields[$field_name];
        if ($expand and ($field instanceOf CommexFieldReference)) {
          $output[$field_name] = $field->expand();
        }
        else {
          $output[$field_name] = $field->view();
        }
      }
    }

    if (count($show_fields) == 1) {
      return reset($output);
    }
    return $output;
  }

  /**
   * Present a definition of the object suitable for OPTIONS
   *
   * @param string $method
   *   The http method.
   * @param array|null $fieldnames
   *   The fieldnames to restrict it to
   *
   * @return array
   *   Field definitions, keyed by field name
   */
  function getFieldDefinitions($method, $fieldnames = NULL) {
    $is_form_method = in_array($method, array('POST', 'PATCH'));
    $defs = array();
    foreach ($this->fields as $name => $field) {
      if ($name == 'id' or ($fieldnames && !in_array($name, $fieldnames))) {
        continue;
      }
      if ($def = $field->getFieldDefinition($is_form_method)) {// Because virtualFields only return for GET
        $defs[$name] = $def;
      }
    }
    return $defs;
  }

  /**
   * Magic method
   */
  function __get($name) {
    $name = strtoLower($name);//TEMP
    if ($name == 'uri') {
      return $this->resourcePlugin->uri($this->id);
    }
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    if (isset($this->fields[$name])) {
      return $this->fields[$name]->value;
    }
    else {
      throw new \Exception("'$name' doesn't exist either as a field or property on Commex object.");
    }
  }

  /*
   * Get all the fields for iterating through
   *
   * @returns CommexField[]
   *   keyed by field name
   */
  public function getFields() {
    return $this->fields;
  }


}



