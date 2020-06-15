<?php

commex_require('proposition', FALSE);

/**
 * @file
 * Defines the offer/ commex resource
 */
class CommexOffer extends proposition {

  function fields() {
    $fields = parent::fields();
    $fields['image'] = array(
      // @todo do we need to specify what formats the platform will accept, or what sizes?
      'fieldtype' => 'CommexFieldImage',
      'label' => 'Image',
      'required' => FALSE
    );
    return $fields;
  }

}
