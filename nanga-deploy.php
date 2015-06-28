<?php
/**
 * Plugin Name:       VG web things Deployer
 * Plugin URI:        https://github.com/Mallinanga/nanga-deploy
 * Description:       A command-line task to deploy to different environments.
 * Version:           1.0.0
 * Author:            Panos Paganis
 * Author URI:        https://github.com/Mallinanga
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    include 'nanga-command.php';
}
