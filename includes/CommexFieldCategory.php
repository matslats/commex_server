<?php

/**
 * @file
 */
commex_require('CommexFieldEnum', TRUE);

class CommexFieldCategory extends CommexFieldEnum {

  /**
   * {@inheritdoc}
   */
  public function __construct($definition, CommexObj $commexObj) {
    commex_require('Category', FALSE);
    $definition['options'] = Category::getCategories();
    parent::__construct($definition, $commexObj);
  }

}
