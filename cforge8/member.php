<?php


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\Entity\User;

/**
 * @file
 * Defines the member/ commex resource
 */
class Member extends CommexRestResource {

  protected $resource = 'member';
  protected $entityTypeId = 'user';
  protected $bundle = 'user';

  /**
   * The structure of the member, not translated.
   * @return array
   */
  function fields() {
    $fields = [
      'name' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'First name & last name',
        'required' => TRUE,
        'sortable' => TRUE,
        'filter' => TRUE,
        'edit_access' => 'ownerOrAdmin',
      ],
      'mail' => [
        'fieldtype' => 'CommexFieldEmail',
        'label' => 'Email',
        'required' => TRUE,
        'filter' => TRUE,
        'edit_access' => 'ownerOrAdmin',
      ],
      'pass' => [
        // todo do we need a default random password?
        'fieldtype' => 'CommexFieldText',
        'label' => 'Password',
        'required' => FALSE,
        'edit_access' => 'ownerOrAdmin',
      ],
      'phone' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Phone',
        'required' => FALSE,
        'edit_access' => 'ownerOrAdmin',
        '_comment' => 'for validation consider https://github.com/googlei18n/libphonenumber',
      ],
      'aboutme' => array(
        'fieldtype' => 'CommexFieldText',
        'lines' => 4,
        'label' => 'What would you do if you had enough money?',
        'required' => FALSE,
        'edit_access' => 'ownerOrAdmin',
      ),
      'street_address' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Street address',
        'required' => FALSE,
        'edit_access' => 'ownerOrAdmin',
      ],
      'locality' => [
        'fieldtype' => 'CommexFieldEnum',
        'label' => 'Neighbourhood',
        'required' => TRUE,
        'options_callback' => 'getLocalityOptions',
        'sortable' => TRUE,
        'filter' => TRUE,
        'edit  access' => 'ownerOrAdmin',
      ],
//      'coordinates' => [
//        'fieldtype' => [
//          'lat' => [
//            'fieldtype' => 'CommexFieldNumber',
//            'min' => -90,
//            'max' => 90
//          ],
//          'lon' => [
//            'fieldtype' => 'CommexFieldNumber',
//            'min' => -180,
//            'max' => 180
//          ],
//        ],
//        'label' => 'Coordinates',
//        'edit_access' => 'ownerOrAdmin',
//        'view access' => 'ownerOrAdmin',
//      ],
      'portrait' => array(
        'label' => 'Portrait',
        // @todo do we need to specify what formats the platform will accept, or what sizes?
        'fieldtype' => 'CommexFieldImage',
        'edit_access' => 'ownerOrAdmin',
      ),
      'balance' => array(
        'fieldtype' => 'CommexFieldVirtual',
        'label' => 'Balance',
        'callback' => 'memberBalance'
      ),
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
    $this->object->creatable = FALSE;
    $this->object->deletable = FALSE;
    //editable is handled field by field
    return $this->object;
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    $user = User::load($id);
    if (!$user) {
      throw new Exception('Unknown user ID: '.$id);
    }
    $values = parent::loadCommexFields($id) + [
      'name' => $user->getDisplayName(),
      'mail' => $user->getEmail(),
      'locality' => $user->address->dependent_locality,
      'street_address' => $user->address->address_line1,
      'aboutme' => $user->notes->value,
      'phone' => $user->phones->value,
      // I imagine there is a more concise way than this
      // view() is required to load in the default image.
      'portrait' => $this->extractImgsFromField($user->user_picture, 'thumbnail')
    ];
    return $values;
  }


  /**
   * {@inheritdoc}
   */
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
    if ($lastspace = strrpos($obj->name, ' ')) {
      $firstname = substr($obj->name, 0, $lastspace);
      $lastname = substr($obj->name, $lastspace + 1);
    }
    else {
      $firstname = $obj->name;
      $lastname = '';
    }
    $user->address->setValue([
      'given_name' => $firstname,
      'family_name' => $lastname,
      'address_line1' => $obj->street_address,
      'dependent_locality' => $obj->locality,
      'country_code' => reset($countries)
    ]);
    $user->phones->setValue($obj->phone);
    $user->notes->setValue($obj->aboutme);
    //TODO
    //$account->user_picture->setValue($params['image']);
  }

  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = $this->getListQuery($params, $offset, $limit);

    $query->condition('uid', 0, '>')->condition('status', 1);
    // Filter by name or part-name.
    // @todo use the entity label field and put this is in the base class
    if (!empty($params['fragment'])) {
      $query->condition('name', $params['fragment'].'%', 'LIKE');
    }
    // Filter by locality.
    if (!empty($params['locality'])) {
      $query->condition('address.dependent_locality', $params['locality']);
    }

    $params += ['sort' => 'lastaccess'];
    //sort (optional, string) ... Sort according to 'proximity' (default), 'pos' or 'neg' shows them in order of balances
    list($field, $dir) = explode(',', $params['sort'].',DESC');
    switch ($field) {
      case 'name':
        $query->sort('name', $dir);
        break;
      case 'locality':
        $query->sort('address.dependent_locality', $dir);
        break;
      case 'changed':
        $query->sort('access', $dir);
        break;
      case 'balance':
        // Must join to the wallet table....
      case 'geo':
      default:
        trigger_error('Cannot sort by members by field: '.$field, E_USER_ERROR);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  function operations($id) {
    $account = User::load($id);
    $operations = [];
    if ($this->ownerOrAdmin($id)) {
      if ($account->present->value) {
        $operations['absent'] = 'Go on holiday';
      }
      else {
        $operations['present'] = 'Return from holiday';
      }
    }
    return $operations;
  }

  /**
   * Mark the user as absent or present
   */
  function operate($id, $operation) {
    $account = User::load($id);
    switch ($operation) {
      case 'absent':
        $account->present->value = 0;
        break;
      case 'present':
        $account->present->value = 1;
        break;
    }
    $account->save();
  }

  /**
   * Field access callback
   *
   * Determine whether a field on a populated commex Object is editable by the current user
   *
   * @param string id
   *   The id of the member, if applicable
   *
   * @return bool
   *   TRUE if acces is granted
   */
  public function ownerOrAdmin($id = NULL) {
    static $result = NULL;
    if (!is_bool($result)) {
      $account = \Drupal::currentUser();
      // If the current user is admin
      if ($account->hasPermission('administer users')) {
        $result = TRUE;
      }
      elseif (is_null($id)) {
        // Although posting users is not currently allowed
        return TRUE;
      }
      else {
        //if the current user is the given user
        $result = $id == $account->id();
      }
    }
    return $result;
  }

  /**
   * Virtual field callback
   */
  function memberBalance($id) {
    $user = \Drupal\user\Entity\User::load($id);
    $wids = \Drupal\mcapi\Storage\WalletStorage::walletsOf($user);
    return (string)\Drupal\mcapi\Entity\Wallet::load(reset($wids))->balance;
  }

  /**
   * Enum field callback
   *
   * @return array for select field options
   */
  function getLocalityOptions() {
    $hoods = \Drupal\mcapi\Mcapi::entityLabelList('node', ['type' => 'neighbourhood']);
    return array_combine($hoods, $hoods);
  }

}
