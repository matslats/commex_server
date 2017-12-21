<?php

/**
 * @file
 */

class CommexFieldReference extends CommexFieldText {

  protected $query; //deprecated
  protected $reffield;
  protected $foreignResource;

  public function __construct($definition, CommexObj $commexObj) {
    list($this->foreignResource, $this->reffield) = explode('.', $definition['reference']);
    $this->query = @$definition['query']; //deprecated
    $definition['widget'] = 'textfield';
    parent::__construct($definition, $commexObj);
  }

  /**
   * {@inheritdoc}
   */
  function view() {
    return $this->value;
  }

  function expand($deep = 0) {
    $plugin = commex_get_resource_plugin($this->foreignResource);
    $vals = $plugin->loadCommexFields($this->value);
    $obj = $plugin->getObj($vals);
    return $plugin->view($obj, array(), $deep);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDefinition($existing = FALSE) {
    if ($props = parent::getFormDefinition($existing)) {
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
      return $props;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getViewDefinition() {
    if ($props = parent::getViewDefinition()) {
      $props['format'] = 'uri';
      return $props;
    }
  }

}


