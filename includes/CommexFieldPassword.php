<?php 

/**
 * @file
 */
 
class CommexFieldPassword extends CommexFieldText {

  public function getFieldDefinition($is_form_method) {
    if ($props = parent::getFieldDefinition($is_form_method)) {
      if ($is_form_method) {
        $props['widget'] = 'password';
      }
      return $props;
    }
  }

}
