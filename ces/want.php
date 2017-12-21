<?php

commex_require('advert', FALSE);

/**
 * Class for handling the Wants resource
  */
class want extends advert {
	/**
	 * {@inheritdoc}
	 */
  public function getList(array $params, $offset, $limit) {
    $params['ad_type'] = 'w';
    return parent::getList($params, $offset, $limit);
	}

	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your offer and put all its field values into an array ready for CommexObj
		$db = new Db();
		$wants = $db->select("SELECT * FROM adverts WHERE id = '$id'");

		if (empty($wants)) {
      trigger_error("Could not find want $id", E_USER_WARNING);
      return array();
		}
		$want = reset($wants);
    return array(
			'id' => $want['id'],
			'user_id' => $want['uid'],
			'adtype' => $want['ad_type'],
			'oftype' => $want['offering_type'],
			'category' => $want['category'],
      'keywords' => $want['keywords'],
			'title' => $want['title'],
			'description' => $want['description'],
			'requesting' => $want['talent_rate'],
			'expires' => $want['date_expires'],
			'image' => $want['image']
		);
	}

	/**
	 * {@inheritdoc}
	 */
	function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    $obj->ad_type = 'o';
    parent::saveNativeEntity($obj, $errors);
  }

  /**
   * Add operations for want
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
        $operations['show'] = 'Show this want';
      }
      else {
        $operations['hide'] = 'Hide this want';
      }
    }
  }

  /**
   * show or hide the ad.
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
