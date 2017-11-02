<?php


interface CommexFieldInterface {

  const API_BASE_INPUT_TYPES = array(
    'textfield',
    'textarea',
    'password',
    'tel',
    'image',
    'date',
    'checkbox',
    'radios',
    'select'
  );

  /**
   * Store the value in the field
   * @param mixed $val
   */
  public function setValue($val);

  /**
   * return the value which will be rendered straight to HTML
   * @return mixed
   */
  public function view();


  /**
   * Get the field definition for the appropriate http method
   *
   * $is_form_method
   *   TRUE if the method is PATCH or POST FALSE if it is GET
   *
   * @return array|null
   */
  public function getFieldDefinition($is_form_method);

}
