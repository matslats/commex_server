<?php

/**
 * Class for handling the Wants resource
 * See the base class for interface and documentation
  */
class want extends CommexRestResource {

	protected $resource = 'want';

	/**
	 * The structure of the want, not translated.
	 */
  function fields() {
    $fields = [
      'category' => [
        'fieldtype' => 'CommexFieldCategory',
        'label' => 'Want category',
        'required' => TRUE,
        'sortable' => TRUE,
        'edit_access' => 'editWantField'
      ],
      'title' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Want title',
        'required' => TRUE,
        'edit_access' => 'editWantField'
      ],
      'description' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Want description',
        'required' => TRUE,
        'edit_access' => 'editWantField'
      ],
      'keywords' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Tags',
        'edit_access' => 'editWantField'
      ],
      'expires' => [
        'fieldtype' => 'CommexFieldDate',
        'label' => 'Date expires',
        'required' => TRUE,
        'sortable' => TRUE,
        'edit_access' => 'editWantField',
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year',
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

	/**
	 * {@inheritdoc}
	 */
  public function getObj(array $vals = array()) {
    parent::getObj($vals);
    //Set the commex permissions
    $this->object->viewable = TRUE;
    $this->object->creatable = TRUE;
    $this->object->deletable = TRUE;
    return $this->object;
  }

	/**
	 * {@inheritdoc}
	 */
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
      default:
        $query .= "ORDER BY $field $dir";
    }

		$query .= " LIMIT $offset, $limit";
		//die($query);
		$db = new Db();
		$results = $db->select($query);

		foreach ($results as $row) {
			$ids[] = $row['id'];
		}
		//print_r($ids);
		//die();
		//$ids = $query->execute();
		// You must support sorting on every field where 'sortable' = TRUE
		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your offer and put all its field values into an array ready for CommexObj
		$strSql = "SELECT * FROM adverts WHERE id = '$id'";
		$db = new Db();
		$wants = $db->select($strSql);

		if (empty($wants)) {
      trigger_error("Could not find want $id", E_USER_WARNING);
      return array();
		}
		$want = reset($wants);
    return array(
			'id' => $want['id'],
			'uid' => $want['uid'],
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
		global $uid;
		$xid = substr($uid, 0, 4);
		$db = new Db();
		$result = $db->select("SELECT `nid`, `country_code` FROM `exchanges` WHERE `xid` = '$xid' LIMIT 1");
    list($nid, $country) = array_values($result[0]);
		//"INSERT INTO `adverts` (xid, nid, display_nid, uid, country, ad_type, category, title, description, added_by, date_starts, date_expires) VALUES ( '" . $strXID . "','" . $strNID . "','" . $strNID . "','" . $strUID . "','" . $strCountry . "','w','Want','" . $strTitle . "','" . $strDescr . "','mob',NOW(),DATE_ADD(CURDATE(), INTERVAL 30 DAY))";"
		//""UPDATE `adverts` SET `title` = '" . $strTitle . "',`description` = '" . $strDescr . "', `date_edited` = NOW(), `date_starts` = NOW(), `date_expires` = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE `id` = " . $intWID . "'"
		if ($obj->id) {
			$query = "UPDATE adverts SET ";
		}
		else {
			$query = "INSERT INTO adverts SET ";
      $fields[] = "ad_type = 'w'";
      $fields[] = "date_starts = NOW()";
      $fields[] =  "nid = '$nid'";
      $fields[] =  "display_nid = '$nid'";
      $fields[] =  "country = '$country'";
		}
		$fields[] =  "title = '$obj->title'";
		$fields[] =  "description = '$obj->description'";
		$fields[] =  "category = '$obj->category'";
		$fields[] =  "keywords = '$obj->keywords'";
		$fields[] =  "date_expires = '".date('Y-m-d', $obj->expires)."'";
		$fields[] =  "uid = '$obj->uid'";
		$fields[] =  "date_edited = NOW() ";
		$query .= implode(', ', $fields);
		//todo need to show the incoming pic should be managed
	//	$user->picture = array(
	//		'url' => $obj->portrait
	//		 //maybe you want to get and store the dimensions or whatever
	//	);

		if ($obj->id) {
			$query .= " WHERE id = $obj->id ";
		}
		$db = new Db();
		$db->query($query);

		if (!$obj->id) {
			$obj->id = $db->last_id();
		}
    if (!$obj->id)
      die($query);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($entity_id) {
		$db = new Db();
		$result = $db->query("DELETE FROM adverts where id = " .$entity_id);
	  return TRUE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
		$fields = parent::view($obj, $fieldnames, $expand);
		unset($fields['mail'], $fields['pass']);// Don't show these to other users.
		return $fields;
	}

  /*
   * Field access callback
   *
   * @return boolean
   *   TRUE if the current user can edit the current offer
   */
  public function editWantField(CommexObj $obj) {

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
   * Add operations for want
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
        $operations['hide'] = 'Hide this want';
      }
      else {
        $operations['show'] = 'Show this want';
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
