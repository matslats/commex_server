<?php

/**
 * @file
 * Floating point numbers
 */

class CommexFieldNumber extends CommexField {

  /**
   * Note that we don't test the min and max for being integers because the date
   * field uses them differently
   */
  private $min;
  private $max;

  public function __construct($definition, CommexObj $commexObj) {
    $this->min = isset($definition['min']) ? $definition['min'] : 0;
    if (isset($definition['max'])) {
      $this->max = $definition['max'];
    }
    $definition += array(
      //this is for a floating point number!
      'widget' => 'textfield'
    );
    parent::__construct($definition, $commexObj);
  }

  public function setValue($val) {
    if (!is_numeric($val)) {
      throw new \Exception(gettype($val) .' is not a number in field '.$this->label);
    }
    // Convert the type into a number
    $val += 0;
    parent::setValue($val);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDefinition($existing = FALSE) {
    if ($props = parent::getFormDefinition($existing)) {
      $props['min'] = $this->min;
      if ($this->max) {
        $props['max'] = $this->max;
      }
      return $props;
    }
  }

}

