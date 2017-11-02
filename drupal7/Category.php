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
    $vid = db_query("SELECT vid FROM {taxonomy_vocabulary} WHERE machine_name = 'offers_wants_categories'")->fetchField();
    foreach (taxonomy_get_tree($vid) as $term) {
      // Hope there are no translation issues....
      $options[$term->tid] = $term->name;
    }
    return $options;
  }


  /**
   * Get more metadata about categories, to be used by the client for navigation, menu items etc.
   *
   * @return array|NULL
   *   An array keyed by category id; each value is an array with keys name, color, and icon
   */
  static function getCategoryNavigation() {
    foreach (static::getCategories() as $id => $name) {
      $cats['categories'][$id] = array(
        'name' => $name,
        'logo' => '',
        'color' => ''
      );
    }
    return $cats;
  }

}
