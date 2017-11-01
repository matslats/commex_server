<?php

/**
 * Example class for handling the member resource
 * See the base class for interface and documentation
 * This class contains imaginary function names and is NOT intended to work.
 */
class member extends CommexRestResourceBase {

  /**
   * The structure of the member, not translated.
   */
  protected $fields = array(
    'name' => [
      'fieldtype' => 'CommexFieldText',
      'label' => 'First name & last name',
      'required' => TRUE,
      'sortable' => TRUE
    ],
    'mail' => [
      'fieldtype' => 'CommexFieldText',
      'label' => 'Email',
      'required' => TRUE,
      'regex' => '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$',
    ],
    'pass' => [
      'fieldtype' => 'CommexFieldText',
      'label' => 'Password',
      'required' => FALSE
    ],
    'phone' => [
      'fieldtype' => 'CommexFieldText',
      'label' => 'Phone',
      'required' => FALSE,
      '_comment' => 'for validation consider https://github.com/googlei18n/libphonenumber'
    ],
    'aboutme' => array(
      'fieldtype' => 'CommexFieldText',
      'long' => TRUE,
      'label' => 'What would you do if you had enough money?',
      'required' => FALSE
    ),
    'street_address' => [
      'fieldtype' => 'CommexFieldText',
      'label' => 'Street address',
      'required' => FALSE
    ],
    'locality' => [
      'fieldtype' => 'CommexFieldEnum',
      'label' => 'Neighbourhood',
      'required' => TRUE,
      'options_callback' => 'getLocalityOptions'
    ],
    'image' => array(
      // @todo do we need to specify what formats the platform will accept, or what sizes?
      'fieldtype' => 'CommexFieldImage',
      'label' => 'Portrait'
    ),
    'balance' => array(
      'fieldtype' => 'CommexFieldVirtual',
      'label' => 'Balance',
      'callback' => 'memberBalance'//callbacks must be in this class and public
    )
  );

  /**
   * {@inheritdoc}
   */
  public function getList(array $params, $offset, $limit) {
    $query = get_user_query();
    // Build a query on your entity type, using the filters passed in $params
    if (isset($params['name'])) {
      $query->where[] = "username = '".$params['name'].'"';
    }
    if (!empty($params['sort'])) {
      list($field, $dir) = explode(',', $params['sort'].',DESC');
      switch ($field) {
        case 'name':
          //NB the username might not always be the display name
          $query->sort[] = "username $dir";
          break;
        case 'id':
          $query->sort[] = "id $dir";
          break;
        case 'lastaccess':
        default:
          $query->sort[] = "lastlogin $dir";
      }
    }
    $ids = $query->execute();
    // You must support sorting on every field where 'sortable' = TRUE
    return $ids;
  }


  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    // Load your member and put all its field values into an array
    $user = user_load($id);//your internal user object
    $values = array(
      'name' => $user->name,
      'aboutme' => $user->aboutme,
      //the mobile interface is likely to be a bit more efficient than the full browser interface
      'address' => $user->street_address ."\n".$user->locality
      //etc
    );
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    $account = $obj->id ? user_load($obj->id) : NULL;
    //get the existing or an unpopulated user object, and overwrite it with each field in the commex obj
    $user = user_load($obj->id);
    // Examples of how you might convert the $obj fields to your internal format
    $user->name = $obj->name;
    $user->mail = $obj->mail;
    $user->pass = $obj->pass;
    if (empty($obj->id)) {
      // Perhaps new users must go through some identification process
      $user->identified = FALSE;
      $user->language[] = 'english'; //whatever needs to be done.
    }
    $user->address[0] = $obj->street_address;
    $user->address[1] = $obj->locality;
    $user->phone['mobile'] = $obj->phone;
    //todo need to show the incoming pic should be managed
    $user->picture = array(
      'url' => $obj->portrait
       //maybe you want to get and store the dimensions or whatever
    );
    $id = user_save($user);
    $obj->id = $id;
    //the $obj might need an id to be viewed.
  }

  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $fields = parent::view($obj, $fieldnames, $expand);
    unset($fields['mail'], $fields['pass']);// Don't show these to other users.
    return $fields;
  }


  /**
   * Example enum callback, also called from example config.php
   */
  function getLocalityOptions() {
    //NB API doesn't specify about these being translated
    return array(
      1 => 'Apple Orchard',
      2 => 'Brown Fields',
      3 => 'Carrot Court',
      4 => 'Dark Hill',
    );
  }
}
