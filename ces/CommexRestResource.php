<?php

commex_require('CommexRestResourceBase', TRUE);

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
		$db = new CommexDb();
    $users = $db->select("SELECT * FROM users WHERE uid = '$username'");
    //if ($users and md5($password) == $users[0]['passwd']) {
      $uid = $username;
      $user = reset($users);
      if (isset($_SESSION['masquerading_as'])) {
        $uid = $_SESSION['masquerading_as'];
        $users = $db->select("SELECT * FROM users WHERE uid = '$uid'");
        $user = reset($users);
      }
			return TRUE;
		//}
	}

  /**
   * Copy the fields from the object to the entity.
   */
  protected function translateToEntity(CommexObj $obj){
    // This is the first chance we get to process the imagefield
    foreach ($this->fields() as $fname => $def) {
      if ($def['fieldtype'] == 'CommexFieldImage') {
        if ($obj->{$fname}) {
          list($info, $base64data) = explode(',', $obj->{$fname});
          if (preg_match('/data:([\/a-z]+);/', $info, $matches)) {
            // This is where we need to save the image and put the field value with the image reference.
            $fileType = substr($matches[1], strpos($matches[1], '/')+1);
            $filename = $this->getAttachedFilename($fname).'.'.$fileType;
            $dest_path = $this->picpath .'/'.$filename;
            file_put_contents($dest_path, base64_decode($base64data));
            $obj->set([$fname => $filename]);
          }
        }
      }
    }
  }

  /**
   * Generate filename (before having saved the thing).
   */
  protected function getAttachedFilename($fieldname = NULL) {

  }


  /**
   * Prepare the Commex object for viewing with the client, including the HATEOAS links
   */
  public function view(CommexObj $obj, array $fieldnames = array(), $expand = 0) {
    $result = parent::view($obj, $fieldnames, $expand);
    foreach ($this->fields() as $fname => $def) {
      if ($def['fieldtype'] == 'CommexFieldText' and isset($def['lines']) and $def['lines'] > 1) {
        $result[$fname] = str_replace(array("\r", "\n"), '<br/>', trim($result[$fname]));
      }
    }
    return $result;
  }

}
