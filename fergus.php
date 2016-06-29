<?php
/**
 * Plugin Name: Fergus - MtHaml Parser
 * Description: This plugin reads haml files as php.
 * Version: 1.0.0
 * Author: Nils Schönwald
 * Author URI: http://schoenwald.media
 * License: MIT
 */

add_action( 'template_include', 'redirect_haml_to_php', 1000 );

function redirect_haml_to_php($template) {
  //if (pathinfo($template, PATHINFO_EXTENSION) != 'haml') return $template;

  $haml_template = str_replace('.php', fergus_extension(), $template);

  if (file_exists($haml_template)) {
    //trigger_error($haml_template);
    fergus_render_template($haml_template);
  } else {
    return $template;
  }

};

fergus_init(wp_get_theme());


/**
 * Initialize fergus
 */
function fergus_init($theme) {
  // // Load drupal template.php
  // $file = dirname($theme->filename) . '/template.php';
  // if (file_exists($file)) {
  //   include_once "./$file";
  // }

  // Set Haml parser options

  if (!empty($theme->info['fergus']['options']['haml'])) {
    _fergus_set_haml_options($theme, $theme->info['fergus']['options']['haml']);
  }
  else {
    _fergus_set_haml_options($theme);
  }

  if (!file_exists(fergus_folder())) {
    mkdir(fergus_folder(), 0777, true);
  }

  // Initialize parser
  _fergus_init();

}

/**
 * The extension for our templates
 */
function fergus_extension() {
  return ".haml";
}

function fergus_folder() {
  return WP_CONTENT_DIR . '/cache/fergus/';
}

/**
 * We're handling HAML template files
 */
function fergus_theme($existing, $type, $theme, $path) {
  $templates = drupal_find_theme_functions($existing, array($theme));
  $templates += drupal_find_theme_templates($existing, fergus_extension(), $path);
  return $templates;
}

/**
 * Render a HAML template
 */
function fergus_render_template($template) {

  // die(
  //   var_dump($template) .
  //   var_dump(pathinfo($template, PATHINFO_EXTENSION))
  // );

  // Retrieve options for the Haml parser
  $options = _fergus_get_haml_options();

  $base_theme = wp_get_theme()->get('TextDomain');
  //die($base_theme);

  // Evaluate where the cached version of the parsed haml template should be
  $template_cache = _fergus_cached_haml_path($template, $base_theme);
  $template_cache_fullDirectory = fergus_folder() . $template_cache['path'];
  $template_cache_fullpath = $template_cache_fullDirectory . '/' . $template_cache['filename'];

  // die(
  //   var_dump($template_cache) .
  //   var_dump($template_cache_fullpath)
  // );

  if ( !_fergus_cache_is_fresh($template_cache_fullpath, $template) ) {

    // Cached file doesn't exist or is old, generate a new file from haml template
    if (wp_is_writable(fergus_folder())) {

      $parser = new MtHaml\Environment('php', $options);
      $compiled = $parser->compileString(file_get_contents($template), $template );

      // die(
      //   var_dump($compiled)
      // );

      $write_to_cache = fopen($template_cache_fullpath, 'w');
      fwrite( $write_to_cache, $compiled );
      fclose( $write_to_cache );

    } else {
      trigger_error('Tried creating "' . $template_cache['path'] . '". You must have your WordPress files directory correctly configured to use fergus.');
    }

  }

  // Extract Variables to a local namescape, needed for template rendering
  // extract($variables, EXTR_SKIP);

  trigger_error($template_cache_fullpath);


  // Render template
  ob_start();
  include($template_cache_fullpath);
  return ob_get_clean();
}

/**
 * A function to allow alteration of underlying parser options by
 * the theme using fergus at runtime.  Also allows modules
 * to alter options as well.
 *
 *  @param $hook
 *    The name of the alteration hook (e.g. haml_options)
 *  @param $theme
 *    Information for the theme.
 *  @param $options
 *    The options for the underlying parser.
 */
function fergus_alter($hook, &$options, $theme) {
  $hook = 'fergus_' . $hook;

  // Allow modules to alter options
  drupal_alter($hook, $options, $theme);

  // Allow theme to alter options
  $theme_function = $theme->name . '_' . $hook . '_alter';
  if (function_exists($theme_function)) {
    $theme_function($options, $theme);
  }
}

/**
 * Internal helpers
 *
 * _fergus_init()
 * _fergus_cached_haml_path($path, $base_theme)
 * _fergus_cache_is_fresh($cached_file, $source_file)
 * _fergus_default_haml_options()
 * _fergus_set_haml_options()
 * _fergus_get_haml_options()
 *
 */

/**
 * Initialize the Haml Parser
 */
function _fergus_init() {
  $mthaml_autoloader = plugin_dir_path( __FILE__ ) . 'lib/MtHaml/Autoloader.php';
  if( file_exists($mthaml_autoloader) ) {
    require_once $mthaml_autoloader;
    MtHaml\Autoloader::register();
  } else {
    trigger_error('MtHaml library not found in "' . $mthaml_autoloader . '" folder. You can download an install a copy of it from its github project page: https://github.com/arnaud-lb/MtHaml');
  }
}

/**
 * Determine the cached version path based on the original template path
 */
function _fergus_cached_haml_path($path, $base_theme) {
  $exploded_path = explode('/', $path);
  $base_theme_index = array_search($base_theme, $exploded_path);

  if ($base_theme_index) {
    $template_source_path = array_slice($exploded_path, $base_theme_index, count($exploded_path) );
    $template_filename = array_pop($template_source_path);
    $cached_filename = str_replace(fergus_extension(), '.php', $template_filename);
  }

  // die(
  //   var_dump($exploded_path) .
  //   var_dump($base_theme_index) .
  //   var_dump($template_source_path) .
  //   var_dump($template_filename) .
  //   var_dump($cached_filename) .
  //   var_dump(get_template_directory())
  // );

  return array( 'path' => implode('/', $template_source_path), 'filename' => $cached_filename );
}

/**
 * Check to see if cached file exist and is older than the source file
 */
function _fergus_cache_is_fresh($cached_file, $source_file) {
  if (file_exists($cached_file) && file_exists($source_file)) {
    if (filemtime($cached_file) > filemtime($source_file)) {
      return true;
    }
  }
  return false;
}

/**
 * Default options for the Haml parser.
 */
function _fergus_default_haml_options() {
  $options = array(
    'format' => 'html5',
    'enable_escaper' => false,
    'escape_html' => false,
    'escape_attrs' => false,
    'autoclose' => array('meta', 'img', 'link', 'br', 'hr', 'input', 'area', 'param', 'col', 'base'),
    'charset' => 'UTF-8',
  );

  return $options;
}

/**
 * Get options for the Haml parser
 */
function _fergus_get_haml_options() {
  return _fergus_set_haml_options();
}

/**
 * Set options for the Haml parser.
 */
function _fergus_set_haml_options($theme = array(), $options = array()) {
  // If no theme was passed in then return the options that have been set
  if (!empty($set_options)) {
    return $set_options;
  }

  // Merge options from theme's info file with the defaults
  $set_options = array_merge(_fergus_default_haml_options(), $options);

  // // Allow modules & running theme to alter Haml parser options
  // fergus_alter('haml_options', $set_options, $theme);

  return $set_options;
}
