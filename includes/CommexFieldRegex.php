<?php

/**
 * @file
 */

commex_require('CommexFieldText', TRUE);

class CommexFieldRegex extends CommexFieldText {

  protected $regex;

  public function __construct($definition, CommexObj $commexObj) {
    $this->regex = $definition['regex'];
    parent:: __construct($definition, $commexObj);
  }

  public function getFieldDefinition($is_form_method) {
    $props = parent::getFieldDefinition($is_form_method);
    if ($is_form_method) {
      $props['regex'] = $this->regex;
    }
    return $props;
  }

}
