<?php
/**
 * Plugin Name: Fergus - MtHaml Parser
 * Description: This plugin reads haml files as php.
 * Version: 1.0.0
 * Author: Nils Schönwald
 * Author URI: http://schoenwald.media
 * License: MIT
 */

fergus_init();

add_action( 'template_include', 'fergus');

function fergus($template) {
  $theme_name = get_option('template');
  $php_template = _fergus_split_path($template, $theme_name);
  $haml_template = _fergus_get_haml_temlplate($php_template);
  $has_haml_template = _fergus_check_if_haml_exists($haml_template);

  if ($has_haml_template) {
    fergus_render_haml($haml_template, $php_template);
  }

  return $template;
}

/**
 * Render a HAML template
 */
function fergus_render_haml($haml_template, $php_template) {
  // Retrieve options for the Haml parser
  $options = _fergus_default_haml_options();

  $haml_dir = get_template_directory() . '/' . $haml_template['path'];
  $haml_fullpath = $haml_dir . $haml_template['name'];
  $php_dir = get_template_directory() . '/' . $php_template['path'];
  $php_fullpath = $php_dir . $php_template['name'];

  if (!file_exists($php_dir)) {
    mkdir($php_dir, 0777, true);
  }

  if (!_fergus_cache_is_fresh($php_fullpath, $haml_fullpath)) {
    // Cached file doesn't exist or is old, generate a new file from haml template
    if (wp_is_writable(get_template_directory())) {
      $parser = new MtHaml\Environment('php', $options);
      $compiled = $parser->compileString(file_get_contents($haml_fullpath), $haml_fullpath );
      file_put_contents($php_fullpath, $compiled, LOCK_EX);
    } else {
      trigger_error('Tried creating "' . $php_fullpath . '". You must have your WordPress files directory correctly configured to use fergus.');
    }
  }
}

/**
 * Initialize the Haml Parser
 */
function fergus_init() {
  $mthaml_autoloader = plugin_dir_path( __FILE__ ) . 'lib/MtHaml/Autoloader.php';
  if( file_exists($mthaml_autoloader) ) {
    require_once $mthaml_autoloader;
    MtHaml\Autoloader::register();
  } else {
    trigger_error('MtHaml library not found in "' . $mthaml_autoloader . '" folder. You can download an install a copy of it from its github project page: https://github.com/arnaud-lb/MtHaml');
  }
}

/**
 * Internal helpers
 */

function _fergus_get_haml_temlplate($php_template) {
  // Evaluate where the cached version of the parsed haml template should be
  $haml_path = 'haml/' . $php_template['path'];
  $haml_name = str_replace('.php', '.haml', $php_template['name']);
  $haml_template = [
    'name' => $haml_name,
    'path' => $haml_path,
  ];
  return $haml_template;
}

function _fergus_check_if_haml_exists($haml_template) {
  if (file_exists(get_template_directory() . '/' . $haml_template['path'] . $haml_template['name'])) {
    return TRUE;
  }
  return FALSE;
}

function _fergus_split_path($path, $theme_name) {
  $exploded_path = explode('/', $path);
  $theme_index = array_search($theme_name, $exploded_path) + 1;

  if ($theme_index) {
    $template_source_path = array_slice($exploded_path, $theme_index, count($exploded_path) );
  } else {
    $template_source_path = '';
  }

  // die(
  //   var_dump($exploded_path) .
  //   var_dump($theme_index) .
  //   var_dump($template_source_path)
  // );

  return $file = [
    'name' => array_pop($template_source_path),
    'path' => implode('/', $template_source_path),
  ];
}

/**
 * Check to see if cached file exist and is older than the source file
 */
function _fergus_cache_is_fresh($php_fullpath, $haml_fullpath) {
  if (file_exists($php_fullpath) && file_exists($haml_fullpath)) {
    if (filemtime($php_fullpath) > filemtime($haml_fullpath)) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Default options for the Haml parser.
 */
function _fergus_default_haml_options() {
  return $options = array(
    'format' => 'html5',
    'enable_escaper' => false,
    'escape_html' => false,
    'escape_attrs' => false,
    'autoclose' => array('meta', 'img', 'link', 'br', 'hr', 'input', 'area', 'param', 'col', 'base'),
    'charset' => 'UTF-8',
  );
}
