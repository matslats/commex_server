<?php

//Otherwise the bootstrapped drupal thinks it is in the wrong directory
$GLOBALS['base_url'] = dirname($GLOBALS['base_url']);
commex_require('CommexRestResourceBase', TRUE);

/**
 * Base plugin for Commex resources.
*/
abstract class CommexRestResource extends CommexRestResourceBase implements CommexRestResourceInterface {


  /**
   * The module configuration object
   *
   * @var Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * The entityType manager
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entityQueryFactory
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * Get a commex object for this resource type, optionally populated with the given values.
   *
   * @param array $vals
   *
   * @return CommexObj
   */
  public function getObj(array $vals = array()) {
    if (empty($this->object)) {
      commex_require('CommexObj', TRUE);
      $this->object = new CommexObj($this);
    }
    return $this->object->set($vals);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($entity_id) {
    $info = entity_get_info($this->entityTypeId);
    $delete_callback = $info['deletion callback'];
    try {
      $delete_callback($entity_id);
    }
    catch (\Exception $e) {
      return 0;
    }
    return TRUE;
  }


  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    // Drupal's user doesn't have a lastModified time - somehow.
    $entity = entity_load($this->entityTypeId, array($id));
    if ($entity instanceOf \Drupal\Core\Entity\EntityChangedInterface) {
      $this->lastModified = max(array($this->lastModified, $entity->changed));
    }
    $fields = parent::loadCommexFields($id);
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    if ($obj->id) {
      $entity = entity_load($this->entityTypeId, array($id));
    }
    else {
      $props = [];
      $definition = \Drupal::entityTypeManager()->getDefinition($this->entityTypeId);
      if ($definition->hasKey('bundle')) {
        $props[$definition->getkey('bundle')] = $this->bundle;
      }
      $entity = entity_create($this->entityTypeId, $props);
    }
    $this->translateToEntity($obj, $entity);
    foreach ($entity->validate() as $violation) {
      $errors[$violation->getPropertyPath()] = (string)$violation->getMessage();
    }
    if ($errors) {
      $content = implode(' ', $errors);
    }
    else {
      $entity->save();
      $obj->id = $entity->id();
    }
  }

  /**
   * Copy the fields from the object to the entity
   */
  protected function translateToEntity(CommexObj $obj, ContentEntityInterface $entity){
    // This is the first chance we get to process the imagefield
    foreach ($this->fields() as $fname => $def) {
      if ($def['fieldtype'] == 'CommexFieldImage') {
        if ($file = $obj->{$fname}) {
          list($info, $data) = explode(',', $file);
          if (preg_match('/data:([\/a-z]+);/', $info, $matches)) {
            $mimeType = $matches[1];
            $fileType = substr($mimeType, strpos($mimeType, '/')+1);
            $decoded = base64_decode($data);
            // there's nothing in Drupal to help with this. We have to first save the file, then build a file entity from scratch.
            //where to save it?
            $filename = \Drupal::currentUser()->id().REQUEST_TIME .'.'.$fileType;
            //Assumes the commex fieldname is the same as the fieldname on the entity.
            $settings = \Drupal::service('entity_field.manager')
              ->getFieldDefinitions($this->entityTypeId, $this->bundle)
              [$fname]
              ->getSettings();
            $destination = $settings['uri_scheme'] . '://' . $settings['file_directory'] .'/'. $filename;
            $class = $settings['uri_scheme'] == 'public' ? '\Drupal\Core\StreamWrapper\PublicStream' : '\Drupal\Core\StreamWrapper\PrivateStream';
            file_put_contents(
              str_replace('public:/', DRUPAL_ROOT .'/'. $class::basePath(), $destination),
              $decoded
            );
            $values = [
              'uid' => \Drupal::currentUser()->id(),
              'status' => 1,
              'uri' => $destination,
              'filesize' => strlen($decoded),
              'filemime' => $mimeType
            ];
            $file = \Drupal\file\Entity\File::create($values);
            drupal_chmod($file->getFileUri());
            $file->save();
            $values = [$fname => $file->id()];
            $obj->set($values);
          }
        }
      }
    }
  }

  /**
   * Prepare the Commex object for viewing with the client, including the HATEOAS links
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $result = parent::view($obj, $fieldnames, $expand);
    foreach ($this->fields() as $fname => $def) {
      if ($def['fieldtype'] == 'CommexFieldImage') {
        // Alwasy renders a thumbnail
        if ($img_id = $obj->{$fname}) {
          if (is_numeric($img_id)) {
            $file = \Drupal\file\Entity\File::load($img_id);
            $style = ImageStyle::load('thumbnail');
            $filename = $style->buildUrl($file->getFileUri());
            // Bit of a hack here...
            $result[$fname] = str_replace('commex/', '', $filename);
          }
        }
      }
    }
    return $result;
  }


  /**
   * {@inheritdoc}
   */
  public static function authenticate($username, $password) {
    // Move this
    global $user;
    if ($uid = user_authenticate($username, $password)) {
      $user = user_load($uid);
    }
    return (bool)$uid;
  }


    /**
   * Creates an EntityQuery and adds conditions common to some resources.
   *
   * Namely $offset, $limit, geo, category
   *
   * @param ParameterBag $params
   * @param int $offset
   * @param int $limit
   *
   * @return EntityQuery
   *
   * @note
   */

  final protected function getListQuery(array $params, $offset, $limit) {
    $query = \Drupal::entityQuery($this->entityTypeId);
    if (empty($limit)) {
      $limit = 10;
    }
    // The query range
    $query->range($offset, $limit);

    // The geocoordinates
    if (!empty($params['radius']) or !empty($params['geo'])) {
      \Drupal\logger('Commex REST API')->warning('Geo features not yet implemented');
    }
    return $query;
  }

  public function operate($id, $operation) {}



  /**
   * Show which fields are expected for each of the given http methods
   */
  public function getOptionsFields(array $methods) {
    $info = parent::getOptionsFields($methods);
    if (module_exists('locale')) {
      foreach ($info as $methods => &$fields) {
        // Translate the field labels
        foreach ($fields as &$field) {
          if (isset($field['label'])) {
            $field['label'] = locale($field['label']);
          }
        }
      }
    }
    return $info;
  }

}
