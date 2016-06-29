<?php
/**
 * Plugin Name: Fergus - MtHaml Parser
 * Description: This plugin reads haml files as php.
 * Version: 1.0.0
 * Author: Nils Schönwald
 * Author URI: http://schoenwald.media
 * License: MIT
 */

/**
 * Initialize fergus
 */
function fergus_init($theme) {
  // Load drupal template.php
  $file = dirname($theme->filename) . '/template.php';
  if (file_exists($file)) {
    include_once "./$file";
  }

  // Set Haml parser options

  if (!empty($theme->info['fergus']['options']['haml'])) {
    _fergus_set_haml_options($theme, $theme->info['fergus']['options']['haml']);
  }
  else {
    _fergus_set_haml_options($theme);
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
function fergus_render_template($template, $variables) {

  // Retrieve options for the Haml parser
  $options = _fergus_get_haml_options();

  // Evaluate where the cached version of the parsed haml template should be
  $base_theme = basename($variables['directory']);
  $base_theme = basename(WP_CONTENT_DIR . '/fergus/');
  $template_cache = _fergus_cached_haml_path($template, $base_theme);
  $template_cache_fullpath = $template_cache['path'] . '/' . $template_cache['filename'];

  if ( !_fergus_cache_is_fresh($template_cache_fullpath, $template) ) {

    // Cached file doesn't exist or is old, generate a new file from haml template
    if ( file_prepare_directory( $template_cache['path'], FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS ) ) {

      $parser = new MtHaml\Environment('php', $options);
      $compiled = $parser->compileString(file_get_contents($template), $template );

      $write_to_cache = fopen($template_cache_fullpath, 'w');
      fwrite( $write_to_cache, $compiled );
      fclose( $write_to_cache );

    } else {

      drupal_set_message('Tried creating \'' . $template_cache['path'] . '\'. You must have your Drupal files directory correctly configured to use fergus.', 'error');

    }

  }

  // Extract Variables to a local namescape, needed for template rendering
  extract($variables, EXTR_SKIP);

  // Render template
  ob_start();
  include drupal_realpath($template_cache_fullpath);
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
    return new WP_Error('error', 'MtHaml library not found in "' . $mthaml_autoloader . '" folder. You can download an install a copy of it from its github project page: https://github.com/arnaud-lb/MtHaml');
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

  return array( 'path' => file_default_scheme() . '://fergus/' . implode('/', $template_source_path), 'filename' => $cached_filename );
}

/**
 * Check to see if cached file exist and is older than the source file
 */
function _fergus_cache_is_fresh($cached_file, $source_file) {
  if (file_exists($cached_file) && file_exists($source_file)) {
    if (drupal_realpath($cached_file) && drupal_realpath($source_file)) {
      if (filemtime($cached_file) > filemtime($source_file)) {
        return true;
      }
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


fergus_init(get_current_theme());
