<?php

/**
 * @file
 */

/**
 * Endpoints this platform supports, For now the keys are fixed in the API, the
 * values are local class names in eponymous .php files the resources directory,
 * @var array
 */
global $framework, $endpoints;

$framework = 'drupal8';

$endpoints = array(
  //endpoint => classname
  'transaction' => 'transaction',
  'offer' => 'offer',
  'want' => 'want',
  'member' => 'member'
);


/**
 * Generate the configuration array.
 *
 * Called from index.php
 *
 * @global array $endpoints
 * @return array
 *   Keys and values specified in the API
 */
function commex_config() {
  global $endpoints;
  $config = array(
    'versions' => array(COMMEX_VERSIONS),
    'logo'  => 'http://myfunkysite.com/logo.gif',
    'slider' => array(),// urls to background
    'sitename' => 'My funkky site',//the site name of the exchange
    'css' => '',
    'endpoints' => $endpoints
  );

  return $config;
}
