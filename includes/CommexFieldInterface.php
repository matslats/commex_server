<?php


interface CommexFieldInterface {

  //const API_BASE_INPUT_TYPES = array(
  //  'textfield',
  //  'textarea',
  //  'password',
  //  'tel',
  //  'image',
  //  'date',
  //  'checkbox',
  //  'radios',
  //  'select'
  //);

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
   * Get definitions of fields suitable for populating forms
   *
   * @param bool $existing
   *   TRUE if this an update form, FALSE for a new object
   *
   * @return array
   */
  public function getFormDefinition($existing = FALSE);
  
  /**
   * Get definitions of fields suitable for display functions
   *
   * @return array
   */
  public function getViewDefinition();
}
