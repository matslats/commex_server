<?php

/**
 * @file
 */

class CommexFieldEnum extends CommexField {

  protected $options = array();
  protected $multiple;

  /**
   * {@inheritdoc}
   */
  public function __construct($definition, CommexObj $commexObj) {
    $definition['format'] = 'html';
    $definition['widget'] = 'select';
    $this->multiple = !empty($definition['multiple']);
    parent::__construct($definition, $commexObj);
    if (isset($definition['options'])) {
      $this->options = $definition['options'];
    }
    elseif (isset($definition['options_callback'])) {
      if (method_exists($commexObj->resourcePlugin, $definition['options_callback'])) {
        $method = $definition['options_callback'];
        $this->options = $commexObj->resourcePlugin->$method();
      }
      else  {
        throw new \Exception('options_callback method not found for field '. $definition['label'] .': '.$definition['options_callback']);
      }
    }
    if (!isset($this->options)) {
      throw new \Exception('No options or options_callback for field : '. $definition['label']);
    }
  }

  /**
   * {@inheritdoc}
   */
  function setValue($val) {
    $keys = array_map('strtolower', array_keys($this->options));
    if ($this->multiple) {
      foreach ((array)$val as $v) {
        if (!in_array(strtolower($v), $keys)) {
          throw new \Exception($this->label .' field on CommexObj '.get_class($this->commexObj->resourcePlugin).' '. $this->commexObj->id .' cannot set invalid option: '.$v.' from '.implode(', ', $keys));
        }
      }
      parent::setValue($val);
    }
    elseif (!$this->multiple && !is_array($val)){
      if (!in_array(strtolower($val), $keys)) {
        // Don't throw an error here, because we might be rendering existing
        // transactions which never had a category
//        $class = get_class($this->commexObj->resourcePlugin);
//        throw new \Exception($this->label ." field on CommexObj '$class' cannot set invalid option: '$val' from ".implode(', ', $keys));
      }
      parent::setValue($val);
    }
    else throw new \Exception('Multiple values provided for single value field');
  }


  /**
   * Get the field definition for the appropriate http method
   *
   * @return array|null
   */
  public function getFieldDefinition($is_form_method) {
    if ($props = parent::getFieldDefinition($is_form_method)) {
      if ($is_form_method) {
        $props['options'] = $this->options;
        $props['multiple'] = $this->multiple;
      }
      elseif ($this->filter) {
        $props['filter'] = $this->options;
      }
      return $props;
    }
  }

  /**
   * Get the renderable value of this field.
   *
   * @return mixed
   */
  public function view() {
    return $this->value;
  }

  public function __get($prop) {
    return $this->{$prop};
  }


}
