<?php

/**
 * This file contains the base and fallback field object with methods to
 * populate, view and show the definition of the field. Each content type
 * (member, offer, transaction, etc) must have its fields defined. See the
 * example class.
 */

/**
 * Default field class assumes the value as stored, input and displayed are the
 * same; could be suitable for numbers and text. However this class is meant for
 * extending and contains several extensions below.
 */
abstract class CommexField implements CommexFieldInterface{

  /**
   * @var string
   */
  public $label;

  /**
   * @var bool
   */
  public $required;

  /**
   * @var mixed
   */
  protected $value;

  /**
   * @var bool
   */
  protected $sortable;

  /**
   * @var bool
   */
  protected $default_callback;

  /**
   * @var string What sort of input widget is required
   */
  public $widget;

  /**
   * @var string What sort of thing the client will receive on GET, html, uri, image
   */
  public $format;

  /**
   * @var mixed filter options or method name giving filter options
   */
  public $filter;

  /**
   * @var this callback returns TRUE if this field is editable in the current context.
   */
  public $edit_access;

  /**
   * @var Bool TRUE if the current user can view this field
   */
  public $view_access;

  /**
   * @var Bool TRUE if the current user can delete this field
   */
  public $deletable;

  /**
   * @var Bool TRUE if the current user can delete this field
   */
  protected $commexObj;

  function __construct($definition, CommexObj $commexObj) {
    $this->commexObj = $commexObj;
    $defaults = array(
      'label' => 0,
      'required' => 0,
      'widget' => '',
      'format' => 'html',
      'sortable' => 0,
      'filter' => NULL,
      'edit_access' => 'ownerOrAdmin',
      'view_access' => TRUE,
      'default_callback' => ''
    );

    foreach ($defaults as $key => $default_val) {
      $this->{$key} = isset($definition[$key]) ? $definition[$key] : $default_val;
    }
    if ($this->default_callback) {
      $this->setValue($commexObj->resourcePlugin->{$this->default_callback}());
    }
  }

  /**
   * Set the value of this field
   * @param mixed $val
   */
  public function setValue($val) {
    $this->value = $val;
  }

  /**
   * Magic method
   * Override this if validation is needed.
   */
  function __set($name, $val) {
    if ($name == 'value') {
      $this->setValue($val);
    }
    else {
      $this->{$$name} = $val;
    }
  }

  /**
   * Magic method
   */
  function __get($prop) {
    return (string)$this->{$prop};
  }

  /**
   * Get the renderable value of this field.
   *
   * @return mixed
   */
  public function view() {
    return (string)$this->value;
  }

  /**
   * Magic method used for converting the field to javascript
   */
  function __toString() {
    return $this->view();
  }

  /**
   * Get the field definition for the appropriate http method
   *
   * $is_form_method
   *   TRUE if the method is PATCH or POST FALSE if it is GET
   *
   * @return array|null
   */
  public function getFieldDefinition($is_form_method) {
    $props = array();
    if ($is_form_method) {
      // Show the field:
      //   If the object already exists and is exitable
      //   if the object doesn't exist
      //only show the widgets if this field is editable

      $props['label'] = $this->label;
      if ($this->editable()) {
        $props['type'] = $this->widget;
        $props['required'] = $this->required ?: 0;
        if ($this->value) {
          $props['default'] = $this->value;
        }
      }
    }
    elseif ($this->viewable()) {
      $props['label'] = $this->label;
      $props['format'] = $this->format;
      $props['sortable'] = $this->sortable;
      if ($this->filter) {
        $props['filter'] = $this->filter;
        if (function_exists($this->filter)){
          $props['filter'] = $props['filter']();
        }
      }
    }
    return $props;
  }

  /**
   * determines whether this field can be edited by this user
   */
  function editable() {
    // For existing objects
    if ($this->commexObj->id) {
      if ($this->edit_access) {
        if ($this->commexObj->id) {
          return $this->commexObj->resourcePlugin->{$this->edit_access}();
        }
        else {
          //There is no object, therefore this field CAN be posted
          return TRUE;
        }
      }
      else {
        // No callback means the the field is editable
        return TRUE;
      }
    }
    //For new objects
    elseif (empty($this->commexObj->id)) {
      //this field is editable if the default value has not been set
      return is_null($this->value);
    }
  }

  function viewable() {
    if (is_string($this->view_access)) {
      return call_user_func_array(
        array($this->commexObj->resourcePlugin, $this->view_access),
        array($this->commexObj->id)
      );
    }
    else {
      return $this->view_access;
    }
  }
}

