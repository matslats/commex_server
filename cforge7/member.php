<?php

/**
 * @file
 *
 * Defines the member/ commex resource
 */
class member extends CommexRestResource {

  protected $resource = 'user';
  protected $entityTypeId = 'user';
  protected $bundle = 'user';

  /**
   * The structure of the member, not translated.
   */
  public function fields() {
    return array(
      'name' => array(
        'label' => 'First name & last name',
        'fieldtype' => 'CommexFieldText',
        'required' => TRUE,
        'sortable' => TRUE,
        'filter' => 'string'
      ),
      'mail' => array(
        'label' => 'Email',
        'fieldtype' => 'CommexFieldText',
        'required' => TRUE,
        'regex' => '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
      ),
      'pass' => array(
        'label' => 'Password',
        'fieldtype' => 'CommexFieldText',
        'required' => FALSE,
        'view access' => 'selfOrAdmin'
      ),
      'phone' => array(
        'label' => 'Phone',
        'fieldtype' => 'CommexFieldText',
        'required' => FALSE,
        '_comment' => 'for validation consider https://github.com/googlei18n/libphonenumber'
      ),
      'aboutme' => array(
        'label' => 'What would you do if you had enough money?',
        'fieldtype' => 'CommexFieldText',
        'long' => TRUE,
        'required' => FALSE
      ),
      'street_address' => array(
        'label' => 'Street address',
        'fieldtype' => 'CommexFieldText',
        'required' => FALSE
      ),
      'locality' => array(
        'label' => 'Neighbourhood',
        'fieldtype' => 'CommexFieldEnum',
        'required' => TRUE,
        'options_callback' => 'getLocalityOptions'
      ),
      'coordinates' => array(
        'label' => 'Coordinates',
        'fieldtype' => array(
            'lat' => array(
              'fieldtype' => 'CommexFieldText',
              'regex' => '^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)$'
            ),
            'lon' => array(
              'fieldtype' => 'CommexFieldText',
              'regex' => '^[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$'
            ),
        )
      ),
      'portrait' => array(
        // @todo do we need to specify what formats the platform will accept, or what sizes?
        'fieldtype' => 'CommexFieldImage',
        'label' => 'Portrait'
      ),
      'balance' => array(
        'fieldtype' => 'CommexFieldVirtual',
        'label' => 'Balance',
        'callback' => 'memberBalance'
      )
    );
  }


  /**
   * {@inheritdoc}
   */
  public function access($method, $account) {
    if ($method == 'GET') {
      $result = user_access('access user profiles', $account);
    }
    elseif ($method == 'POST') {
      $result = user_access('administer users', $account);
    }
    return $result;
  }


  /**
   * {@inheritdoc}
   */
  public function accessId($method, $account, $entity_id) {
    if ($method == 'GET') {
      $result = user_access('access user profiles');
    }
    elseif ($method == 'PATCH') {
      $result = $GLOBALS['user']->uid == $entity_id or user_access('administer users');
    }
    elseif ($method == 'DELETE') {
      $result = FALSE;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions($id = NULL, $operation = NULL) {
    $methods = parent::getOptions($id);
    //prevent creating new users, for now.
    if (is_null($id)) {
      unset($methods[array_search('POST', $methods)]);
    }
    return $methods;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsFields(array $methods) {
    $fields = parent::getOptionsFields($methods);
    // Prevent showing mail on GET
    unset($fields['GET']['mail'],$fields['GET']['pass']);
    return $fields;
  }



  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = db_select('users', 'u')->fields('u', array('uid'));
    $query->condition('u.uid', 0, '>');
    $query->condition('u.status', 1);
    $query->range($offset, $limit);

    // Filter by name or part-name.
    // @todo use the entity label field and put this is in the base class
    if (!empty($params['fragment'])) {
      $query->condition('name', $params['fragment'].'%', 'LIKE');
    }
    // Filter by locality.
    if (!empty($params['locality'])) {
      $query->join('field_data_profile_address', 'a', "a.entity_id = u.uid AND a.entity_type = 'user'");
      $query->condition('a.profile_address_dependent_locality', $params['locality']);
    }

    //sort (optional, string) ... Sort according to 'proximity' (default), 'pos' or 'neg' shows them in order of balances
    if (!empty($params['sort'])) {
      list($field, $dir) = explode(',', $params['sort'].',DESC');
      switch ($field) {
        case 'name':
          //NB the username might not always be the display name
          $query->orderby('u.name', $dir);
          break;
        case 'id':
          $query->orderby('u.id', $dir);
          break;
        case 'lastaccess':
        default:
          $query->orderby('access', $dir);
      }
    }
    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $fields = parent::view($obj, $fieldnames, $expand);
    if (is_array($fields)) {
      unset($fields['mail'], $fields['pass']);
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    if ($user = user_load($id)) {
      if (!empty($user->picture)) {
        if (is_numeric($user->picture)) {
          $user->picture = file_load($account->picture);
        }
        if (!empty($user->picture->uri)) {
          $pic_filepath = $user->picture->uri;
        }
      }
      elseif (variable_get('user_picture_default', '')) {
        $pic_filepath = variable_get('user_picture_default', '');
      }
      $fieldData = parent::loadCommexFields($id) + array(
        'name' => $user->name,
        'mail' => $user->mail,
        'portrait' => file_create_url($pic_filepath),
        'phone' => $user->profile_phones ? $user->profile_phones[LANGUAGE_NONE][0]['value'] : '', //@todo put the second phone number here as well?
        'street_addresss' => $user->profile_address[LANGUAGE_NONE][0]['thoroughfare'],
        'locality' => $user->profile_address[LANGUAGE_NONE][0]['dependent_locality'],
        'aboutme' => $user->profile_notes ? $user->profile_notes[LANGUAGE_NONE][0]['value'] : '',
      );

      // Drupal's user doesn't have a lastModified time - somehow.
      $this->lastModified = max(array($this->lastModified, $user->login));
      return $fieldData;
    }
  }

  /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    $account = $obj->id ? user_load($obj->id) : NULL;
    //build up the $edit for user_save()
    @list($firstname, $lastname) = explode(' ', $obj->name);
    $edit = array(
      'name' => $obj->name,//this will be reset in cforge_user_presave
      'mail' => $obj->mail
    );
    if ($pass = $obj->pass){
      $edit['pass'] = $pass;
    }
    if (empty($obj->id)) {
      $edit['init'] = $obj->mail;
      $edit['status'] = variable_get('user_register') < 2 or user_access('administer users');
      $edit['roles'] = array(RID_TRADER => RID_TRADER);
    }
    $edit['profile_address'] = $account ? $account->profile_address : array();
    $instance = field_info_instance('user','profile_address',  'user');
    $edit['profile_address'][LANGUAGE_NONE][0] = array(
      'first_name' => $firstname,
      'last_name' => $lastname,
      'name_line' => $obj->name,
      'thoroughfare' => $obj->street_address,
      'dependent_locality' => $obj->locality,
      'country' => $instance['default_value'][0]['country']
    );
    $edit['phones'][LANGUAGE_NONE][0]['value'] = $obj->phone;
    $edit['aboutme'][LANGUAGE_NONE][0]['value'] = $obj->aboutme;
    // Use existing values as defaults for the rest of the addressfield
    if ($account)  {
      $edit['profile_address'][LANGUAGE_NONE][0] += $account->profile_address[LANGUAGE_NONE][0];
    }
    if ($pic = $obj->portrait) {
      $validators = array(
        'file_validate_is_image' => array(),
        'file_validate_image_resolution' => array(variable_get('user_picture_dimensions', '85x85')),
        'file_validate_size' => array(variable_get('user_picture_file_size', '30') * 1024),
      );
      // The image should be in $_FILES['files']
      // @see user_validate_picture(&$form, &$form_state)
      // which calls file_save_upload
      //N.B. We haven't used the field value yet
      $edit['picture'] = file_save_upload('image', $validators);
    }
    $account = user_save($account, $edit);
    $obj->id = $account->uid;
  }


  /**
   * Enum field callback
   *
   * @return array
   *   For select widget options
   */
  function getLocalityOptions() {
    return drupal_map_assoc(preg_split("/\r\n|\n|\r/", variable_get('cforge_neighbourhoods', "")));
  }
  /**
   * Virtual field callback
   */
  function memberBalance($id) {
    // todo, determine the currency better
    return transaction_totals($id, 'credunit')->balance;
  }

  /**
   * {@inheritdoc}
   */
  function ownerOrAdmin() {
    return !empty($GLOBALS['user']->roles[RID_COMMITTEE]) or $this->object->id == $GLOBALS['user']->uid;
  }

}
