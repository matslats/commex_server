<?php


class member extends CommexRestResource {

	protected $resource = 'member';

	/**
	 * The structure of the member, not translated.
	 */
	function fields() {
    $fields = [
      'type' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Account type',
        'required' => TRUE,
        'edit_access' => 'isAdmin'
      ],
      'name' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Given name & second name',
        'required' => TRUE,
        'sortable' => TRUE, //NB this is the only sortable field at the moment.
        'edit_access' => 'editUserField'
      ],
      'pass' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Password',
        'edit_access' => 'editUserField'
      ],
      'mail' => [
        'fieldtype' => 'CommexFieldEmail',
        'label' => 'Email',
        'required' => TRUE,
        // It is good practice to check a user controls an address before allowing changes
        'edit_access' => 'editUserField'
      ],
      'phone' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Phone',
        'edit_access' => 'editUserField',
        '_comment' => 'for validation consider https://github.com/googlei18n/libphonenumber'
      ],
      'bio' => [
        'fieldtype' => 'CommexFieldText',
        'long' => TRUE,
        'label' => 'Bio',
        'edit_access' => 'editUserField'
      ],
      'address' => [
        'fieldtype' => 'CommexFieldText',
        'long' => TRUE,
        'label' => 'Address',
        'edit_access' => 'editUserField'
      ],
      'locality' => [
        'fieldtype' => 'CommexFieldEnum',
        'label' => 'Sub area',
        'required' => TRUE,
        'options_callback' => 'getLocalityOptions',
        'edit_access' => 'editUserField'
      ],
      'image' => [
        // @todo do we need to specify what formats the platform will accept, or what sizes?
        'fieldtype' => 'CommexFieldImage',
        'label' => 'Portrait',
        'edit_access' => 'editUserField'
      ],
      'balance' => [
        'fieldtype' => 'CommexFieldVirtual',
        'label' => 'Balance',
        'callback' => 'memberBalance'//method in this class
      ],
      'offers' => [
        'fieldtype' => 'CommexFieldVirtual',
        'label' => 'Offerings',
        'callback' => 'offerCount'//method in this class
      ]
    ];
    return $fields;
  }


	/**
	 * {@inheritdoc}
	 */
	public function getList(array $params, $offset, $limit) {
		global $uid;

		$query = "SELECT uid FROM users";
		$conditions = array();

		$conditions[] = "xid = '".substr($uid, 0, 4) ."'";

		// Build a query on your entity type, using the filters passed in $params
		if (isset($params['uid'])) {
		  $conditions[] = "uid = '".$params['uid']."'";
		}
		if (isset($params['name'])) {
		  $conditions[] = ' CONCAT(firstname, " ", surname) LIKE "%'.$params['name'].'%" ';
		}
		//mail
		//locality
		//street address

    if ($conditions) {
			$query .= " WHERE ". implode(' AND ', $conditions);
		}
		//sort=name:ASC,uid:DESC
		//" ORDER BY name ASC, UID DESC "
    $params += ['sort' => 'uid,DESC'];// Default sort

    list($field, $dir) = explode(',', $params['sort'].', DESC');
    switch ($field) {
      case 'name':
        //NB the username might not always be the display name
        $query .= " ORDER BY surname $dir ";
        break;
      case 'uid':
        //NB the username might not always be the display name
        $query .= " ORDER BY uid $dir ";
        break;
      default:
        trigger_error('Cannot sort by members by field: '.$field, E_USER_ERROR);
    }

		$query .= " LIMIT $offset, $limit";

		$db = new Db();
		$results = $db->select($query);

		foreach ($results as $row) {
			$ids[] = $row['uid'];
		}
		//$ids = $query->execute();
		// You must support sorting on every field where 'sortable' = TRUE
		return $ids;
	}

  public function getObj(array $vals = array()) {
    parent::getObj($vals);
    //Set the commex permissions
    $this->object->viewable = TRUE;
    $this->object->creatable = FALSE;
    $this->object->deletable = FALSE;
    //editable is handled field by field
    return $this->object;
  }

	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your member and put all its field values into an $arrayName = array('',);
		$db = new Db();
		$users = $db->select("SELECT * FROM users WHERE uid = '$id'");
		if (empty($users)) {
      trigger_error("Could not find user $id", E_USER_WARNING);
      return array();
		}
		$user = reset($users);
		$usertype = $user['usertype'];

		if ($usertype == "com" || $usertype == "org" || $usertype == "pub") {
			$name = $user['orgname_long'];
		} else {
			$name = $user['firstname'] . ' ' . $user['surname'];
		}

		$address = $user['address1'] . "<br />" . $user['address2'] . "<br />" . $user['address3'] . "<br />" . $user['postcode'];

		$values = array(
			'id' => $user['uid'],
			'uid' => $user['uid'],
			'type' => $user['usertype'],
			'pass' => $user['passwd'],
			'name' => $name,
			'aboutme' => $user['bio'],
			'address' => $address,
			'mail' => $user['email'],
			'phone' => $user['phone_m'],
			'locality' => $user['subarea'],
			'image' => $user['picture']
		);
		return $values;
	}

    protected function translateToEntity(CommexObj $obj, ContentEntityInterface $user) {
    parent::translateToEntity($obj, $user);//this will save any pics
    if ($user->isNew()) {
      $user->status = \Drupal::currentUser()->hasPermission('administer users')
          || \Drupal::Config('user.settings')->get('register') == USER_REGISTER_VISITORS;
      $user->init->value = $obj->mail;
      $user->setPassword($obj->pass);
    }
    $user->setUsername($obj->name);
    $user->setEmail($obj->mail);
    $countries = \Drupal\field\Entity\FieldConfig::load('user.user.address')->get('settings')['available_countries'];
    $lastspace = strrpos($obj->name, ' ');
    $user->address->setValue([
      'given_name' => substr($obj->name, 0, $lastspace),
      'family_name' => substr($obj->name, $lastspace + 1),
      'address_line1' => $obj->street_address,
      'dependent_locality' => $obj->locality,
      'country_code' => reset($countries)
    ]);
    $user->phones->setValue($obj->phone);
    $user->notes->setValue($obj->aboutme);
    //TODO
    //$account->portrait->setValue($params['image']);
  }

	/**
	 * {@inheritdoc}
	 */
	function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    parent::translateToEntity($obj);//this will save any pics
		//"insert into users set field1 = a, field2 = b)"
		//"update users (field1 = a, field2 = b) where uid = ctte001"
		if ($obj->id) {
			$query = "UPDATE users SET ";
		}
		else {
			$query = "INSERT INTO users SET ";
		}
		$fields[] =  "type = '$obj->type'";
		@list($firstname, $lastname) = explode(' ', $obj->name);

		$fields[] =  "firstname = '$firstname'";
		$fields[] =  "surname = '$lastname'";
		$fields[] =  "mail = '$obj->mail'";
		$fields[] =  "phone = '$obj->phone'";
		$fields[] =  "bio = '$obj->bio'";
		@list($line1, $line2, $line3) = explode('<br />', $obj->address);
		if ($line1) {
			$fields[] =  "address1 = '$line1'";
		}
		if ($line2) {
			$fields[] =  "address2 = '$line2'";
		}
		if ($line3) {
			$fields[] =  "address3 = '$line3'";
		}
		$fields[] =  "locality = '$obj->locality'";

		$query .= implode(', ', $fields);

		/**
     * @todo The pic is saved on the server, now save $obj->picture reference.
     */

		if ($obj->id) {
			$query .= " WHERE uid = $obj->id ";
		}

		$db = new Db();
		$id = $db->query($query);
		if (!$obj->id) {
			$obj->id = $id;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
		$fields = parent::view($obj, $fieldnames, $expand);
    if (is_array($fields)) {
  		unset($fields['mail'], $fields['pass']);// Don't show these to other users.
    }
	 	return $fields;
	}

  function offerCount() {
    global $uid;
    $db = new Db();
    return $db->select1("SELECT COUNT(*) FROM adverts WHERE ad_type = 'o' AND date_expires >= CURDATE() AND NOT hide AND uid = '$uid'");
  }

  function getLocalityOptions() {
    global $uid;
    $xid = substr($uid, 0, 4);
    $db = new Db();
    $result = $db->select("SELECT `sub_area` FROM `subareas` WHERE `xid` = '$xid' AND `active`");

    // matslats HACK to ensure that all subareas are on the list.
    $result = array_merge($result, $db->select("SELECT DISTINCT subarea as sub_area FROM `users`"));

    $subareas = array("" => "");
    foreach ($result as $row) {
      $subareas[$row['sub_area']] = $row['sub_area'];
    }
    return $subareas;
  }

  function memberBalance($uid) {
    $db = new Db();
    $balance = $db->select1("SELECT `balance` FROM `balances` WHERE `uid` = '$uid'");
    return $balance;
  }

  /*
   * Field access callback
   *
   * @return boolean
   *   TRUE if the current user is superadmin or the commexObj is the currenct user
   */
  function editUserField(CommexObj $obj) {

  }

  /*
   * Field access callback
   *
   * @return boolean
   *   TRUE if the current user is superadmin or admin of the current group
   */
  public function isAdmin() {
    global $user;
    return $user['usertype'] == 'adm';
  }

  /**
   * Add operations for user
   *
   * TODO TIM
   */
  function operations($id) {
    global $uid;
    if ($this->isAdmin() or $id == $uid) {
      $db = new Db();
      $isPresent = $db->select("SELECT present FROM users WHERE uid = '$uid'");

      if ($isPresent) {
        $operations['absent'] = 'Going on holiday';
      }
      else {
        $operations['present'] = 'Back from holiday';
      }
    }
  }

  function operate($id, $operation) {
    switch ($operation) {
      case 'absent':
        db_query('UPDATE users set present = 0 where id = '.$id);
        break;
      case 'present':
        db_query('UPDATE users set present = 1 where id = '.$id);
        break;
    }
  }
}
