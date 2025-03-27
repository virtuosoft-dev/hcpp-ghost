<?php
/**
 * Extend the HestiaCP Pluginable object with our Ghost object for
 * allocating Ghost instances.
 * 
 * @author Virtuosoft/Stephen J. Carnam
 * @license AGPL-3.0, for other licensing options contact support@virtuosoft.com
 * @link https://github.com/virtuosoft-dev/hcpp-ghost
 * 
 */

if ( ! class_exists( 'Ghost') ) {
    class Ghost extends HCPP_Hooks {
        public $supported = ['20'];
        public $updating = false;

        /**
         * Customize Ghost install screen
         */ 
        public function hcpp_add_webapp_xpath( $xpath ) {
            if ( ! (isset( $_GET['app'] ) && $_GET['app'] == 'Ghost' ) ) return $xpath;
            global $hcpp;

            // Check for bash shell user
            $user = $_SESSION["user"];
            if ($_SESSION["look"] != "") {
                $user = $_SESSION["look"];
            }
            $domain = $_GET['domain'];
            $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $domain);
            $shell = $hcpp->run( "v-list-user $user json")[$user]['SHELL'];
            if ( $shell != 'bash' ) {
                $style = '<style>div.u-mb10{display:none;}</style>';
                $html = '<span class="u-mb10">Cannot continue. User "' . $user . '" must have bash login ability.</span>';
            }else{
                $style = '<style>#webapp_php_version, label[for="webapp_php_version"]{display:none;}</style>';
                $html =  '<div class="u-mb10">
                              The Ghost instance lives inside the "nodeapp" folder (next to "public_html"). It can be a
                              standalone instance in the domain root, or in a subfolder using the <b>Install Directory</b> 
                              field above.
                          </div>';
            }
            $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html );
            $xpath = $hcpp->insert_html( $xpath, '/html/head', $style );

            // Remove existing public_html related alert if present
            $alert_div = $xpath->query('//div[@role="alert"][1]');
            if ( $alert_div->length > 0 ) {
                $alert_div = $alert_div[0];
                $alert_div->parentNode->removeChild( $alert_div );
            }

            // Insert our own alert about non-empty nodeapp folder
            $folder = "/home/$user/web/$domain/nodeapp";
            if ( file_exists( $folder ) && iterator_count(new \FilesystemIterator( $folder, \FilesystemIterator::SKIP_DOTS)) > 0 ) {
                $html = '<div class="alert alert-info u-mb10" role="alert">
                        <i class="fas fa-info"></i>
                        <div>
                            <p class="u-mb10">Data Loss Warning!</p>
                            <p class="u-mb10">Your nodeapp folder already has files uploaded to it. The installer will overwrite your files and/or the installation might fail.</p>
                            <p>Please make sure ~/web/' . $domain . '/nodeapp is empty or an empty subdirectory is specified!</p>
                        </div>
                    </div>';
                $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $html, true );
            }else{
                $above = '<div class="alert alert-info u-mb10" role="alert">
                            <i class="fas fa-info"></i>
                            <div>
                                <p class="u-mb10">Important</p>
                                <p class="u-mb10">Be sure to visit your website\'s /ghost/ subfolder to setup the administrator account.</p>
                            </div>
                          </div>';
                $xpath = $hcpp->insert_html( $xpath, '//div[contains(@class, "form-container")]', $above, true );
            }
            return $xpath;
        }

        /**
         * Install, uninstall, or setup Ghost with the given options
         * This can be invoked from the command line v-invoke-plugin and
         * is used by the webapp installer.
         */
        public function hcpp_invoke_plugin( $args ) {
            if ( count( $args ) < 0 ) return $args;
            global $hcpp;

            // Setup Ghost with the supported NodeJS on the given domain 
            if ( $args[0] == 'ghost_setup' ) {
                $options = json_decode( $args[1], true );
                $hcpp->log( $options );
                $user = $options['user'];
                $domain = $options['domain'];
                $email = $options['ghost_email'];
                $nodejs_version = $this->supported[0];
                $ghost_folder = $options['ghost_folder'];
                if ( $ghost_folder == '' || $ghost_folder[0] != '/' ) $ghost_folder = '/' . $ghost_folder;
                $nodeapp_folder = "/home/$user/web/$domain/nodeapp";

                // Database connection details
                $dbUser = $user . '_' . $options['database_user'];
                $dbPassword = $options['database_password'];
                $dbName = $user . '_' . $options['database_name'];
                
                // Create parent nodeapp folder first this way to avoid CLI permissions issues
                mkdir( $nodeapp_folder, 0755, true );
                chown( $nodeapp_folder, $user );
                chgrp( $nodeapp_folder, $user );
                $ghost_folder = $nodeapp_folder . $ghost_folder;
                $ghost_root = $hcpp->delLeftMost( $ghost_folder, $nodeapp_folder ); 
                $hcpp->runuser( $user, "mkdir -p $ghost_folder" );

                // Copy over nodeapp files and content folders
                $hcpp->copy_folder( __DIR__ . '/nodeapp', $ghost_folder, $user );
                $hcpp->copy_folder( '/opt/ghost/content', $ghost_folder . '/content', $user );
                chmod( $nodeapp_folder, 0755 );

                // Create symbolic links
                $hcpp->runuser( $user, "ln -s /opt/ghost/current $nodeapp_folder/current" );
                $hcpp->runuser( $user, "ln -s /opt/ghost/content/themes/casper $nodeapp_folder/content/themes/casper" );
                $hcpp->runuser( $user, "ln -s /opt/ghost/content/themes/source $nodeapp_folder/content/themes/source" );

                // Update the .nvmrc file
                file_put_contents( $ghost_folder . '/.nvmrc', "v$nodejs_version" );

                // Cleanup, allocate ports, prepare nginx and start services
                $hcpp->nodeapp->shutdown_apps( $nodeapp_folder );
                $hcpp->nodeapp->allocate_ports( $nodeapp_folder );
                $port = file_get_contents( "/usr/local/hestia/data/hcpp/ports/$user/$domain.ports" );
                $port = $hcpp->delLeftMost( $port, '$ghost_port ' );
                $port = $hcpp->getLeftMost( $port, ';' );
    
                // Fill out config.development.json and config.production.json
                $config = file_get_contents( $ghost_folder . '/config.production.json' );
                $config = str_replace( '%database_name%', $dbName, $config );
                $config = str_replace( '%database_user%', $dbUser, $config );
                $config = str_replace( '%database_password%', $dbPassword, $config );
                $config = str_replace( '%ghost_port%', $port, $config );
                $config = str_replace( '%ghost_email%', $email, $config );
                $url = "http://$domain" . $subfolder;
                if ( is_dir( "/home/$user/conf/web/$domain/ssl") ) {
                    $url = "https://$domain" . $subfolder;
                }
                $config = str_replace( '%ghost_url%', $url, $config );
                $config = str_replace( '%ghost_content%', rtrim($ghost_folder, '/') . '/content', $config );
                file_put_contents( $ghost_folder . '/config.production.json', $config );
                file_put_contents( $ghost_folder . '/config.development.json', $config );

                // Update proxy and restart nginx
                if ( $nodeapp_folder . '/' == $ghost_folder ) {
                    $ext = $hcpp->run( "v-list-web-domain '$user' '$domain' json" )[$domain]['PROXY_EXT'];
                    $ext = str_replace( ' ', ',', $ext );
                    $hcpp->run( "v-change-web-domain-proxy-tpl '$user' '$domain' 'NodeApp' '$ext' 'no'" );
                }else{
                    $hcpp->nodeapp->generate_nginx_files( $nodeapp_folder );
                    $hcpp->nodeapp->startup_apps( $nodeapp_folder );
                }
                $hcpp->run( "v-restart-proxy" );
            }

            return $args;
        }

        /**
         * Check daily for Ghost updates and install them.
         */
        public function nodeapp_autoupdate() {
            
            // Get current ghost version
            global $hcpp;
            $cmd = 'su -s /bin/bash ghost -c "export NVM_DIR=/opt/nvm && source /opt/nvm/nvm.sh && nvm use ';
            $cmd .= $this->supported[0] . ' && cd /opt/ghost && ghost check-update"';
            $result = shell_exec( $cmd );
            $current = trim( $hcpp->getLeftMost( $hcpp->delLeftMost( $result, 'Current version: ' ), "\n" ) );
            $latest = trim( $hcpp->getLeftMost( $hcpp->delLeftMost( $result, 'Latest version: '), "\n" ) );
            $hcpp->log( 'Ghost on v' . $this->supported[0] . ': ' . $current . ' vs ' . $latest );
            if ( $current == $latest ) return;

            // Update Ghost
            $hcpp->nodeapp->do_maintenance( function( $pm2_list ) use ( $hcpp ) {
                $cmd = 'su -s /bin/bash ghost -c "export NVM_DIR=/opt/nvm && source /opt/nvm/nvm.sh && nvm use ';
                $cmd .= $this->supported[0] . ' && cd /opt/ghost && ghost install --dir /opt/ghost --no-prompt ';
                $cmd .= '--no-setup --no-restart --no-enable --no-checkmem --auto"';
                $hcpp->log( $cmd );
                $hcpp->log( shell_exec( $cmd ) );
            }, ['20'], ['ghost'] );
        }
    }
    global $hcpp;
    $hcpp->register_plugin( Ghost::class );
}