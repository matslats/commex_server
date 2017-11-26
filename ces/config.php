<?php

/**
 * @file
 */
global $endpoints;
/**
 * Endpoints this platform supports, For now the keys are fixed in the API, the
 * values are local class names in eponymous .php files the resources directory,
 * @var array
 */
$endpoints = array(
	'member' => 'Member',
	'offer' => 'Offer',
	'want' => 'Want',
	'trade' => 'Transaction'
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
    'logo' => '', //the logo of the exchange
    'sitename' => '',//the site name of the exchange
    'css' => '',
    'endpoints' => $endpoints
  );
  return $config;
}
