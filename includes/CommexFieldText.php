<?php

/**
 * @file
 */

class CommexFieldText extends CommexField {

  private $lines;

  public function __construct($definition, CommexObj $commexObj) {
    $this->lines = isset($definition['lines']) ? $definition['lines'] : 1;
    $definition['widget'] = $this->lines  > 1 ? 'textarea' : 'textfield';
    $definition['format'] = 'html';
    unset($definition['long']);
    parent:: __construct($definition, $commexObj);
  }

  public function getFieldDefinition($is_form_method) {
    $props = parent::getFieldDefinition($is_form_method);
    if ($is_form_method && isset($props['type']) and $props['type'] == 'textarea') {
      $props['lines'] = $this->lines;
    }
    return $props;
  }

}
