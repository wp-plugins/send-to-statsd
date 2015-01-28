<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Send to Statsd
 * Plugin URI:
 * Description:       Send statistics to a statsd daemon
 * Version:           1.0.0
 * Author:            Cyrus David
 * Author URI:        https://jcyr.us/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       send-to-statsd
 */

require 'vendor/autoload.php';

global $sts_defaults;
$sts_defaults = [
  'sts_general_options' => [
    'publish_post' => [
      'name' => 'Publish Post',
      'value' => 'off',
      'type' => 'checkbox'
    ],
    'load_time' => [
      'name' => 'Page Load Time',
      'value' => 'off',
      'type' => 'checkbox'
    ]
  ],
  'sts_server_options' => [
    'host' => [
      'name' => 'Host',
      'value' => '127.0.0.1',
      'type' => 'text'
    ],
    'port' => [
      'name' => 'Port',
      'value' => 8125,
      'type' => 'number'
    ]
  ]
];

register_activation_hook( __FILE__, function() {
  global $sts_defaults;
  foreach( $sts_defaults as $section => $fields ) {
    $options = get_option( $section );

    if ( false === $options || !is_array( $options ) ) {
      $options = array_map(function( $field ) {
        return $field['value'];
      }, $fields);
    } else {
      /*
      Iterate over each fields and make sure
      any new default field gets inserted.
       */
      foreach( $fields as $field => $properties ) {
        if ( array_key_exists( $field, $options ) ) continue;

        $options[$field] = $properties['value'];
      }
    }
    update_option( $section, $options );
  }
});

$sts_general_options = get_option( 'sts_general_options' );
$sts_server_options = get_option( 'sts_server_options' );

global $sts_statsd;
$sts_statsd = new \Domnikl\Statsd\Client(
  new \Domnikl\Statsd\Connection\UdpSocket($sts_server_options['host'], $sts_server_options['port'])
);

if( $sts_general_options['load_time'] === 'on' ){
  add_action( 'shutdown', function(){
    if( is_admin() ) return;
    global $sts_statsd;
    $load_time = round( 1000 * timer_stop( 0 ) );
    $sts_statsd->timing( 'wordpress.load_time', $load_time );
  });
}

if( $sts_general_options['publish_post'] === 'on' ){
  add_action( 'publish_post', function(){
    global $sts_statsd;
    $load_time = round( 1000 * timer_stop( 0 ) );
    $sts_statsd->increment( 'wordpress.post_publish' );
  });
}

add_action( 'admin_menu', function(){
  add_options_page(
    'Send to Statsd',
    'Send to Statsd',
    'manage_options',
    'send-to-statsd',
    function(){
      global $sts_defaults;
      require_once plugin_dir_path( __FILE__ ) . 'partial.options.php';
    }
  );
});

add_action( 'admin_init', function(){
  global $sts_defaults;

  function section_title( $section ){
    switch( $section ){
      case 'sts_general_options':
        return 'General';
      case 'sts_server_options':
        return 'Statsd Server';
    }
  }

  function section_description( $section ){
    switch( $section ){
      case 'sts_general_options':
        return function(){
          echo '<p>Tick events you would like to monitor and be sent to the statsd server.</p>';
        };
      case 'sts_server_options':
        return function(){
          echo '<p>Modify the following if your daemon is not listening on the default port or on another server.</p>';
        };
    }
  }

  function render_field( $type, $section, $field ){
    $options = get_option( $section );
    $val = $options ? ( array_key_exists( $field, $options ) ? $options[$field] : '' ) : '';

    switch ( $type ) {
      case 'checkbox':
        return function() use ($val, $type, $field, $section) {
          echo '<input type="checkbox" ' . checked( $val, 'on', false ) . ' name="' . $section . '[' . $field . ']">';
        };
      case 'text':
        return function() use ($val, $section, $field, $section) {
          echo '<input type="text" value="' . esc_attr( $val ) . '" name="' . $section . '[' . $field . ']">';
        };
      case 'number':
        return function() use ($val, $section, $field, $section) {
          echo '<input type="number" value="' . esc_attr( $val ) . '" name="' . $section . '[' . $field . ']">';
        };
    }
  }

  function validate( $section ){
    return function ( $new ) use ( $section ) {
      global $sts_defaults;
      $options = get_option( $section );
      foreach( $sts_defaults[$section] as $field => $opts ) {
        $options[$field] = is_array( $new ) && array_key_exists( $field, $new) ? $new[$field] : $opts['value'];
      }

      return $options;
    };
  }

  foreach( $sts_defaults as $section => $fields){
    add_settings_section( $section, section_title ( $section ), section_description( $section ), 'send-to-statsd' );

    foreach( $fields as $field => $properties ){
      add_settings_field( $field, $properties['name'], render_field( $properties['type'], $section, $field ), 'send-to-statsd', $section );
    }

    register_setting( 'send-to-statsd', $section, validate( $section ) );
  }
});
