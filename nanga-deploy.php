<?php
/**
 * Plugin Name:       VG web things Deployer
 * Plugin URI:        https://github.com/Mallinanga/nanga-deploy
 * Description:       A command-line task to deploy to different environments.
 * Version:           1.1.2
 * Author:            Panos Paganis
 * Author URI:        https://github.com/Mallinanga
 */
if ( ! defined('WPINC')) {
    die;
}
//@todo We're not sure if this Class is available.
//add_action('tgmpa_register', function () {
//    $plugins = array(
//        array(
//            'name'               => 'WP Migrate DB',
//            'slug'               => 'wp-migrate-db',
//            'required'           => true,
//            'force_activation'   => true,
//            'force_deactivation' => false,
//        ),
//    );
//    tgmpa($plugins);
//});
if (defined('WP_CLI') && WP_CLI) {
    include 'nanga-command.php';
}
require_once plugin_dir_path(__FILE__) . 'nanga-updates.php';
new Nanga_Deploy_Updates();
