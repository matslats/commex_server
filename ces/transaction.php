<?php

/**
 * @file
 * Defines the transaction/ commex resource
 *
 */
class Transaction extends CommexRestResource {

  /**
   * The structure of the transaction, not translated.
   *
   * @var array
   */
  function fields() {
    $fields = [
      'description' => [
        'label' => 'Description',
        'fieldtype' => 'CommexFieldText',
        'required' => FALSE,
        'filter' => 'string',
        'edit_access' => 'transactionEditAccess'
      ],
      'payer' => [
        'label' => 'Buyer',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.id',
        'required' => TRUE,
        'edit_access' => 'transactionEditAccess'
      ],
      'payee' => [
        'label' => 'Seller',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.id',
        'required' => TRUE,
        'edit_access' => 'transactionEditAccess'
      ],
      'created' => [
        'label' => 'Date',
        'fieldtype' => 'CommexFieldVirtual',
        'callback' => 'transactionCreated',
        'sortable' => TRUE,
        'edit_access' => 'transactionEditAccess'
      ],
      'amount' => [
        'label' => 'Amount',
        'fieldtype' => 'CommexFieldNumber',
        'min' => 0,
        //todo dynamically adjust this according to the form direction and current user's min or max limits
        //probably need to make a new FieldHandler for this.
        'sortable' => TRUE,
        'required' => TRUE,
        'edit_access' => 'transactionEditAccess'
      ]
    ];
    // Adjust the amount field to a compound field as necessary
    return $fields;
  }

  public function getObj(array $vals = array()) {
    parent::getObj($vals);
    //Set the commex permissions
    $this->object->viewable = TRUE;
    $this->object->creatable = TRUE;//near enough
    $this->object->deletable = FALSE;
    //editable is handled field by field
    return $this->object;
  }


  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
		global $uid;
		$conditions[] = "xid = '".substr($uid, 0, 4) ."'";
    if (!empty($params['payer'])) {
      $conditions[] = "buyer = ".$params['payer'];
    }
    if (!empty($params['payee'])) {
      $conditions[] = "seller = ".$params['payee'];
    }

    // Filter by name or part-name.
    // @todo use the entity label field and put this is in the base class
    if (!empty($params['fragment'])) {
      //add a where description LIKE $params['fragment']% condition
      $conditions[] = "description LIKE '%".$params['fragment']."%' ";
    }

		$query = "SELECT txid FROM transactions  WHERE ". implode(' AND ', $conditions);

    $params += ['sort' => 'id'];
    list($field, $dir) = explode(',', $params['sort'].',DESC');
    switch ($field) {
      case 'amount':
        //add an ORDER BY amount $dir
        break;
      case 'created':
        $query.= ' ORDER BY date_entered '.$dir;
        break;
      case 'id':
        $query.= ' ORDER BY txid '.$dir;
        break;
      default:
        trigger_error('Cannot sort by transactions by field: '.$field, E_USER_ERROR);
    }

    $query .= " LIMIT $offset, $limit ";
		$db = new Db();
		$results = $db->select($query);
    $transactions = [];
    foreach ($results as $result){
      $transactions[] = $result['txid'];
    }

    return $transactions;
  }

  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $operations = parent::operations($id);
    //$transaction = transaction_load($id);
    //if the transaction is less than 2 weeks old and the current user is permitted...
    //$operations['delete'] = 'Delete';
    return $operations;
  }

  /**
   * Mark the user as absent or present
   */
  function operate($id, $operation) {
    if ($operation == 'delete') {
      //delete transaction $id and notify everybody concerned.
    }
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
		$db = new Db();
		if ($result = $db->select("SELECT * FROM transactions where txid = $id"))  {
      $transaction = $result[0];
      $fieldData = parent::loadCommexFields($id) + array(
        // Note we are giving the user ID not the wallet ID, otherwise we need to define a new REST endpoint
        'payer' => $transaction['buyer'],
        'payee' => $transaction['seller'],
        'created' => strtotime($transaction['date_entered']),
        'amount' => $transaction['seller_amount'],
        'description' => $transaction['description'],
      );
      return $fieldData;
    }
    trigger_error("Could not find transaction $id", E_USER_WARNING);
  }

  /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    global $uid;
//    if ($obj->id) {
//      $db = new Db();
//      if ($result = $db->select("SELECT * FROM transactions WHERE txid = ".$obj->id)) {
//        $transaction = (object)$result[0];
//      }
//      else trigger_error('Cannot retrieve transaction '.$obj->id, E_USER_ERROR);
//    }
//    else {
//      $transaction = new stdClass();
//    }
    if ($errors) {
      //tell the client that the transaction failed to validate
      //although in fact this is just the commexobject field validation
      header('Status: 400 Bad data');
      return $errors;
    }

    $buyer_xid = substr($obj->payer, 0, 4);
    $seller_xid = substr($obj->payee, 0, 4);
    $db = new Db();
    $results = $db->select("SELECT xid, nid, levy_rate, conversion_rate FROM exchanges WHERE xid IN ('$buyer_xid', '$seller_xid')");
    foreach ($results as $exchange) {
      $exchanges[$exchange['xid']] = $exchange;
    }
		$sngSellerConversionRate	= $exchanges[$seller_xid]['conversion_rate'];
		$sngBuyerConversionRate	= $exchanges[$buyer_xid]['conversion_rate'];
    $curSellerAmount	= round(($obj->amount*($sngSellerConversionRate/$sngBuyerConversionRate)),2);

    $db = new Db();
    if ($obj->id) {
      // NOT TESTED
      //update the transaction in the db
      $db->query("UPDATE transactions SET
        type = 'sess',
        seller = '$obj->payee',
        buyer = '$obj->payer',
        seller_nid = '".$exchanges[$seller_xid]['nid']."',
        buyer_nid = '".$exchanges[$buyer_xid]['nid']."',
        seller_amount = $obj->amount,
        buyer_amount = '$obj->amount',
        seller_levy = '".$exchanges[$seller_xid]['levy_rate']/100 * $obj->amount."',
        buyer_levy = '".$exchanges[$buyer_xid]['levy_rate']/100 * $obj->amount."',
        seller_levyrate = '".$exchanges[$seller_xid]['levy_rate']."',
        buyer_levyrate = '".$exchanges[$buyer_xid]['levy_rate']."',
        base_amount = '".round($curSellerAmount/$sngSellerConversionRate, 2)."',
        description = '$obj->description',
        date_edited = NOW(),
        who_entered = 's',
        interface = 'commex'
        WHERE txid = $obj->id
      ");
    }
    else {
      //save a new transaction in the db
      $query = "INSERT INTO transactions SET
        type = 'sess',
        seller = '$obj->payee',
        buyer = '$obj->payer',
        seller_nid = '".$exchanges[$seller_xid]['nid']."',
        buyer_nid = '".$exchanges[$buyer_xid]['nid']."',
        seller_amount = '$obj->amount',
        buyer_amount = '$obj->amount',
        seller_levy = '".$exchanges[$seller_xid]['levy_rate']/100 * $obj->amount."',
        buyer_levy = '".$exchanges[$buyer_xid]['levy_rate']/100 * $obj->amount."',
        seller_levyrate = '".$exchanges[$seller_xid]['levy_rate']."',
        buyer_levyrate = '".$exchanges[$buyer_xid]['levy_rate']."',
        base_amount = '".round($curSellerAmount/$sngSellerConversionRate, 2)."',
        description = '$obj->description',
        date_entered = NOW(),
        date_edited = NOW(),
        entered_by = '$uid',
        who_entered = 's',
        interface = 'cmx'
      ";
      $db->query($query);

      //put the new transaction id here...
      $obj->id = $db->last_id();
    }

  }

  /**
   * virtual field callback
   */
  function transactionCreated($id) {
		$db = new Db();
		if ($timestamp = $db->select1("SELECT date_entered FROM transactions where txid = ".$id)) {
      return date('d M Y', strtotime($timestamp));
    }
    else {
      trigger_error('Unable to load transaction '.$id, E_USER_ERROR);
    }
  }

  /**
   * field edit access callback
   */
  function transactionEditAccess() {
    //editing not allowed by anybody!
    return FALSE;
  }


  function delete($entity_id) {
    $db = new Db();
    $db->query("DELETE FROM transactions WHERE txid = $entity_id");
    echo 'deleted transaction';
    return TRUE;
  }


  /*
	 * {@inheritdoc}
   */
  function ownerOrAdmin() {
    global $uid;
    if ($this->object->payer == $uid or $this->object->payee == $uid) {
      return TRUE;
    }
    global $user;
    return $user['usertype'] == 'adm';
  }

}

