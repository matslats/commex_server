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
      $subdef['edit_access'] = isset($definition['edit_access']) ?: 'ownerOradmin';
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
    $result = array();
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

    $vals = array();
    foreach ($this->subFields as $subField) {
      $vals[] = $subField->view();
    }
    return $vals;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDefinition($existing = FALSE) {
    $props['label'] = $this->label;
    foreach ($this->subFields as $key => $field) {
      if ($val = $field->getFormDefinition($existing)) {
        $props['type'][$key] = $val;
      }
      $props['required'] = $this->required ?: 0;
    }
    return $props;
  }

}
