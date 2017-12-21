<?php

/**
 * @file
 */

class CommexFieldPassword extends CommexFieldText {

  /**
   * {@inheritdoc}
   */
  public function getFormDefinition($existing = FALSE) {
    if ($props = parent::getFormDefinition($existing)) {
      $props['widget'] = 'password';
      return $props;
    }
  }

}
