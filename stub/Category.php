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
    return array(
      '99' => 'My Category'
    );
  }

  /**
   * Get more metadata about categories, to be used by the client for navigation, menu items etc.
   *
   * @return array|NULL
   *   An array keyed by category id; each value is an array with keys name, color, and icon
   */
  static function getCategoryNavigation() {
    //might want to call self::getCategories()
    return array(
      '99' => array(
        'name' => 'My Category',
        'color' => '#811',
        'icon' => 'http://mydomain.com/icons/my_category.png',
       )
    );
  }

}
