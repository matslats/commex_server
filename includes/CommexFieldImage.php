<?php

/**
 * @file
 * image upload field
 */

class CommexFieldImage extends CommexField {

  /**
   * Constructor
   */
  public function __construct($definition, CommexObj $commexObj) {
    $definition['format'] = 'image';
    $definition['widget'] = 'image';
    parent::__construct($definition, $commexObj);
  }

  public function getFieldDefinition($is_form_method) {
    if ($props = parent::getFieldDefinition($is_form_method)) {
      if (!$is_form_method) {
        $props['format'] = 'image';
      }
      return $props;
    }
  }

  /**
   * Magic method
   */
  function __get($prop) {
    return (string)$this->{$prop};
  }

  public function setValue($val) {
    $this->value = $val;
  }

}
