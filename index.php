<?php

// We immediately move one directory up so that the base path is the path of the
// parent application, which is highly likely to be included. All internal paths
// are handled by commex_require.
chdir('../');

/**
 * @file
 * This script implements commex API requests and returns without
 * reference to any platform at all. It should be placed at
 * /commex/index.php
 * relative to the application root.
 * Platform maintainers should not touch this file, only files within
 * /commex/resources/
 *
 * NB Ensure the web server is picking up the directives in this directory's .htaccess
 */

define('COMMEX_VERSIONS', '0.5');
set_exception_handler('commex_rest_exception_handler');


foreach ($_SERVER as $name => $value) {
  if (substr($name, 0, 5) == 'HTTP_') {
    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
  }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
  // Not used
  $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
}

// Deny access
if ($headers['Accept'] != '*/*') {
  if ($_SERVER["REQUEST_METHOD"] != 'OPTIONS') {
    if ($headers['Accept'] and $headers['Accept'] != strtolower('application/json')) {
      commex_deliver(406, 'Not Acceptable: '.$headers['Accept']);
    }
  }
}

$request = parse_url($_SERVER['REQUEST_URI']);
//request path begins with /commex
@list(,,$resource_type, $id, $operation) = explode('/', $request['path']);

process(
  $_SERVER["REQUEST_METHOD"],
  $resource_type,
  $id,
  $operation,
  @$request['query'],
  commex_json_input()
);

function commex_require($object_name, $shared = TRUE) {
  global $framework;
  $file = 'commex/';
  if ($shared) {
    $file .= 'includes/'.$object_name.'.php';
  }
  else {
    $file .= $framework.'/'.$object_name.'.php';
  }
  if (file_exists($file)) {
    require_once($file);
    return;
  }
  commex_deliver(500, 'Unable to load file '.$file);
}

/**
 * Process an incoming request.
 *
 * @param string $method
 * @param string $resource_type
 * @param int $id
 * @param string $operation
 * @param string $query_string
 *   the GET parameters
 * @param array $input
 *
 * @return array
 *   http status code and array | NULL
 *
 * @throws \Exception
 */
function process($method, $resource_type, $id = 0, $operation= '', $query_string = '', array $input = array()) {
  require_once 'commex/config.php';
  //This allows each endpoint / service potentially to do its own authentication
  if (!$resource_type) {
    // This applies to ALL methods
    return commex_deliver(200, commex_config());
  }

  commex_require('CommexRestResourceInterface', TRUE);
  commex_require('CommexRestResource', FALSE);

  header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
  if ($resource_type == 'category') {
    if ($method == 'GET') {
      commex_require('Category', FALSE);
      return commex_deliver(200, Category::getCategoryNavigation());
    }
    else {
      return commex_deliver(405, 'Method not allowed.');
    }
  }
  //All other endpoints are defined by plugins and require logging in.
  if ($method == 'OPTIONS') {
    header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PATCH, PUT, DELETE");
    // Preflights are NOT authorised
    if (empty($_SERVER['PHP_AUTH_USER'])) {
      return commex_deliver(200, array());
    }
  }
  $resource_plugin = commex_get_resource_plugin($resource_type);
  if (!$resource_plugin->authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    return commex_deliver(401, 'Access denied.');
  }
  // TODO This might more properly be an operation
  if ($resource_type == 'contact') {
    if ($method == 'POST' and $id) {
      commex_require('contact', FALSE);
      if ($success = Contact::message($id, $input['subject'], $input['body'])) {
        return commex_deliver(200);
      }
      else {
        //mail failed
        return commex_deliver(400);
      }
    }
    elseif ($method == 'OPTIONS') {
      return commex_deliver(200, array('POST'));
    }
    else {
      return commex_deliver(405, 'Method not allowed.');
    }
  }

  $status_code = 200;
  $content = '';
  switch ($method) {
    case 'OPTIONS':
      $options = $resource_plugin->getOptions($id, $operation);
      header('Allow: '. implode(', ', $options));
      return commex_deliver(200, $resource_plugin->getOptionsFields($options, $id));

    case 'GET':
    case 'HEAD':
      parse_str(@$query_string, $query);
      if (isset($params['fragment'])) {
        $params['fragment'] = urldecode($params['fragment']);
      }
      $fieldnames = isset($query['fields']) ? explode(',',$query['fields']) : array();
      $offset = isset($query['offset']) ? $query['offset'] : 0;
      $limit = isset($query['limit']) ? $query['limit'] : 10;
      unset($_GET['depth'], $_GET['fields'], $_GET['limit'], $_GET['offset']);
      if ($id) {
        if ($vals = $resource_plugin->loadCommexFields($id)) {
          $obj = $resource_plugin->getObj($vals);
          $content = $resource_plugin->view(
            $obj,
            $fieldnames,
            @$query['depth']
          );
        }
        else {
          $status_code = 410; //Gone
        }
      }
      else {
        $content = array();
        foreach ($resource_plugin->getList($query, $offset, $limit) as $id) {
          $vals = $resource_plugin->loadCommexFields($id);
          $obj = $resource_plugin->getObj($vals);
          if ($fieldnames) {
            $expand = 0;
          }
          elseif (!empty($query['depth']) ){
            $expand = $query['depth'];
          }
          if ($fieldnames or !empty($expand)) {
            $content[] = $resource_plugin->view(
              $obj,
              $fieldnames,
              $expand
            );
          }
          else {//depth = 0 and no fieldnames
            $content[] = $obj->uri;
          }
        }
      }
      break;

    case 'POST':
      $obj = $resource_plugin->getObj($input);
      $resource_plugin->saveNativeEntity($obj, $errors);
      if ($errors) {
        $content = '';
        $status_code = 400;
        foreach ($errors as $loc => $message) {
          $content .= $loc .': '. $message;
        }
      }
      else {
        $content = $resource_plugin->view($obj);
        $status_code = 201;
      }
      break;

    case 'PATCH':
      if (!$id) {
        return commex_deliver(405, 'Method not allowed.');
      }
      $vals = $input + $resource_plugin->loadCommexFields($id);
      $obj = $resource_plugin->getObj($vals);
      $resource_plugin->saveNativeEntity($obj, $errors);
      if ($errors) {
        $status_code = 400;
        $content = implode(' ', $errors);
      }
      else {
        $content = $resource_plugin->view($obj);
      }
      break;

    case 'PUT':
      if (!$id) {
        return commex_deliver(405, 'Method not allowed.');
      }
      $resource_plugin->operate($id, $operation);
      $obj = $resource_plugin->getObj($resource_plugin->loadCommexFields($id));
      $content = $resource_plugin->view($obj);
      break;

    case 'DELETE':
      if (!$id) {
        return commex_deliver(405, 'Method not allowed.');
      }
      if ($resource_plugin->delete($id)) {
        $content = 'Deleted';
        $status_code = 204;
      }
      else {
        $status_code = 410;
      }
      break;

    default:
      return commex_deliver(405, 'Method not allowed.');
  }
  return commex_deliver($status_code, $content);
}

/**
 * Get the request body json and convert it to php.
 */
function commex_json_input() {
  $input = file_get_contents('php://input');
  $body_array = (array)json_decode($input);
  if ($input and empty($body_array)) {
    commex_deliver(400, 'Unable to parse http body input');
  }
  html_entity_decode_deep($body_array);
  return $body_array;
}

/**
 * Recursive function to html decode nested arrays
 */
function html_entity_decode_deep(&$val) {
  if (is_array($val)) {
    foreach ($val as &$v) {
      html_entity_decode_deep($v);
    }
  }
  else {
    $val = html_entity_decode($val);
  }
}

/**
 * Supporting functions
 */
function commex_get_resource_plugin($resource_type) {
  global $endpoints;
  commex_require('CommexRestResourceInterface', TRUE);
  commex_require('CommexRestResource', FALSE);
  $classname = isset($endpoints[$resource_type]) ? $endpoints[$resource_type] : $resource_type;
  commex_require($classname, FALSE);
  if (class_exists($classname)) {
    return new $classname();
  }
  throw new \Exception('File does not exist: '. $className);
}

/**
 * Output the results of the request.
 *
 * @param int $status_code
 * @param array $content
 *
 * @return array
 *   The same status code and content|NULL
 */
function commex_deliver($status_code, $content) {
  if (strpos($_SERVER['PHP_SELF'], 'test.php')){
    return array($status_code, $content);
  }
  else {
    commex_json_deliver($status_code, $content);
  }
}

/**
 * Delivery callback
 *
 * @param int The http status code
 * @param array|string $content
 *   The json content or error message
 *
 * @return array
 *   The same status code and content|NULL
 *
 * @todo Simplify this function by calling it with the right error codes
 */
function commex_json_deliver($status_code, $content = '') {
  header('Status: '. $status_code);
  switch ($status_code) {
    case 404:
      $output = array("error" => $content ?: "404 Not found");
      break;

    case 403:
      $output = array("error" => "403 Access denied");
      break;

    case 200:
    case 201:
    case 204:
      $output = $content;
      break;

    default:
      $output = array("error" => $content);
  }
  header('Access-Control-Allow-Origin: *');
  if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
    $output = 'NULL';
  }
  else {
    header('Content-type: application/json');
  }
  print json_encode($output, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
  exit;
}

/**
 * Custom exception handler callback
 *
 * @param Exception $exception
 */
function commex_rest_exception_handler(\Exception $exception) {
  return commex_deliver(500, $exception->getmessage());
}

/**
 * Load the file for the given classname.
 *
 * One class per file is assumed, with the same name! Most classes will be found
 * in includes, but failing that we look for classes in the resources
 * directory
 *
 * @param string $className
 *   Includes the fieldtype class files and returns the name of it.
 */
function commex_get_field_class($className) {
  global $framework;
  commex_require('CommexFieldInterface', TRUE);//this contains the base class

  if (is_array($className)) {
    commex_require('CompoundCommexField', TRUE);
    return 'CompoundCommexField';
  }
  else {
    commex_require('CommexField', TRUE);
  }

  commex_require($className, TRUE);

//  if (file_exists($commex_root .'/includes/'. $className.'.php')) {
//    commex_require($className, TRUE);
//  }
//  elseif (file_exists($framework.'/'.$className.'.php')) {
//    commex_require($className, FALSE);
//  }
//  else {
//    throw new \Exception('File does not exist: '. $className);
//  }
  return $className;
}


/**
 * Class for handling the offerings resource
  */
class advert extends CommexRestResource {

	/**
	 * The structure of the offer, not translated.
	 */
  function fields() {
    $fields = [
      'category' => [
        'fieldtype' => 'CommexFieldCategory',
        'label' => 'Offering category',
        'required' => TRUE,
        'sortable' => TRUE
      ],
      'title' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering title',
        'required' => TRUE
      ],
      'description' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Offering description',
        'required' => TRUE
      ],
      'keywords' => [
        'fieldtype' => 'CommexFieldText',
        'label' => 'Tags'
      ],
      'expires' => [
        'fieldtype' => 'CommexFieldDate',
        'label' => 'Date expires',
        'required' => TRUE,
        'sortable' => TRUE,
        'min' => 'today:add:1:day',
        'max' => 'today:add:1:year'
      ],
      'uid' => [
        'fieldtype' => 'CommexFieldReference',
        'reference' => 'member.id',
        'label' => 'Advertiser',
        'required' => TRUE,
        'sortable' => TRUE,
        'edit_access' => 'isAdmin'
      ]
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
    $this->object->creatable = TRUE;
    $this->object->deletable = TRUE;
    return $this->object;
  }

	/**
	 * {@inheritdoc}
	 */
  public function getList(array $params, $offset, $limit) {
		global $uid;
		// @todo just select id
		$query = "SELECT * FROM adverts";
		$conditions = array();
    //Assume the search is only in the user's own exchange
		$conditions[] = "xid = '".substr($uid, 0, 4) ."'";
    $ad_type = $params['ad_type'];
    $conditions[] = "ad_type = '$ad_type'";

		// Build a query on your entity type, using the filters passed in $params
		if (isset($params['uid'])) {
		  $conditions[] = "uid = '".$params['uid']."'";
		}
    elseif (!$this->ownerOrAdmin()) {
      //hide the 'hidden' ads if we're not filtering by a specific user
      $conditions[] = 'hide = 0';
    }
		if (isset($params['category'])) {
		  $conditions[] = "category = '".$params['category']."'";
    }
		if (isset($params['title'])) {
		  $conditions[] = "title = '".$params['title']."'";
    }
		if (isset($params['keywords'])) {
		  $conditions[] = "keywords LIKE '%".$params['keywords']."%'";
    }
		if (isset($params['fragment'])) {
		  $conditions[] = "(title LIKE '%".$params['fragment']."%' OR description LIKE '%".$params['fragment']."%' OR category like LIKE '%".$params['fragment']."%' OR keywords LIKE '%".$params['fragment']."%' )";
    }
		$query .= " WHERE ". implode(' AND ', $conditions);

		/*
     * We must support sorting on every field where 'sortable' = TRUE
     * $params[sort]=name:ASC,uid:DESC
     * translates to
     * " ORDER BY name ASC, UID DESC "
     */
    if (empty($params['sort'])) {
      $params['sort'] = 'expires,DESC';
    }
    list($field, $dir) = explode(',', $params['sort']);
    $dir  = strtoupper($dir);
    switch ($field) {
      case 'category':
      case 'uid':
        break;
      case 'expires':
        $field = 'date_expires';
        break;
    }
    $query .= " ORDER BY $field $dir LIMIT $offset, $limit ";
		$db = new Db();
    $ids =  array();
		foreach ($db->select($query) as $row) {
			$ids[] = $row['id'];
		}
		return $ids;
	}

	/**
	 * {@inheritdoc}
	 */
	function loadCommexFields($id) {
		// Load your offer and put all its field values into an array ready for CommexObj
		$db = new Db();
		$ads = $db->select("SELECT * FROM adverts WHERE id = '$id'");

		if (empty($ads)) {
      trigger_error("Could not find offer $id", E_USER_WARNING);
      return array();
		}
		$ad = reset($ads);
		return array(
			'id' => $ad['id'],
			'uid' => $ad['uid'],
			'adtype' => $ad['ad_type'],
			'oftype' => $ad['offering_type'],
			'category' => $ad['category'],
			'keywords' => $ad['keywords'],
			'title' => $ad['title'],
			'description' => $ad['description'],
			'requesting' => $ad['talent_rate'],
			'expires' => $ad['date_expires'],
			'image' => $ad['image']
		);
	}

	/**
	 * {@inheritdoc}
	 */
	function saveNativeEntity(CommexObj $obj, &$errors = array()) {
    global $uid;
		$xid = substr($uid, 0, 4);
		$db = new Db();
		$result = $db->select("SELECT `nid`, `country_code` FROM `exchanges` WHERE `xid` = '$xid' LIMIT 1");
    list($nid, $country) = array_values($result[0]);
		//"insert into users set field1 = a, field2 = b)"
		//"update users (field1 = a, field2 = b) where uid = ctte001"
		if ($obj->id) {
			$query = "UPDATE adverts SET ";
		}
		else {
			$query = "INSERT INTO adverts SET ";
      $fields[] = "ad_type = '$obj->ad_type'";
      $fields[] = "date_starts = NOW()";
      $fields[] =  "nid = '$nid'";
      $fields[] =  "display_nid = '$nid'";
      $fields[] =  "country = '$country'";
		}
		$fields[] =  "title = '$obj->title'";
		$fields[] =  "description = '$obj->description'";
    $fields[] =  "category = '$obj->category'"; //TODO categories have names
		$fields[] =  "keywords = '$obj->keywords'";
		$fields[] =  "date_expires = '".date('Y-m-d', $obj->expires)."'";
		$fields[] =  "uid = '$obj->uid'";
		$fields[] =  "date_edited = NOW()";
		$query .= implode(', ', $fields);
		//todo need to show the incoming pic should be managed
	//	$user->picture = array(
	//		'url' => $obj->portrait
	//		 //maybe you want to get and store the dimensions or whatever
	//	);
		if ($obj->id) {
			$query .= " WHERE id = '$obj->id' ";
		}
		$db = new Db();
		$db->query($query);
		if (!$obj->id) {
			$obj->id = $db->last_id();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($entity_id) {
		$db = new Db();
		$result = $db->query("DELETE FROM adverts where id = " .$entity_id);
    return TRUE;
	}

  /*
	 * {@inheritdoc}
   */
  function ownerOrAdmin() {
    global $uid;
    if ($this->object->uid == $uid) {
      return TRUE;
    }
    return $this->isAdmin();
  }

  /*
   * Custom field access callback
   *
   * @return boolean
   *   TRUE if the current user is superadmin or admin of the current group
   */
  protected function isAdmin() {
    global $user;
    return $user['usertype'] == 'adm';
  }

}
