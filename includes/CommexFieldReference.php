<?php

/**
 * @file
 */

class CommexFieldReference extends CommexFieldText {

  protected $query; //deprecated
  protected $reffield;
  protected $foreignResource;

  public function __construct($definition, CommexObj $commexObj) {
    $this->foreignResource = $definition['resource'];
    $this->query = @$definition['query']; //deprecated
    $this->reffield = $definition['reffield'];
    $definition['widget'] = 'textfield';
    parent::__construct($definition, $commexObj);
  }

  /**
   * {@inheritdoc}
   */
  function view() {
    return $this->value;
  }

  function expand() {
    $plugin = commex_get_resource_plugin($this->foreignResource);
    $vals = $plugin->loadCommexFields($this->value);
    $obj = $plugin->getObj($vals);
    return $plugin->view($obj, [], 1);
  }

  public function getFieldDefinition($is_form_method) {
    if ($props = parent::getFieldDefinition($is_form_method)) {
      if ($is_form_method) {
        $props['type'] = 'textfield';
        $props['resource'] = $this->foreignResource;
        $props['autocomplete'] = 'fields='.$this->reffield.'&fragment=';
        if (isset($props['default'])) {
          $plugin = commex_get_resource_plugin($this->foreignResource);
          $vals = $plugin->loadCommexFields($this->value);
          $obj = $plugin->getObj($vals);
          // Look up the default value label from its id.
          $props['default'] = $obj->{$this->reffield};
        }
      }
      else {
        $props['format'] = 'uri';
      }
      return $props;
    }
  }

}

