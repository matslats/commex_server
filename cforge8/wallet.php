<?php

/**
 * @file
 * Pseudo CommexResource class just to autocomplement the transaction payer and payee fields
 */

/**
 * @file
 * Defines the wallet (special) resource
 */
class Wallet {

  /**
   * Get Wallets, assuming they identifiable by their holders.
   */
  public static function getList(array $params, $offset, $limit) {
    if (empty($params['fragment'])) {
      trigger_error('Commex Wallet::getList called without fragment');
      return [];
    }
    $query = \Drupal::entityQuery('mcapi_wallet');
    if (empty($limit)) {
      $limit = 10;
    }
    // The query range
    $query->range($offset, $limit);
    // Filter by name or part-name.
    $query->condition('name', $params['fragment'].'%', 'LIKE');

    return $query->execute();
  }


  /**
   * {@inheritdoc}
   */
  function loadCommexFields($id) {
    $wallet = \Drupal\mcapi\Entity\Wallet::load($id);
    if (empty($wallet)) {
      trigger_error('Unable to load wallet: '. $id, E_USER_ERROR);
    }
    return [
      'id' => $id,
      'name' => $wallet->label($wallet),
    ];
  }

  function getObj($vals) {
    $pseudoObj = new stdClass();
    $pseudoObj->id = $vals['id'];
    $pseudoObj->name = $vals['name'];
    return $pseudoObj;
  }

  // Show the wallet owner, not the wallet.
  function view($obj, $fieldnames = [], $expand = FALSE) {
    if ($expand)  {
      $plugin = commex_get_resource_plugin('member');
      $vals = $plugin->loadCommexFields(\Drupal\mcapi\Entity\Wallet::load($obj->id)->getOwnerId());
      $obj = $plugin->getObj($vals);
      return $plugin->view($obj, [], 1);
    }
    return $obj->name;
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
  public function authenticate($username, $password) {
    commex_include('CommexRestResourceBase', TRUE);
    return CommexRestresource::authenticate($username, $password);
  }
}
