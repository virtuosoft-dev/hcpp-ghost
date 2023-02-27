<?php
/**
 * Plugin Name: Ghost
 * Plugin URI: https://github.com/steveorevo/hestiacp-ghost
 * Description: Ghost is a plugin for HestiaCP that allows you to Quick Install a Ghost instance.
 */

// Register the install and uninstall scripts
global $hcpp;
require_once( dirname(__FILE__) . '/ghost.php' );

$hcpp->register_install_script( dirname(__FILE__) . '/install' );
$hcpp->register_uninstall_script( dirname(__FILE__) . '/uninstall' );
