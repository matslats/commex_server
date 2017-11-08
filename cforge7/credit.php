<?php

module_load_include('inc', 'mcapi');

/**
 * @file
 * Defines the credit/ commex resource
 * The classname transaction is already in use in the mutual credit module
 *
 */
class credit extends CommexRestResource {

  protected $resource = 'transaction';
  protected $entityTypeId = 'transaction';

  /**
   * The structure of the transaction, not translated.
   *
   * @var array
   */
  function fields() {
    $fields = array(
      'description' => array(
        'label' => 'Description',
        'fieldtype' => 'CommexFieldText',
        'required' => FALSE,
        'filter' => 'string',
        'edit access' => 'transactionEditAccess'
      ),
      'payer' => array(
        'label' => 'Payer',
        'fieldtype' => 'CommexFieldReference',
        'required' => TRUE,
        'resource' => 'member',
        'query' => 'fields=name&fragment=',
        'edit access' => 'transactionEditAccess'
      ),
      'payee' => array(
        'label' => 'Payee',
        'fieldtype' => 'CommexFieldReference',
        'required' => TRUE,
        'resource' => 'member',
        'query' => 'fields=name&fragment=',
        'edit access' => 'transactionEditAccess'
      ),
      'category' => array(
        'label' => 'Category',
        'fieldtype' => 'CommexFieldCategory',
        'filter' => 'getCategoryOptions',
        'edit access' => 'transactionEditAccess'
      ),
      'created' => array(
        'label' => 'Date',
        'fieldtype' => 'CommexFieldVirtual',
        'callback' => 'transactionCreated',
        'sortable' => TRUE,
        'edit access' => 'transactionEditAccess'
      ),
      'amount' => array(
        'label' => 'Amount',
        'fieldtype' => 'CommexFieldInteger',
        'min' => 0,
        'sortable' => TRUE,
        'edit access' => 'transactionEditAccess'
      )
    );
    // Adjust the amount field to a compound field as necessary

    $currency_display = $this->currency()->display;
    if ($currency_display['divisions']) { // make it a compound field
      $fields['amount'] = array(
        'label' => 'Amount',
        'fieldtype' => array($fields['amount'])
      );
      unset($fields['amount']['fieldtype'][0]['label']);

      switch($currency_display['divisions']) {
        case CURRENCY_DIVISION_MODE_CENTS_INLINE://this won't work ATM as the amount field is an integer
        case CURRENCY_DIVISION_MODE_CENTS_FIELD:
          $fields['amount']['fieldtype'][1] = array(
            'fieldtype' => 'CommexFieldInteger',
            'max' => 99,
            'min' => 0,
            'width' => 2
          );
          break;

        case CURRENCY_DIVISION_MODE_CUSTOM:
          $fields['amount']['fieldtype'][1] = array(
            'fieldtype' => 'CommexFieldEnum',
            'options' => $currency_display['divisions_allowed']
          );
          break;
      }
    }
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
    $query = db_select('mcapi_transactions', 't')
      ->fields('t', array('serial'))
      ->range($offset, $limit);
    $query->join('field_data_worth', 'w', "t.xid = w.entity_id AND w.entity_type = mcapi_transaction'");
    $query->condition('w.worth_currcode', $this->currency()->info['currcode']);
    if (!empty($params['state'])) {
      $query->condition('state', $state);
    }
    else {
      $query->condition('t.state', array(TRANSACTION_STATE_FINISHED, TRANSACTION_STATE_PENDING), 'IN');
    }

    if (!empty($params['payer'])) {
      $query->condition('t.payer', $params['payer']);
    }
    if (!empty($params['payee'])) {
      $query->condition('t.payee', $params['payer']);
    }
    if (!empty($params['involving'])) {
      $or = db_or();
      $or->condition('t.payee', $params['involving'])
        ->condition('t.payer', $params['involving']);
      $query->condition($or);
    }

    // Filter by name or part-name.
    // @todo use the entity label field and put this is in the base class
    if (!empty($params['fragment'])) {
      $query->condition('description', $params['fragment'].'%', 'LIKE');
    }

    //sort (optional, string) ... Sort according
    if (!empty($params['sort'])) {
      list($field, $dir) = explode(',', $params['sort'].',DESC');
      switch ($field) {
        case 'amount':
          $query->join('field_data_worth', 'worth', 'worth.entity_id = t.xid');
          $query->orderby('worth.worth_quantity', $dir);
          break;
        case 'created':
        default:
          $query->orderby('t.created', $dir);
      }
    }
    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $transaction = transaction_load($id);
    if ($transaction->state == TRANSACTION_STATE_PENDING and module_exists('mcapi_signatures')) {echo 1;
      if (isset($transaction->pending_signatures[$GLOBALS['user']->uid]) and $transaction->pending_signatures[$GLOBALS['user']->uid] == 1) {
        $operations['sign'] = 'Sign';
      }
      elseif (!empty($GLOBALS['user']->roles[RID_COMMITTEE])) {
        $operations['signoff'] = 'Sign';
      }
    }
    return $operations;
  }

  /**
   * Mark the user as absent or present
   */
  function operate($id, $operation) {
    $transaction = transaction_load($id);
    module_load_include('inc', 'mcapi_signatures');
    if ($operation == 'sign') {
      transaction_sign($transaction, $GLOBALS['user']->uid);
    }
    elseif ($operation == 'signoff') {
      foreach (array_keys(mcapi_get_signatories($id)) as $uid) {
        transaction_sign($transaction, $uid);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    $transaction = transaction_load($id);// NB $id is the serial number not the xid
    $worth = $transaction->worth[LANGUAGE_NONE][0];
    $fieldData = parent::loadCommexFields($id) + array(
      // Note we are giving the user ID not the wallet ID, otherwise we need to define a new REST endpoint
      'payer' => $transaction->payer,
      'payee' => $transaction->payee,
      'created' => $transaction->created
    );
    $fields = $this->fields();
    if ($fields['amount']['fieldtype'] == 'CommexFieldInteger') {
      $fieldData['amount'] = intval($worth['quantity']);
    }
    else {
      $fieldData['amount'] = explode('.', $worth['quantity']);
    }
    if ($items = field_get_items('transaction', $transaction, variable_get('transaction_description_field', ''))) {
      $fieldData['description'] = $items[0]['value'];
    }
    //prepare non-virtual CommexField values from the native entity
    return $fieldData;
  }
  /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {

    //THIS HAS TO MOVE - to be done more natively, or done here directly from $obj
    module_load_include('inc', 'mcapi');
    $obj->amount->value = currency_explode($obj->amount);

    if ($obj->id) {
      $transaction = transaction_load($obj->id);
    }
    else {
      $transaction = new stdClass();
      $transaction->type = '1stparty';
      $transaction->state = TRANSACTION_STATE_FINISHED;
      module_load_include('inc', 'mcapi_signatures');
      if (in_array($transaction->type, _get_signable_transaction_types())) {
        $config = _signature_settings_default($type);
        if ($config['participants'] || $config['countersignatories']) {
          $transaction->state = TRANSACTION_STATE_PENDING;
        }
      }
      $transaction->creator = $GLOBALS['user']->uid;
      $transaction->created = REQUEST_TIME;
    }
    $transaction->payer = $obj->payer;
    $transaction->payee = $obj->payee;
    $transaction->description = $obj->description;
    $fields = $this->fields();
    if ($fields['amount']['fieldtype'] != 'CommexFieldInteger') {
      $obj->amount = implode('.', $obj->amount);
    }
    $transaction->worth[LANGUAGE_NONE][0] = array(
      'currcode' => currency_load()->info['currcode'],
      'quantity' => $obj->amount
    );

    if ($errors) {
      header('Status: 400 Bad data');
      return $errors;
    }
    if ($obj->id) {
      entity_get_controller('transaction')->update($transaction, $transaction->state);
    }
    else {
      $cluster = transaction_cluster_create($transaction, TRUE);
    }
    $obj->ID = reset($cluster)->serial;
  }

  /**
   * {@inheritdoc}
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $result = parent::view($obj, $fieldnames, $expand);
    $formatted = array(
      '#theme' => 'worth_item',
      '#quantity' => is_array($obj->amount)  ? implode('.', $obj->amount) : $obj->amount,
      '#currcode' => $this->currency()->info['currcode']
    );
    $result['amount'] = render($formatted);
    $result['payer'] = format_username(user_load($result['payer']));
    $result['payee'] = format_username(user_load($result['payee']));
    return $result;
  }

  /**
   * Get the first currency, assuming that's the one the app uses
   *
   * @return stdClass
   *   A ctools currency
   */
  private function currency() {
    return currency_load();
  }


  function transactionCreated($id) {
    $transaction = transaction_load($id);
    return $transaction->created;
  }


  function getCategoryOptions() {
    require_once('resources/Category.php');
    return Category::getCategories();
  }

  function transactionEditAccess($xid) {
    return FALSE;
  }

}

