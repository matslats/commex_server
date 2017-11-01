<?php

/**
 * @file
 */

class CompoundCommexField extends CommexField {

  private $subFields = array();

  /**
   * Constructor.
   */
  function __construct($definition, CommexObj $commexObj) {
    parent::__construct($definition, $commexObj);
    foreach ($definition['fieldtype'] as $subdef) {
      $classname = commex_get_field_class($subdef['fieldtype']);
      $this->subFields[] = new $classname($subdef, $commexObj);
    }
  }

  /**
   * {@inheritdoc}
   */
  function setValue($val) {
    if (!is_array($val)) {
      $datatype = gettype($val);
      throw new \Exception("Compound field $this->label requires an array value, but received $datatype");
    }
    foreach (array_values($val) as $delta => $v) {
      $this->subFields[$delta]->value = $v;
    }
  }

  /**
   * Return an array of values.
   */
  function __get($prop) {
    $result = [];
    foreach ($this->subFields as $subfield) {
      $result[] = $subfield->value;
    }
    return $result;
  }

  /**
   * Conjoin the subfields with commas.
   */
  function __toString() {
    return implode(',', $this->view());
  }

  /**
   * Concatenate the subfields.
   */
  public function view() {
    $vals = [];
    foreach ($this->subFields as $subField) {
      $vals[] = $subField->view();
    }
    return implode(' ', $vals);
  }


  /**
   * Get the field definition for the appropriate http method.
   *
   * @return array|null
   */
  public function getFieldDefinition($is_form_method) {
    $def['label'] = $this->label;
    if ($is_form_method) {
      foreach ($this->subFields as $key => $field) {
        if ($val = $field->getFieldDefinition(TRUE)) {
          $def['type'][$key] = $val;
        }
      }
      $def['required'] = $this->required ?: 0;
    }
    else {
      foreach ($this->subFields as $key => $field) {
        //$def['format'][$key] = $field->getFieldDefinition('GET');
        //unset($def['format'][$key]['sortable']);
      }
      $def['format'] = 'html';
    }
    return $def;
  }

}
