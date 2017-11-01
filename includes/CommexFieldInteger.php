<?php

/**
 * @file
 */

commex_require('CommexFieldNumber', TRUE);

class CommexFieldInteger extends CommexFieldNumber {

  public function __construct($definition, CommexObj $commexObj) {
    $definition['widget'] = 'number';
    parent::__construct($definition, $commexObj);
  }

  public function setValue($val) {
    parent::setValue($val);
    $val += 0;
    if (!is_integer($val)) {
      echo $val;
      throw new \Exception('Non-integer value passed to integer field '.$this->label);
    }
  }


  function __get($prop) {
    return (int)$this->{$prop};
  }

  public function getFieldDefinition($is_form_method) {
    $props = parent::getFieldDefinition($is_form_method);
    $props['type'] = 'number';
    return $props;
  }
}

