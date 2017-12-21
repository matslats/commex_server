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

  /**
   * {@inheritdoc}
   */
  public function getFormDefinition($existing = FALSE) {
    if ($props = parent::getFormDefinition($existing)) {
      $props['regex'] = $this->regex;
      return $props;
    }
  }


}
