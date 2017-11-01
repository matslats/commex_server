<?php

/**
 * @file
 */

commex_require('CommexFieldRegex', TRUE);

class CommexFieldEmail extends CommexFieldRegex {

  public function __construct($definition, CommexObj $commexObj) {
    $definition['regex'] = '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$';
    parent:: __construct($definition, $commexObj);
  }

}
