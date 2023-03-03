<?php
/**
 * Extend the HestiaCP Pluginable object with our Ghost object for
 * allocating Ghost instances.
 * 
 * @version 1.0.0
 * @license GPL-3.0
 * @link https://github.com/steveorevo/hestiacp-ghost
 * 
 */

if ( ! class_exists( 'Ghost') ) {
    class Ghost {
        /**
         * Constructor, listen for the invoke, POST, and render events
         */
        public function __construct() {
            global $hcpp;
            $hcpp->ghost = $this;
            $hcpp->add_action( 'invoke_plugin', [ $this, 'setup' ] );
        }

        // Setup Ghost with the given user options
        public function setup( $args ) {
            if ( $args[0] != 'ghost_install' ) return $args;
            global $hcpp;
            $options = json_decode( $args[1], true );
            $user = $options['user'];
            $domain = $options['domain'];

            // Copy the Ghost files to the user folder
            $ghost_folder = $options['ghost_folder'];
            if ( $ghost_folder == '' || $ghost_folder[0] != '/' ) $ghost_folder = '/' . $ghost_folder;
            $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
            $subfolder = $ghost_folder;
            $ghost_folder = $nodeapp_folder . $ghost_folder;

            // Create the nodeapp folder 
            $cmd = "mkdir -p " . escapeshellarg( $ghost_folder ) . " && ";
            $cmd .= "chown -R $user:$user " . escapeshellarg( $nodeapp_folder );
            shell_exec( $cmd );

            // Copy over ghost core files
            $cmd = 'cd ' . escapeshellarg( $ghost_folder ) . ' && ';
            $cmd .= 'runuser -l ' . $user . ' -c "cp /opt/ghost/.ghost-cli ./" && ';
            $cmd .= 'runuser -l ' . $user . ' -c "cp -r /opt/ghost/content ./" && ';
            $cmd .= 'runuser -l ' . $user . ' -c "cp -r /opt/ghost/current ./"';
            shell_exec( $cmd );

            // Copy over nodebb config files
            $hcpp->copy_folder( __DIR__ . '/nodeapp', $nodebb_folder, $user );
        }

    }
    new Ghost();
}