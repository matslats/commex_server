<?php

commex_require('advert', FALSE);

/**
 * Class for handling the offerings resource
  */
class offer extends advert {

	/**
	 * The structure of the offer, not translated.
	 */
  function fields() {
    $fields = parent::fields();
    $fields['image'] = [
      // @todo do we need to specify what formats the platform will accept, or what sizes?
      'fieldtype' => 'CommexFieldImage',
      'label' => 'Picture'
    ];
    $fields['requesting'] = [
      'fieldtype' => 'CommexFieldText',
      'label' => 'Requesting'
    ];
    return $fields;
  }

	/**
	 * {@inheritdoc}
	 */
  public function getList(array $params, $offset, $limit) {
    $params['ad_type'] = 'o';
    return parent::getList($params, $offset, $limit);
	}

	/**
	 * {@inheritdoc}
	 */
	function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    $obj->ad_type = 'o';
    parent::saveNativeEntity($obj, $errors);
  }

  /**
   * Add operations for offer
   *
   * A way to hide or show the ad with one button.
   */
  function operations($id) {
    global $uid;
    $operations = array();
    if ($this->ownerOrAdmin()) {
      $db = new Db();
      $hidden = $db->select1("SELECT hide from adverts WHERE id = '$id'");
      if ($hidden) {
        $operations['show'] = 'Show this offer';
      }
      else {
        $operations['hide'] = 'Hide this offer';
      }
    }
    return $operations;
  }

  /**
   * Show or hide the ad.
   *
   * @param string ID
   *   The id of the ad to operate on.
   * @param string $operation
   *   A key in the result of operations i.e. show, hide
   */
  function operate($id, $operation) {
    $db = new Db();
    switch ($operation) {
      case 'show':
        $db->query("UPDATE adverts SET hide = 0 WHERE id = '$id'");
        break;
      case 'hide':
        $db->query("UPDATE adverts SET hide = 1 WHERE id = '$id'");
        break;
    }
  }


}