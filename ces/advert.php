<?php

/**
 * Class for handling the offerings resource
  */
class advert extends CommexRestResource {

	/**
	 * The structure of the offer, not translated.
	 */
  function fields() {
    $fields = [
      'category' => [
        'fieldtype' => 'CommexFieldCategory',
        'label' => 'Offering category',
        'required' => TRUE,
        'sortable' => TRUE
      ],
      'title' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering title',
        'required' => TRUE
      ],
      'description' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering description',
        'required' => TRUE
      ],
      'keywords' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Tags'
      ],
      'expires' => [
        'fieldtype' => 'CommexFieldDate',
        'label' => 'Date expires',
        'required' => TRUE,
        'sortable' => TRUE,
        'default_callback' => 'defaultExpires',
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year'
      ],
      'uid' => [
        'label' => 'Advertiser',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.id',
        'default_callback' => 'currentUserId',
        'required' => FALSE,
        'sortable' => TRUE,
        'edit_access' => 'isAdmin'
      ]
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
    $this->object->deletable = $this->ownerOrAdmin();
    return $this->object;
  }

	/**
	 * {@inheritdoc}
	 */
  public function getList(array $params, $offset, $limit) {
		global $uid;
		// @todo just select id
		$query = "SELECT * FROM adverts";
		$conditions = array();
    //Assume the search is only in the user's own exchange
		$conditions[] = "xid = '".substr($uid, 0, 4) ."'";
    $ad_type = $params['ad_type'];
    $conditions[] = "ad_type = '$ad_type'";

		// Build a query on your entity type, using the filters passed in $params
		if (isset($params['uid'])) {
		  $conditions[] = "uid = '".$params['uid']."'";
		}
    elseif (!$this->ownerOrAdmin()) {
      //hide the 'hidden' ads if we're not filtering by a specific user
      $conditions[] = 'hide = 0';
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

		/*
     * We must support sorting on every field where 'sortable' = TRUE
     * $params[sort]=name:ASC,uid:DESC
     * translates to
     * " ORDER BY name ASC, UID DESC "
     */
    if (empty($params['sort'])) {
      $params['sort'] = 'expires,DESC';
    }
    list($field, $dir) = explode(',', $params['sort']);
    $dir  = strtoupper($dir);
    switch ($field) {
      case 'category':
      case 'uid':
        break;
      case 'expires':
        $field = 'date_expires';
        break;
    }
    $query .= " ORDER BY $field $dir LIMIT $offset, $limit ";
		$db = new Db();
    $ids =  array();
		foreach ($db->select($query) as $row) {
			$ids[] = $row['id'];
		}
		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your offer and put all its field values into an array ready for CommexObj
		$db = new Db();
		$ads = $db->select("SELECT * FROM adverts WHERE id = '$id'");

		if (empty($ads)) {
      trigger_error("Could not find offer $id", E_USER_WARNING);
      return array();
		}
		$ad = reset($ads);
		return array(
			'id' => $ad['id'],
			'uid' => $ad['uid'],
			'adtype' => $ad['ad_type'],
			'oftype' => $ad['offering_type'],
			'category' => $ad['category'],
			'keywords' => $ad['keywords'],
			'title' => $ad['title'],
			'description' => $ad['description'],
			'requesting' => $ad['talent_rate'],
			'expires' => $ad['date_expires'],
			'image' => $ad['image']
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
      $fields[] = "ad_type = '$obj->ad_type'";
      $fields[] = "date_starts = NOW()";
      $fields[] =  "nid = '$nid'";
      $fields[] =  "display_nid = '$nid'";
      $fields[] =  "country = '$country'";
		}
		$fields[] =  "title = '$obj->title'";
		$fields[] =  "description = '$obj->description'";
    $fields[] =  "category = '$obj->category'"; //TODO categories have names
		$fields[] =  "keywords = '$obj->keywords'";
		$fields[] =  "date_expires = '".date('Y-m-d', $obj->expires)."'";
		$fields[] =  "uid = '$obj->uid'";
		$fields[] =  "date_edited = NOW()";
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
    foreach ($this->fields() as $fname => $def) {
      if ($def['fieldtype'] == 'CommexFieldImage') {
        // Always renders a thumbnail
        if ($img_id = $obj->{$fname}) {
          $fields[$fname] = 'https://community-exchange.org/pics/'.$img_id;
        }
      }
    }
    return $fields;
  }


  /*
	 * {@inheritdoc}
   */
  public function ownerOrAdmin() {
    global $uid;
    if ($this->object->uid == $uid) {
      return TRUE;
    }
    return $this->isAdmin();
  }

  /*
   * Custom field access callback
   *
   * @return boolean
   *   TRUE if the current user is superadmin or admin of the current group
   */
  public function isAdmin() {
    global $user;
    return $user['usertype'] == 'adm';
  }

	/**
	 * {@inheritdoc}
	 */
  protected function getAttachedFilename($fieldname = NULL) {
    global $uid;
    return $uid . time();
  }

  /**
   * Default field callback
   */
  public function currentUserId() {
    global $uid;
    return $uid;
  }

  /**
   * Default field callback
   */
  public function defaultExpires() {
    return strtotime('+1 year');
  }


}
