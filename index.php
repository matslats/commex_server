<?php

$commex_root = dirname($_SERVER['SCRIPT_FILENAME']) .'/';

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
    if ($headers['Accept'] != strtolower('application/json')) {
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
  if ($shared) {
    require_once 'includes/'.$object_name.'.php';
  }
  else {
    require_once $framework.'/'.$object_name.'.php';
  }
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
function process($method, $resource_type, $id = 0, $operation= '', $query_string = '', array $input = []) {
  //echo "\n<br />$resource_type $method";
  require_once $_SERVER['DOCUMENT_ROOT'].'/commex/config.php';
  //This allows each endpoint / service potentially to do its own authentication
  if (!$resource_type) {
    // This applies to ALL methods
    return commex_deliver(200, commex_config());
  }

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
      return commex_deliver(200, []);
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
      return commex_deliver(200, ['POST']);
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
      $fieldnames = isset($query['fields']) ? explode(',',$query['fields']) : array();
      $offset = isset($query['offset']) ? $query['offset'] : 0;
      $limit = isset($query['limit']) ? $query['limit'] : 10;
      unset($_GET['depth'], $_GET['fields'], $_GET['limit'], $_GET['offset']);
      if ($id) {
        $expand = @$query['depth'] > 1;
        if ($vals = $resource_plugin->loadCommexFields($id)) {
          $obj = $resource_plugin->getObj($vals);
          $content = $resource_plugin->view(
            $obj,
            $fieldnames,
            $expand
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
          if (!empty($query['depth']) or $fieldnames) {
            $expand = @$query['depth'] -1;
            $content[] = $resource_plugin->view(
              $obj,
              $fieldnames,
              $expand
            );
          }
          else {
            $content[] = $obj->uri;
          }
        }
      }
      //header('Last-modified: '. date('D, d M Y H:i:s T', $resource_plugin->lastModified));
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
      $resource_plugin->operate($id, $operation);
      $content = $resource_plugin->getObj($resource_plugin->loadCommexFields($id))->view();
      break;

    case 'DELETE':
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


function commex_json_input() {
  $input = file_get_contents('php://input');
  return (array)json_decode($input);
}

/**
 * Supporting functions
 */
function commex_get_resource_plugin($resource_type) {
  global $commex_root, $endpoints;

  commex_require('CommexRestResourceInterface', TRUE);
  commex_require('CommexRestResource', FALSE);
  $classname = isset($endpoints[$resource_type]) ? $endpoints[$resource_type] : $resource_type;
  commex_require($classname, FALSE);
  $class = new $classname();
  return $class;
}

/**
 *
 * @param int $status_code
 * @param array $content
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

/*
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
      $output = array("error" => "404 Not found");
      break;

    case 403:
      $output = array("error" => "403 Access denied");
      break;

    case 200:
    case 201:
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
 */
function commex_get_field_class($className) {
  global $commex_root;
  global $framework;
  commex_require('CommexFieldInterface', TRUE);//this contains the base class

  if (is_array($className)) {
    commex_require('CompoundCommexField', TRUE);
    return 'CompoundCommexField';
  }
  else {
    commex_require('CommexField', TRUE);
  }

  if (file_exists($commex_root .'/includes/'. $className.'.php')) {
    commex_require($className, TRUE);
  }
  elseif (file_exists($framework.'/'.$className.'.php')) {
    commex_require($className, FALSE);
  }
  else {
    throw new \Exception('File does not exist: '. $className);
  }
  return $className;
}
