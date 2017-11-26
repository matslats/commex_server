<?php

use Drupal\user\Entity\User;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\image\Entity\ImageStyle;

//Otherwise the bootstrapped Drupal thinks it is in the wrong directory
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
   * {@inheritdoc}
   */
  public function delete($entity_id) {
    $storage = $this->getEntityStorage();
    if ($entity = $storage->load($entity_id)) {
      $storage->delete([$entity_id => $entity]);
      // Assume success
      return TRUE;
    }
  }


  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    // Drupal's user doesn't have a lastModified time - somehow.
    $entity = $this->getEntityStorage()->load($id);
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
    $storage = $this->getEntityStorage();
    if ($obj->id) {
      $entity = $storage->load($obj->id);
    }
    else {
      $props = [];
      $definition = \Drupal::entityTypeManager()->getDefinition($this->entityTypeId);
      if ($definition->hasKey('bundle')) {
        $props[$definition->getkey('bundle')] = $this->bundle;
      }
      $entity = $storage->create($props);
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
   * Check whether the given username & password are valid and load the current
   * user as appropriate
   *
   * @param string $username
   * @param string $password
   *
   * @return boolean
   */
  public static function authenticate($username, $password) {
    // This is for Drupal 8
    global $container;
    if ($uid = $container->get('user.auth')->authenticate($username, $password)) {
      \Drupal::currentUser()->setAccount(User::load($uid));
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


  /**
   * @return Drupal\Core\Entity\EntityStorageInterface
   */
  private function getEntityStorage() {
    return \Drupal::entityTypeManager()->getStorage($this->entityTypeId);
  }

  public function operate($id, $operation) {}


  /**
   * Take a rendered entity image field and just extract the url(s)
   *
   * @param FieldItems $items
   * @param string $image_style_name
   * @param bool $multiple
   *
   * @return string[]
   */
  protected function extractImgsFromField($items, $image_style_name, $multiple = FALSE) {
    $renderable = $items->view(['image_style' => $image_style_name]);
    $html = \Drupal::service('renderer')->renderRoot($renderable);
    $pattern = '/src="(http[^"]*?")/';
    if ($multiple) {
      preg_match_all($pattern, $html, $matches);
    }
    else {
      preg_match($pattern, $html, $matches);
    }
    return $matches[1] ?: '';
  }

}
