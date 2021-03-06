<?php

use Drupal\mcapi\Storage\TransactionStorage;
use Drupal\mcapi\Entity\Currency;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * @file
 *
 * Defines the member/ commex resource
 */
class CommexTransaction extends CommexRestResource {

  protected $entityTypeId = 'mcapi_transaction';
  protected $bundle = 'mcapi_transaction';

  /**
   * The structure of the transaction, not translated.
   *
   * @var array
   */
  function fields() {
    $format = $this->currency()->format;
    $fields = [
      'description' => [
        'label' => 'Description',
        'fieldtype' => 'CommexFieldText',
        'required' => TRUE,
      ],
      // These are problematic because we select the wallet name on the transaction form, but the
      'payer' => [
        'label' => 'Payer',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'wallet.name',
        'required' => TRUE
      ],
      'payee' => [
        'label' => 'Payee',
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'wallet.name',
        'required' => TRUE
      ],
      'category' => [
        'label' => 'Category',
        'fieldtype' => 'CommexFieldCategory',
        'required' => FieldConfig::load('mcapi_transaction.mcapi_transaction.category')->isRequired()
      ],
      'amount' => [
        'label' => 'Amount',
        'fieldtype' => 'CommexFieldInteger',
        'required' => TRUE,
        'min' => 0,
        'max' => str_replace('0', '9', $format[1]),
        'sortable' => TRUE,
      ],
    ];
    if (isset($format[3])) {
      // Adjust the amount field to a compound field as necessary
      if ($format[3]) {// make it a compound field
        $fields['amount'] = [
          'label' => 'Amount',
          'fieldtype' => [$fields['amount']],
          'required' => TRUE,
        ];
        unset($fields['amount']['fieldtype'][0]['label'], $fields['amount']['fieldtype'][0]['required']);
      }
      if ($format[3] == '99') {
        $fields['amount']['fieldtype'][1] = [
          'fieldtype' => 'CommexFieldInteger',
          'max' => 99,
          'min' => 0,
          'width' => 2,
        ];
      }
      // Special case for hours
      elseif($format[3] == '59/4') {
        $fields['amount']['fieldtype'][1] = [
          'fieldtype' => 'CommexFieldEnum',
          'options' => [
            '0' => '00',
            '15' => '15 mins',
            '30' => '30 mins',
            '45' => '45 mins',
          ]
        ];
      }
      elseif (strpos($format[3], '/')) {
        $fields['amount']['fieldtype'][1]['fieldtype'] = 'CommexFieldEnum';
        list($total, $divisor) = explode('/', $format[3]);
        $total++;
        $chunk = $total/$divisor;
        $fields['amount']['fieldtype'][1]['options'][0] = "00";
        for ($i=1; $i < $divisor; $i++) {
          $val = $chunk*$i;
          $fields['amount']['fieldtype'][1]['options'][$val] = $val;
        }
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $transaction = TransactionStorage::loadBySerial($id);
    $operations = [];
    // Signature operations
    if ($transaction->state->target_id == 'pending') {
      $signable = \Drupal::service('mcapi.signatures')->setTransaction($transaction);
      if ($signable->isWaitingOn()) {
        $operations['sign'] = t('Sign');
      }
      elseif (\Drupal::currentUser()->hasPermission('manage mcapi')) {
        $operations['sign'] = t('Sign for all');
      }
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  function operate($id, $operation) {
    $transaction = TransactionStorage::loadBySerial($id);
    $signable = \Drupal::service('mcapi.signatures')->setTransaction($transaction);
    if ($signable->isWaitingOn()) {
      $signable->sign();
    }
    elseif(\Drupal::currentUser()->hasPermission('manage mcapi')) {
      $signable->signOff();
    }
    $transaction->save();
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    if ($transaction = TransactionStorage::loadBySerial($id, FALSE)) {
      $currency = $transaction->worth->first()->currency;
      $amount = $currency->formattedParts($transaction->worth->first()->value);
      if (count($amount) == 1) {
        // This is not a compound field, so requires a single value
        $amount = reset($amount);
      }
      echo $id;
      print_r($transaction->payer->getValue());
      $values = parent::loadCommexFields($id) + [
        'id' => $id,
        // Note we are giving the user ID not the wallet ID, otherwise we need to define a new REST endpoint
        'payer' => $transaction->payer->entity->id(),
        'payee' => $transaction->payee->entity->id(),
        'amount' => $amount,
        'description' => $transaction->description->value,//needs sanitising
        'state' => $transaction->state->target_id,
      ];
      if (property_exists($transaction, 'category')) {
        foreach ($transaction->category->getValue() as $val) {
          $values['category'] = $val['target_id'];
        }
      }
      return $values;
    }
    throw new Exception('Unknown transaction serial: '.$id);
  }

    /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    $storage = \Drupal::entityTypeManager()->getStorage('mcapi_transaction');
    if ($obj->id) {//PATCH
      $transaction = $storage->loadbySerial($obj->id);
    }
    else {//POST new
      $props = ['type' => 'default'];
      $transaction = $storage->create($props);
    }
    $this->translateToEntity($obj, $transaction);
    foreach ($transaction->validate() as $violation) {
      $errors[$violation->getPropertyPath()] = (string)$violation->getMessage();
    }
//Array(
//  [payer.0] => The wallet value cannot be null
//  [worth] => This value should not be null.
//  [payer.0.target_id] => This value should be of the correct primitive type.
//)

    if ($errors) {
      $content = implode(' ', $errors);
    }
    else {
      $transaction->save();
      $obj->id = $transaction->serial->value;
    }
  }


  /**
   * Get the transaction commex object, converting the user names to wallet ids.
   *
   * Example incoming $vals;
   * {
   * "payer": "Sylvester Parker",
   * "payee": "Jerry Laker",
   * "description": "blah",
   * "amount": [2, 2],
   * "category": 11
   * }
   */
  public function getObj(array $vals = array()) {
    if (isset($vals['payer']) && !is_numeric($vals['payer'])) {
      $wids = \Drupal::entityQuery('mcapi_wallet')->condition('name', $vals['payer'])->execute();
      $vals['payer'] = reset($wids);
    }
    if (isset($vals['payee']) && !is_numeric($vals['payee'])) {
      $wids = \Drupal::entityQuery('mcapi_wallet')->condition('name', $vals['payee'])->execute();
      $vals['payee'] = reset($wids);
    }
    $obj = parent::getObj($vals);
    $access = \Drupal::entityTypeManager()->getAccessControlHandler('mcapi_transaction');
    //Set the commex permissions
    $obj->viewable = TRUE;
    $obj->creatable = $access->createAccess();
    if ($obj->id) {
      $transaction = TransactionStorage::loadBySerial($obj->id);
      $obj->deletable = $access->access($transaction, 'delete');
    }
    else {
      $obj->deletable = FALSE;
    }
    return $obj;
  }

  /**
   * {@inheritdoc}
   */
  protected function translateToEntity(CommexObj $obj, ContentEntityInterface $transaction) {
    $currency = $this->currency();
    $vals = [];
    //  the amount could be an integer or an array
    if (is_array($this->fields()['amount']['fieldtype'])) {
      $vals[1] = $obj->amount[0];
      $vals[3] = $obj->amount[1];
    }
    else {
      $vals = [1 => $obj->amount];
    }
    $transaction->worth->setValue(['curr_id' => $currency->id(), 'value' => $currency->unformat($vals)]);
    $transaction->description->value = $obj->description;
    // Payer and Payer are integers on POST, URIs otherwise.
    $payer_wallet = is_numeric($obj->payer) ? $obj->payer : substr($obj->payer, strpos($obj->payer, '/')+1);
    $payee_wallet = is_numeric($obj->payee) ? $obj->payee : substr($obj->payee, strpos($obj->payee, '/')+1);

    $transaction->payer->setValue($payer_wallet);
    $transaction->payee->setValue($payee_wallet);
    $transaction->category->setValue($obj->category);
  }

  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = $this->getListQuery($params, $offset, $limit);
    $query->condition('worth.curr_id', $this->currency()->id());

    if (isset($params['fragment'])) {
      $query->condition('description', $params['fragment'].'%', 'LIKE');
    }
    if (isset($params['payer'])) {
      $payer = \Drupal\user\Entity\User::load($params['payer']);
      $query->payer($payer);
    }
    if (isset($params['payee'])) {
      $payee = \Drupal\user\Entity\User::load($params['payee']);
      $query->payee($payee);
    }
    if (isset($params['involving'])) {
      $holder = \Drupal\user\Entity\User::load($params['involving']);
      $query->involving($holder);
    }
    if (isset($params['state'])) {
      $query->condition('state', $params['state']);
    }
    else {
      $query->condition('state', 'deleted', '<>');
    }
    if (!empty($params['category'])) {
      $query->condition('category', explode(',', $params['category']), 'IN');
    }

    $params += ['sort' => 'created'];
    list($field, $dir) = explode(',', $params['sort'].',DESC');
    switch ($field) {
      case 'created':
        $query->sort('created', $dir);
        break;
      case 'amount':
        $query->sort('worth', $dir);
        break;
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $depth = 0) {
    $result = parent::view($obj, $fieldnames, $depth);
    // Remove the self link - but why?
//    array_shift($result['_links']);
    // Currently the $result['amount'] is the output of the compound field, which
    // is the rendered parts of the worth, concatenated with a space.
    // We're going to reload the transaction and and render the worth field normally.
    $result['amount'] = (string) TransactionStorage::loadBySerial($obj->id)->worth;
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($entity_id) {
    $storage = \Drupal::entityTypeManager()->getStorage('mcapi_transaction');
    if ($entity = $storage->loadBySerial($entity_id)) {
      $storage->delete([$entity_id => $entity]);
      // Assume success
      return TRUE;
    }
  }

  /**
   * return Currency
   */
  private function currency() {
    // We don't have a way to choose which currency by configuration.
    $currencies = Currency::loadMultiple();
    $currencies = Currency::loadMultiple();
    return reset($currencies);
  }

  /**
   * {@inheritdoc}
   */
  public function ownerOrAdmin() {
    static $result = NULL;
    if (is_null($result)) {
      $account = \Drupal::currentUser();
      if ($account->hasPermission('manage mcapi')) {
        $result = TRUE;
      }
    }
    return $result;
  }
}

