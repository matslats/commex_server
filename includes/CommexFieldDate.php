<?php

/**
 * @file
 * for systems which don't store dates as unixtime
 */

commex_require('CommexFieldInteger', TRUE);

class CommexFieldDate extends CommexFieldInteger {

  private $min;
  private $max;

  public function __construct($definition, CommexObj $commexObj) {
    $this->min = isset($definition['min']) ? $definition['min'] : 0;
    if (isset($definition['max'])) {
      $this->max = $definition['max'];
    }
    $definition['format'] = 'html';
    parent::__construct($definition, $commexObj);
  }

  public function view() {
    // The API specified unixtime dates so this not needed.
    return date('d-M-Y', $this->value);
  }
  
  public function setValue($value) {
    if (!is_numeric($value)) {
      $val = strtotime($value);
      if (empty($val)) {
        throw new \Exception('Php strtotime unable to parse date: '.$value);
      }
      $value = $val;
    }
    parent::setValue($value);
  }

  public function getFieldDefinition($is_form_method) {
    if ($props = parent::getFieldDefinition($is_form_method)) {
      if ($is_form_method) {
        $props['type'] = 'date';
        $props['min'] = $this->min;
        if ($this->max) {
          $props['max'] = $this->max;
        }
      }
      return $props;
    }
  }

}

