<?php

commex_require('CommexObj', TRUE);
commex_require('CommexRestResourceBase', TRUE);
commex_require('db_class', FALSE);

/**
 * Base class for REST endpoints
 *
 * This base class contains valid but hardly functional code. It is intended as
 * starting point for developers .
 * Each endpoint class should extend this class and hence implement CommexRestResourceInterface
 * This class would normally be heavily modified as well.
 */

/**
 * Base plugin for Commex resources.
*/
abstract class CommexRestResource extends CommexRestResourceBase implements CommexRestResourceInterface {

	/**
	 * The name of the current resource, or the first part of the path
	 *
	 * @var string
	 */
	protected $resource;

	/**
	 * The last modified date of the last modified entity loaded
	 * @var int
	 * @deprecated
	 */
	public $lastModified;

	/**
	 * The internal representation of a data object.
	 * @var CommexObj
	 */
	protected $object;


	/**
	 * {@inheritdoc}
	 * you MUST overwrite this
	 */
  function loadCommexFields($id) {
    return array('id' => $id);
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
		global $uid, $user;
    $username = strtoupper($username);
		$db = new Db();
    $users = $db->select("SELECT * FROM users WHERE uid = '$username'");
    if ($users & md5($password) == $users[0]['passwd']) {
      $uid = $username;
			return TRUE;
		}
	}


  /**
   * Copy the fields from the object to the entity
   */
  protected function translateToEntity(CommexObj $obj){
    // This is the first chance we get to process the imagefield
    foreach ($this->fields() as $fname => $def) {
      if ($def['fieldtype'] == 'CommexFieldImage') {
        if ($file = $obj->{$fname}) {
          list($info, $data) = explode(',', $file);
          if (preg_match('/data:([\/a-z]+);/', $info, $matches)) {
            $mimeType = $matches[1];
            $fileType = substr($mimeType, strpos($mimeType, '/')+1);
            $decoded = base64_decode($data);

            // This is where we need to save the image and put the field value with the image reference.
            //trigger_error('Image saving route not yet written.');return;
            global $uid;
            // Make a unique filename
            $filename = $uid . time();
            $destination = '/tmp/'. $filename.'.'.$fileType;
            file_put_contents($destination, $decoded);
            $values = [$fname => $filename];
            $obj->set($values);
          }
        }
      }
    }
  }

  function delete($entity_id) {
    // nothing
  }

}

