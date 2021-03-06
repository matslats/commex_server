<?php

/**
 * @file
 * A field type for display only
 */

class CommexFieldVirtual extends CommexField {

  protected $callback;

  public function __construct($definition, CommexObj $commexObj) {

    if (method_exists($commexObj->resourcePlugin, $definition['callback'])) {
      $this->callback = $definition['callback'];
    }
    else {
      throw new \Exception('Could not find plugin method for virtual field: '.$definition['callback']);
    }
    $definition['format'] = 'html';
    parent::__construct($definition, $commexObj);
  }
  /**
   * {@inheritdoc}
   */
  function structure($method) {
    if ($method == 'GET' or $method == 'HEAD') {
      return array(
        'type' => 'textfield'
      );
    }
  }
  function __get($prop) {
    if ($prop == 'value') {
      return (string)$this->view();
    }
  }

  function view() {
    $callback = $this->callback;
    return $this->commexObj->resourcePlugin->$callback($this->commexObj->id);
  }

  /**
   * Determines whether this field can be edited by this user.
   */
  function editable($existing = FALSE) {
    return FALSE;
  }


}

