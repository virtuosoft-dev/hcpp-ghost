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
            $hcpp->add_action( 'render_page', [ $this, 'render_page' ] );
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
            $hcpp->( 'ghost->setup' );
            $hcpp->log( $cmd );
            $r = shell_exec( $cmd );
            $hcpp->log( $r );

            // Copy over ghost config files
            $hcpp->copy_folder( __DIR__ . '/nodeapp', $ghost_folder, $user );

            // Cleanup, allocate ports, prepare nginx and prepare to start services
            $hcpp->nodeapp->shutdown_apps( $nodeapp_folder );
            $hcpp->nodeapp->allocate_ports( $nodeapp_folder );
            $port = file_get_contents( "/usr/local/hestia/data/hcpp/ports/$user/$domain.ports" );
            $port = $hcpp->delLeftMost( $port, '$ghost_port ' );
            $port = $hcpp->getLeftMost( $port, ';' );

            // Fill out config.development.json and config.production.json
            $config = file_get_contents( $ghost_folder . '/config.development.json' );
            $config = str_replace( '%database_name%', $user . '_' . $options['database_name'], $config );
            $config = str_replace( '%database_user%', $user . '_' . $options['database_user'], $config );
            $config = str_replace( '%database_password%', $options['database_password'], $config );
            $config = str_replace( '%ghost_port%', $port, $config );
            $url = "http://$domain" . $subfolder;
            if ( is_dir( "/home/$user/conf/web/$domain/ssl") ) {
                $url = "https://$domain" . $subfolder;
            }
            $config = str_replace( '%ghost_url%', $url, $config );
            $config = str_replace( '%ghost_content%', $ghost_folder . '/content', $config );

            file_put_contents( $ghost_folder . '/config.development.json', $config );
            file_put_contents( $ghost_folder . '/config.production.json', $config );

            // Update proxy and restart nginx
            if ( $nodeapp_folder . '/' == $ghost_folder ) {
                $hcpp->run( "change-web-domain-proxy-tpl $user $domain NodeApp" );
            }else{
                $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                $hcpp->nodeapp->startup_apps( $nodeapp_folder );
                $hcpp->run( "restart-proxy" );
            }

            // Await startup of Ghost and POST credentials to complete setup
            $post_url = $url . '/ghost/#/setup';

            // Setup ghost if creds given
            // http://test3.openmy.info/ghost/#/setup
            // Start up ghost
            // export NODE_ENV=production;node current/index.js
        }

        // Customize the install page
        public function render_page( $args ) {
            global $hcpp;
            if ( strpos( $_SERVER['REQUEST_URI'], '/add/webapp/?app=Ghost&' ) === false ) return $args;
            $content = $args['content'];
            $user = trim($args['user'], "'");
            $shell = $hcpp->run( "list-user $user json")[$user]['SHELL'];

            // Suppress Data loss alert, and PHP version selector
            $content = '<style>.alert.alert-info.alert-with-icon{display:none;}</style>' . $content;
            if ( $shell != 'bash' ) {

                // Display bash requirement
                $content = '<style>.form-group{display:none;}</style>' . $content;
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'Cannot contiue. User "' . $user . '" must have bash login ability.</span>';
                $msg .= '<script>$(function(){$(".l-unit-toolbar__buttonstrip.float-right a").css("display", "none");});</script>';
            }elseif ( !is_dir('/usr/local/hestia/plugins/nodeapp') ) {
        
                // Display missing nodeapp requirement
                $content = '<style>.form-group{display:none;}</style>' . $content;
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'Cannot contiue. The Ghost Quick Installer requires the NodeApp plugin.</span>';
                $msg .= '<script>$(function(){$(".l-unit-toolbar__buttonstrip.float-right a").css("display", "none");});</script>';
            }else{
        
                // Display install information
                $msg = '<div style="margin-top:-20px;width:75%;"><span>';
                $msg .= 'The Ghost instance lives inside the "nodeapp" folder (adjacent to "public_html"). ';
                $msg .= 'It can be a standalone instance in the domain root, or in a subfolder using the ';
                $msg .= '<b>Install Directory</b> field below.</span><br><span style="font-style:italic;color:darkorange;">';
                $msg .= 'Files will be overwritten; be sure the specified <span style="font-weight:bold">Install Directory</span> is empty!</span></div><br>';
                
                // Enforce username and password, remove PHP version
                $msg .= '
                <script>
                    $(function() {
                        $("label[for=webapp_php_version]").parent().css("display", "none");
                        let borderColor = $("#webapp_ghost_username").css("border-color");
                        let toolbar = $(".l-center.edit").html();
                        function nr_validate() {
                            if ( $("#webapp_ghost_username").val().trim() == "" || $("#webapp_ghost_password").val().trim() == "" || $("#webapp_ghost_email").val().trim() == "" ) {
                                $(".l-unit-toolbar__buttonstrip.float-right a").css("opacity", "0.5").css("cursor", "not-allowed");
                                if ($("#webapp_ghost_username").val().trim() == "") {
                                    $("#webapp_ghost_username").css("border-color", "red");
                                }else{
                                    $("#webapp_ghost_username").css("border-color", borderColor);
                                }
                                if ($("#webapp_ghost_password").val().trim() == "") {
                                    $("#webapp_ghost_password").css("border-color", "red");
                                }else{
                                    $("#webapp_ghost_password").css("border-color", borderColor);
                                }
                                if ($("#webapp_ghost_email").val().trim() == "") {
                                    $("#webapp_ghost_email").css("border-color", "red");
                                }else{
                                    $("#webapp_ghost_email").css("border-color", borderColor);
                                }
                                return false;
                            }else{
                                $(".l-unit-toolbar__buttonstrip.float-right a").css("opacity", "1").css("cursor", "");
                                $("#webapp_ghost_username").css("border-color", borderColor);
                                $("#webapp_ghost_password").css("border-color", borderColor);
                                $("#webapp_ghost_email").css("border-color", borderColor);
                                return true;
                            }
                        };
        
                        // Override the form submition
                        $(".l-unit-toolbar__buttonstrip.float-right a").removeAttr("data-action").removeAttr("data-id").click(function() {
                            if ( nr_validate() ) {
                                $(".l-sort.clearfix").html("<div class=\"l-unit-toolbar__buttonstrip\"></div><div class=\"l-unit-toolbar__buttonstrip float-right\"><div><div class=\"timer-container\" style=\"float:right;\"><div class=\"timer-button spinner\"><div class=\"spinner-inner\"></div><div class=\"spinner-mask\"></div> <div class=\"spinner-mask-two\"></div></div></div></div></div>");
                                $("#vstobjects").submit();
                            }
                        });
                        $("#vstobjects").submit(function(e) {
                            if ( !nr_validate() ) {
                                e.preventDefault();
                            }
                        });
                        $("#webapp_ghost_username").blur(nr_validate).keyup(nr_validate);
                        $("#webapp_ghost_password").blur(nr_validate).keyup(nr_validate);
                        $("#webapp_ghost_email").blur(nr_validate).keyup(nr_validate);
                        $(".generate").click(function() {
                            setTimeout(function() {
                                nr_validate();
                            }, 500)
                        });
                        nr_validate();
                    });
                </script>
                ';
            }
            $content = str_replace( '<div class="app-form">', '<div class="app-form">' . $msg, $content );
            $args['content'] = $content;
            return $args;
        }
    }
    new Ghost();
}