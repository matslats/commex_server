<?php

/**
 * Class for handling the Announcements resource
 */
class CommexAnnouncement extends CommexRestResource {


	/**
	 * The structure of the offer, not translated.
	 */
  function fields() {
    $fields = [
      'title' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering title',
        'required' => TRUE
      ],
      'description' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering description',
        'lines' => 6,
        'required' => TRUE
      ],
      'image' => [
        'fieldtype' => 'CommexFieldImage',
        'label' => 'Image'
      ],
      'date_event' => [
        'fieldtype' => 'CommexFieldDate',
        'label' => 'Date expires',
        'required' => TRUE,
        'sortable' => TRUE,  //this is the only sortable field
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year'
      ],
      'user_id' => [
        'label' => 'Posted by',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.id',
        'default_callback' => 'currentUserId',
        'required' => FALSE,
        'sortable' => FALSE,
        'edit_access' => 'ownerOrAdmin'
      ]
    ];
    return $fields;
  }

	/**
	 * {@inheritdoc}
	 */
  public function getList(array $params, $offset, $limit) {
    global $uid, $user;
		// @todo just select id
		$query = "SELECT * FROM announcements";
		$conditions = array();
    //Assume the search is only in the user's own exchange
		$conditions[] = "xid = '".substr($uid, 0, 4) ."'";

		// Build a query on your entity type, using the filters passed in $params
		if (isset($params['user_id'])) {
		  $conditions[] = "uid = '".$params['user_id']."'";
      if ($params['user_id'] != $uid) {
        $conditions[] = 'hidden = 0';
      }
		}
    elseif ($user['usertype'] <> 'adm') {
      //hide the 'hidden' ads if we're not filtering by a specific user
      $conditions[] = 'hidden = 0';
    }
		if (isset($params['fragment'])) {
		  $conditions[] = "(title LIKE '%".$params['fragment']."%' OR description LIKE '%".$params['fragment']."%')";
    }

    // TODO put everything below here in a new function in the parent
		$query .= " WHERE ". implode(' AND ', $conditions);

    if (empty($params['sort'])) {
      $params['sort'] = 'date_event,DESC';
    }
    list($field, $dir) = explode(',', $params['sort']);
    $dir  = strtoupper($dir);
    switch ($field) {
      case 'date_event':
        $field = 'date_expires';
        break;
    }
    $query .= " ORDER BY $field $dir LIMIT $offset, $limit ";
    $ids =  array();
		$db = new CommexDb();
		foreach ($db->select($query) as $row) {
			$ids[] = $row['id'];
		}
		return $ids;
	}


	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your announcement and put all its field values into an array ready for CommexObj
		$db = new CommexDb();
		$wants = $db->select("SELECT * FROM announcements WHERE id = '$id'");

		if (empty($wants)) {
      trigger_error("Could not find want $id", E_USER_WARNING);
      return array();
		}
		$want = reset($wants);
    return array(
			'id' => $want['id'],
			'title' => $want['title'],
			'description' => $want['description'],
			'image' => $want['image'],
			'date_event' => $want['date_event'],
      'user_id' => $want['user_id']
		);
	}


	/**
	 * {@inheritdoc}
	 */
	function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    global $uid;
		$xid = substr($uid, 0, 4);
		if ($obj->id) {
			$query = "UPDATE announcements SET ";
		}
		else {
			$query = "INSERT INTO announcements SET ";
      $fields[] = "xid = '$xid'";
      $fields[] = "date_added = NOW()";
      $fields[] = "date_edited = NOW()";
      $fields[] = "hidden = 0";
		}
		$fields[] = "title = '$obj->title'";
		$fields[] = "description = '$obj->description'";
    $fields[] = "image = '$nid'";
    $fields[] = "uid = '$obj->user_id'";
    $fields[] = "date_event = '$obj->date_event'";
    $fields[] = "date_expires = '$obj->date_event'";

		$query .= implode(', ', $fields);
		//todo need to show the incoming pic should be managed
	//	$user->picture = array(
	//		'url' => $obj->portrait
	//		 //maybe you want to get and store the dimensions or whatever
	//	);
		if ($obj->id) {
			$query .= " WHERE id = '$obj->id' ";
		}
		$db = new CommexDb();
		$db->query($query);
		if (!$obj->id) {
			$obj->id = $db->last_id();
		}
	}

  /**
   *{@inheritdoc}
   */
  public function ownerOrAdmin() {
    global $uid;
    if ($this->object->id == $uid) {
      return TRUE;
    }
    return substr($uid, 4) == '0000';
  }

  function delete($entity_id) {
    $db = new CommexDb();
    $db->query("DELETE FROM announcements WHERE id = '$entity_id'");
    return TRUE;
  }
  public function currentUserId() {
    global $uid;
    return $uid;
  }
}
