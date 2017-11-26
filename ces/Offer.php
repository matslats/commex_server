<?php

/**
 * Example class for handling the offerings resource
 * See the base class for interface and documentation
  */
class offer extends CommexRestResource {

	protected $resource = 'offer';

	/**
	 * The structure of the offer, not translated.
	 */
  function fields() {
    $fields = [
      'category' => [
        'fieldtype' => 'CommexFieldCategory',
        'label' => 'Offering category',
        'required' => TRUE,
        'sortable' => TRUE,
        'edit_access' => 'editOfferField'
      ],
      'title' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering title',
        'required' => TRUE,
        'edit_access' => 'editOfferField'
      ],
      'description' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering description',
        'required' => TRUE,
        'edit_access' => 'editOfferField'
      ],
      'requesting' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Requesting',
        'edit_access' => 'editOfferField',
      ],
      'keywords' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Tags',
        'edit_access' => 'editOfferField'
      ],
      'expires' => [
        'fieldtype' => 'CommexFieldDate',
        'label' => 'Date expires',
        'required' => TRUE,
        'sortable' => TRUE,
        'edit_access' => 'editOfferField',
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year'
      ],
      'uid' => [
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.id',
        'label' => 'Advertiser',
        'required' => TRUE,
        'sortable' => TRUE,
        'edit_access' => 'isAdmin'
      ],
      'image' => [
        // @todo do we need to specify what formats the platform will accept, or what sizes?
        'fieldtype' => 'CommexFieldImage',
        'label' => 'Picture',
        'edit_access' => 'editOfferField',
      ],
    ];
    return $fields;
  }

  public function getObj(array $vals = array()) {
    parent::getObj($vals);
    //Set the commex permissions
    $this->object->viewable = TRUE;
    $this->object->creatable = TRUE;
    $this->object->deletable = TRUE;
    return $this->object;
  }

  public function getList(array $params, $offset, $limit) {
		global $uid;
		$query = "SELECT * FROM adverts";
		$conditions = array();
    //Assume the search is only in the user's own exchange
		$conditions[] = "xid = '".substr($uid, 0, 4) ."'";
    $conditions[] = "ad_type = 'w'";

		// Build a query on your entity type, using the filters passed in $params
		if (isset($params['uid'])) {
		  $conditions[] = "uid = '".$params['uid']."'";
		}
		if (isset($params['category'])) {
		  $conditions[] = "category = '".$params['category']."'";
    }
		if (isset($params['title'])) {
		  $conditions[] = "title = '".$params['title']."'";
    }
		if (isset($params['keywords'])) {
		  $conditions[] = "keywords LIKE '%".$params['keywords']."%'";
    }
		if (isset($params['fragment'])) {
		  $conditions[] = "(title LIKE '%".$params['fragment']."%' OR description LIKE '%".$params['fragment']."%' OR category like LIKE '%".$params['fragment']."%' OR keywords LIKE '%".$params['fragment']."%' )";
    }

		$query .= " WHERE ". implode(' AND ', $conditions);


		//sort=name:ASC,uid:DESC
    //translates to
		//" ORDER BY name ASC, UID DESC "
    if (empty($params['sort'])) {
      $params['sort'] = 'expires,DESC';
    }
    list($field, $dir) = explode(',', $params['sort']);
    $dir  = strtoupper($dir);
    switch ($field) {
      case 'category':
        //need to do something with the category name
        break;

      case 'uid':
        //need to do something with the advertiser name
        break;

      // these translate directly to the db table.
      case 'expires':
        $field = 'date_expires';
      case 'starts':
      case 'id':
        $query .= " ORDER BY $field $dir ";
    }
		$query .= " LIMIT $offset, $limit";
		//die($query);
		$db = new Db();
		$results = $db->select($query);

		foreach ($results as $row) {
			$ids[] = $row['id'];
		}
		// You must support sorting on every field where 'sortable' = TRUE
		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your offer and put all its field values into an array ready for CommexObj
		$db = new Db();
		$offers = $db->select("SELECT * FROM adverts WHERE id = '$id'");

		if (empty($offers)) {
      trigger_error("Could not find offer $id", E_USER_WARNING);
      return array();
		}

		$offer = reset($offers);
		return array(
			'id' => $offer['id'],
			'uid' => $offer['uid'],
			'adtype' => $offer['ad_type'],
			'oftype' => $offer['offering_type'],
			'category' => $offer['category'],
			'keywords' => $offer['keywords'],
			'title' => $offer['title'],
			'description' => $offer['description'],
			'requesting' => $offer['talent_rate'],
			'expires' => $offer['date_expires'],
			'image' => $offer['image']
		);

	}

	/**
	 * {@inheritdoc}
	 */
	function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    global $uid;
		$xid = substr($uid, 0, 4);
		$db = new Db();
		$result = $db->select("SELECT `nid`, `country_code` FROM `exchanges` WHERE `xid` = '$xid' LIMIT 1");
    list($nid, $country) = array_values($result[0]);
		//"insert into users set field1 = a, field2 = b)"
		//"update users (field1 = a, field2 = b) where uid = ctte001"
		if ($obj->id) {
			$query = "UPDATE adverts SET ";
		}
		else {
			$query = "INSERT INTO adverts SET ";
      $fields[] = "ad_type = 'o'";
      $fields[] = "date_starts = NOW()";
      $fields[] =  "nid = '$nid'";
      $fields[] =  "display_nid = '$nid'";
      $fields[] =  "country = '$country'";
		}
    $fields[] =  "category = '$obj->category'"; //TODO categories have names
		$fields[] =  "title = '$obj->title'";
		$fields[] =  "description = '$obj->description'";
		$fields[] =  "date_expires = '".date('Y-m-d', $obj->expires)."'";
		$fields[] =  "uid = '$obj->uid'";
		$fields[] =  "date_edited = NOW()";
		$fields[] =  "keywords = '$obj->keywords'";
		$query .= implode(', ', $fields);
		//todo need to show the incoming pic should be managed
	//	$user->picture = array(
	//		'url' => $obj->portrait
	//		 //maybe you want to get and store the dimensions or whatever
	//	);
		if ($obj->id) {
			$query .= " WHERE id = '$obj->id' ";
		}
		$db = new Db();
		$db->query($query);
		if (!$obj->id) {
			$obj->id = $db->last_id();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
		$fields = parent::view($obj, $fieldnames, $expand);
		unset($fields['mail'], $fields['pass']);// Don't show these to other users.
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($entity_id) {
		$db = new Db();
		$result = $db->query("DELETE FROM adverts where id = " .$entity_id);
    return TRUE;
	}


  /*
   * Field access callback
   *
   * @return boolean
   *   TRUE if the current user can edit the current offer
   */
  public function editOfferField(CommexObj $obj) {

  }

  /*
   * Field access callback
   *
   * @return boolean
   *   TRUE if the current user is superadmin or admin of the current group
   */
  public function isAdmin(CommexObj $obj) {

  }


  /**
   * Add operations for offer
   *
   * A way to hide or show the ad with one button.
   *
   * TODO TIM
   */
  function operations($id) {
    global $uid;
    if ($this->object->uid == $uid or $this->isAdmin($this->object)) { //TEST  THIS
      $db = new Db();
      $hidden = $db->select();

      if ($isPresent) {
        $operations['hide'] = 'Hide this offer';
      }
      else {
        $operations['show'] = 'Show this offer';
      }
    }
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
