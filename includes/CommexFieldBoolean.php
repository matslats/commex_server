<?php

/**
 * @file
 */

class CommexFieldBoolean extends CommexField {

  private $on;
  private $off;

  public function __construct($definition, CommexObj $commexObj) {
    $definition['type'] = 'checkbox';
    $this->on = isset($definition['on']) ? $definition['on'] : 1;
    $this->off = isset($definition['off']) ? $definition['off'] : 0;
    parent::__construct($definition, $commexObj);
  }

  /**
   * {@inheritdoc}
   *
   * Should we send the raw value here and formatting in the field definition or send the formatted value here?
   */
  public function view() {
    return $this->value ? $this->on : $this->off;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDefinition($existing = FALSE) {
    if ($props = parent::getFormDefinition($existing)) {
      $props['type'] = 'checkbox';
      $props['on'] = $this->on;
      $props['off'] = $this->off;
      return $props;
    }
  }

}

