<?php

/**
 * @file
 * Public functions, mostly called from index.php
 * Do NOT Modify!
 */

interface CommexRestResourceInterface {

  static function authenticate($username, $password);

  function delete($entity_id);

  function getList(array $params, $offset, $limit);

  function getObj(array $vals = array());

  function getOptions($id = NULL, $operation = NULL);

  function getOptionsFields(array $methods);

  function operations($id);

  function loadCommexFields($id);

  function saveNativeEntity(CommexObj $obj, &$errors = array());

  function uri($id, $operation = NULL);

  function view(CommexObj $obj, array $fieldnames = array(), $expand = FALSE);

}
