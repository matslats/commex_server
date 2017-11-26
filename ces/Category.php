<?php

/**
 * @file
 * This is a required endpoint, but very limited in functionality.
 * Note that it does NOT extend CommexRestResourceInterface.
 */
class Category {

  /**
   * get a list of categories, keyed by category id.
   */
  static function getCategories() {
    global $uid;
    $xid = substr($uid, 0, 4);
    $db = new Db();
    $result = $db->select("SELECT category FROM offering_categories WHERE xid = '$xid'");
    foreach ($result as $row) {
      $cats[$row['category']] = $row['category'];
    }
    return $cats;
  }

  /**
   * Get more metadata about categories, to be used by the client for navigation, menu items etc.
   *
   * @return array|NULL
   *   An array keyed by category id; each value is an array with keys name, color, and icon
   */
  static function getCategoryNavigation() {
    foreach (self::getCategories() as $catName) {
      $cats[$catName] = array(
        'name' => $catname,
        'color' => '',
        'icon' => ''
      );
    }
    return $cats;
  }

}
