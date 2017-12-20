<html>
  <head>
    <title>Commex testing script</title>
  </head>
  <body>
  <?php if (!$_POST) { ?>
    <form method = "post">
      <input type="text" name="username" placeholder="username" />
      <input type="text" name="password" placeholder="password" />
      <input type="submit">
    </form>
  <?php } else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    $_SERVER['REQUEST_URI'] = dirname($_SERVER['REQUEST_URI']);
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    //$_SERVER['HTTP_AUTHORIZATION'] = 'Basic '.base64_encode($_POST['username'].':'.$_POST['password']);
    $_SERVER['PHP_AUTH_USER'] = $_POST['username'];
    $_SERVER['PHP_AUTH_PW'] = $_POST['password'];
    include_once('index.php');
    $config = commex_config();
    validate_config($config);
    global $endpoints;
    foreach ($endpoints as $endpoint => $name) {
      test_endpoint($endpoint);
    }

    //Contact form
    $user_id = autocomplete_rand('member', 'fields=id&fragment=');
    list($status_code) = process('POST', 'contact', $user_id, '', '', array('test subject', 'test body'));
    if ($status_code == 200) {
      test_output_ok('Sent contact mail to user: '.$id, 'DELETE', $resource);
    }
    else {

    }
    global $messages;
    foreach($messages as $resource => $methods) {
      foreach ($methods as $method => $ids) {
        foreach ($ids as $id => $comments) {
          foreach ($comments as $comment) {
            echo "\n<br />$resource: $method: $id: " . $comment;
          }
        }
      }
    }
  } ?>
  </body>
</html>

<?php

function validate_config($config) {
  //do tests...
}

/**
 * Run through viewinng, writing editing and deleting resources on an endpoint.
 *
 * @param string $resource
 */
function test_endpoint($resource)  {
  global $messages;
  // Test the content of OPTIONS
  list($status_code, $result) = process('OPTIONS', $resource);
  if ($status_code == 403) {
    die('Login failed');
  }
  elseif (!is_array($result)) {
    die("No OPTIONS on $resource: $result");
  }
  foreach ($result as $method  => $fields) {
    test_field_definitions($resource, $method, $fields);
  }

  // Now we POST to this resource
  if (isset($result['POST']))  {
    unset($existing);
    $values = commex_test_populate_fields($result['POST'], TRUE);
    list($status_code, $content) = process('POST', $resource, 0, '', '', $values);

    if ($status_code == 201) {
      $id = $content['id'];
      test_output_ok("Item $id created", 'POST', $resource);
      commex_test_check_values($content, $values, 'POST', $resource);
    }
    else {
      test_output_error($status_code .'Unable to create item: '.$content, 'POST', $resource);
      return;
    }
  }
  else {
    test_output_warning("Configured not to be able to create $resource", 'POST', $resource);
    // Get an item to edit.
    list($status_code, $existing) = process('GET', $resource);
    $id = key($existing);
    list($status_code, $content) = process('GET', $resource, $id);
  }
  list($status_code, $result) = process('OPTIONS', $resource, $id);
  foreach ($result as $method  => $fields) {
    test_field_definitions($resource, $method, $fields);
  }

  // Now we need to PATCH, GET, and DELETE the thing we just POSTed
  if (isset($result['PATCH'])) {
    $test_values = commex_test_populate_fields($result['PATCH'], FALSE);
    list($status_code, $content) = process('PATCH', $resource, $id, '', '', $test_values);
    if ($status_code == 200 and is_array($content)) {
      test_output_ok('Item updated', 'PATCH', $resource);
      commex_test_check_values($content, $test_values, 'PATCH', $resource);
    }
    else {
      test_output_error($status_code ." Unable to update item $id:".print_r($content, 1), 'PATCH', $resource, $id);
    }
  }

  if (isset($result['GET'])) {
    list($status_code, $content) = process('GET', $resource, $id);
    if ($status_code == 200)  {
      test_output_ok("Item $id retrieved", 'GET', $resource);
      if (empty($existing)) {
        commex_test_check_values($content, $test_values, 'GET', $resource);
      }
    }
    else {
      test_output_error($status_code.' Failed to retrieve resource '.$id, 'GET', $resource, $id);
      return;
    }
  }
  if (isset($existing)) {
    return;
  }
  // Now delete the item
  if (isset($result['GET'])) {
    $id = $content['id'];
    list($status_code) = process('DELETE', $resource, $id);
    if ($status_code == 204) {
      list($status_code, $content) = process('GET', $resource, $id);
      if ($status_code == 410) {
        test_output_ok('Deleted item: '.$id, 'DELETE', $resource);
      }
      else {
        test_output_error($status_code .' Unexpected result when trying to GET deleted resource '.$id, 'DELETE', $resource, $id);
      }
    }
    else {
      test_output_error($status_code .' Problem deleting '.$content['id'], 'DELETE', $resource, $content['id']);
    }
  }


}

function test_field_definitions($resource, $method, $fields) {
  foreach ($fields as $id => $def) {
    if (empty($def['label'])) {
      //Subfields shouldn't have labels anyway
      //test_output_error('No label on field '.$id, $method, $resource, $id);
    }
    if ($method == 'GET') {
      $formats = array('html', 'uri', 'image');
      if (!in_array($def['format'], $formats)) {
        test_output_error('Invalid format '.$def['format'], 'GET-OPTIONS', $resource, $id);
      }
    }
    elseif ($method == 'POST' or $method == 'PATCH') {
      // For compound fields call this function recursively
      if (is_array($def['type'])) {
        test_field_definitions($resource, $method, $def['type']);
      }
      else {
        switch($def['type']) {
          case 'textfield':
            if (isset($def['autocomplete'])) {
              // Pick the first result from a randomletter
              $randletter = chr(97 + mt_rand(0, 25));
              list($status_code, $items) = process('GET', $def['resource'], 0, '', $def['autocomplete'].$randletter);
              //check that all the users contain the letter!
              foreach ($items as $name) {
                if (empty($name)) {
                  test_output_error("GET with fragment '$randletter' returned empty item", 'GET (list)', $resource, $id);
                  break;
                }
                if (stripos($name, $randletter) === FALSE) {
                  test_output_warning("GET with fragment '$randletter' returned '$name' without that fragment in the title", 'GET (list)', $resource, $id);
                  break;
                }
              }
            }
            break;
          case 'textarea':
            if (empty($def['lines'])) {
              test_output_error('Textarea must define number of lines', 'POST-OPTIONS', $resource, $id);
            }
            break;
          case 'date':
            if (empty($def['min'])) {
              test_output_error('Date must define min, ie earliest', 'POST-OPTIONS', $resource, $id);
            }
            break;
          case 'number':
            if (!isset($def['min'])) {
              test_output_error('Number must define min', 'POST-OPTIONS', $resource, $id);
            }
            break;

          case 'select':
            if (empty($def['options'])) {
              test_output_error('Select field must define options or options_callback', 'POST-OPTIONS', $resource, $id);
            }
            break;
          case 'image':
          case 'number':
            break;
          default:
            test_output_error('Invalid widget type '.$def['type'], 'POST-OPTIONS', $resource, $id);
        }
      }
    }
    elseif($method == 'DELETE') {
      //no checks
    }
    else {
      test_output_error('OPTIONS should not return '.$method, 'OPTIONS', $resource);
    }
  }
  if (empty($messages['OPTIONS'][$method])) {
    test_output_ok("OK", $method, $resource, 'definition');
  }
}

function test_output_error($message, $method, $resource, $id = 0) {
  global $messages;
  $messages[$resource][$method][$id][] = "<font color=\"red\">$message</font>";
}

function test_output_ok($message, $method, $resource, $id = NULL) {
  global $messages;
  $messages[$resource][$method][$id][] = "<font color=\"green\">$message</font>";
}
function test_output_warning($message, $method, $resource, $id = NULL) {
  global $messages;
  $messages[$resource][$method][$id][] = "<font color=\"orange\">$message</font>";
}

/**
 * Get some example data for each field of a resource.
 *
 * @param array $field_definitions from the OPTIONS
 * @param bool $required_only
 *   TRUE to populate the required fields, FALSE to populate the non-required fields.
 *
 * @return array
 *   Some default values
 */
function commex_test_populate_fields(array $field_definitions, $required_only = TRUE) {
  $vals = array();
  foreach ($field_definitions as $fieldname => $def) {
    if ((bool)@$def['required'] or !$required_only) {
      // Compound Fields
      if (is_array($def['type'])) {
        //always get all the subfields
        $vals[$fieldname] = commex_test_populate_fields($def['type'], $required_fields);
      }
      else {
        if ($def['type'] == 'textfield' and isset($def['min'])) {
          // Because floating point numbers use a textfield widget
          $def['type'] = 'number';
        }
        switch ($def['type']) {
          case 'textfield':
            if (isset($def['autocomplete'])) {
              $vals[$fieldname] = autocomplete_rand($def['resource'], $def['autocomplete']);
            }
            else {
              $vals[$fieldname] = 'Default text for a textfield';
            }
            break;
          case 'textarea':
            for ($i = 0; $i < $def['lines']; $i++) {
              $contents[] = 'Default text for a one line of a textarea';
            }
            $vals[$fieldname] = implode("\n", $contents);
            break;
          case 'image':
            $img = file_get_contents('/home/matslats/Pictures/matsfoot250px.jpg');
            // Might need to put some stuff before the comma
            $vals[$fieldname] = 'data:image/jpeg;base64,'.base64_encode($img);
            break;
          case 'select':
            $vals[$fieldname] = array_rand($def['options'], $def['multiple'] + 1);
            break;
          case 'number':
            $vals[$fieldname] = 1;
            break;
          case 'integer':
            $vals[$fieldname] = rand(isset($def['min']) ? $def['min'] : 0, $def['max'] ?: 10);
            break;
          case 'date':
            // We don't understand the min/max format so just put today's date
            $vals[$fieldname] = time();
            //$vals[$fieldname] = rand(isset($def['min']) ? $def['min'] : strtotime('-1 month'), $def['max'] ?: strtotime('+1 month'));
            break;
        }
      }
    }
  }
  return $vals;
}


function autocomplete_rand($resource_type, $string) {
  static $used = array();
  $used[$resource_type] = (array)$used[$resource_type];
  list($code, $items) = process('GET', $resource_type, 0, '', $string.'&limit=100&depth=0');
  if (empty($items)) {
    die("No items on $resource_type");
  }
  $items = array_diff($items, $used[$resource_type]);
  shuffle($items);
  $rand_item = reset($items);
  $used[$resource_type][] = $rand_item;
  return $rand_item;
}

function commex_test_check_values(array $content, array $values, $method, $resource_id) {
  $resource_plugin = commex_get_resource_plugin($resource_id);
  foreach ($values as $name => $value) {
    if ($name == 'pass') {
      continue;
    }
    $fieldDef = $resource_plugin->fields()[$name];
    if ($fieldDef['fieldtype'] == 'CommexFieldImage') {
      continue;
    }
    if ($content[$name] != $values[$name]) {
      $message = "Field '$name' has a different value. given: '".substr(print_r($values[$name], 1), 0, 50) ."' returned: ".$content[$name];
      test_output_warning($message, $method, $resource_id);
    }
  }
}
