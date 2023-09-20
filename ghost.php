<?php
/**
 * Extend the HestiaCP Pluginable object with our Ghost object for
 * allocating Ghost instances.
 * 
 * @version 1.0.0
 * @license GPL-3.0
 * @link https://github.com/virtuosoft-dev/hcpp-ghost
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
            $hcpp->add_action( 'hcpp_invoke_plugin', [ $this, 'setup' ] );
            $hcpp->add_action( 'hcpp_render_body', [ $this, 'hcpp_render_body' ] );
        }

        // Setup Ghost with the given user options
        public function setup( $args ) {
            if ( $args[0] != 'ghost_install' ) return $args;
            global $hcpp;
            $options = json_decode( $args[1], true );
            $user = $options['user'];
            $domain = $options['domain'];

            // Database connection details
            $dbUser = $user . '_' . $options['database_user'];
            $dbPassword = $options['database_password'];
            $dbName = $user . '_' . $options['database_name'];

            // Copy the Ghost files to the user folder
            $ghost_folder = $options['ghost_folder'];
            if ( $ghost_folder == '' || $ghost_folder[0] != '/' ) $ghost_folder = '/' . $ghost_folder;
            $nodeapp_folder = "/home/$user/web/$domain/nodeapp";
            $subfolder = $ghost_folder;
            $ghost_folder = $nodeapp_folder . $ghost_folder;

            // Create the nodeapp folder 
            $cmd = "mkdir -p " . escapeshellarg( $ghost_folder ) . " ; ";
            $cmd .= "chown -R $user:$user " . escapeshellarg( $nodeapp_folder ) . " ; ";
            $cmd .= 'runuser -l ' . $user . ' -c "cd ' . escapeshellarg( $ghost_folder ) . ' && ';
            $cmd .= 'export NVM_DIR=/opt/nvm && source /opt/nvm/nvm.sh && nvm use v18 && ';
            $cmd .= 'ghost install --url https://' . $domain . ' --db mysql --dbhost 127.0.0.1 --dbuser ';
            $cmd .= $dbUser . ' --dbpass ' . $dbPassword;
            $cmd .= ' --port 3306 --dbname ' . $dbName . ' --mail Sendmail';
            $cmd .= ' --process local --dir ' . $ghost_folder . ' --no-prompt --no-setup-nginx"';
            $hcpp->log( $cmd );
            $hcpp->log( shell_exec( $cmd ) );

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
            $config = str_replace( '%database_name%', $dbName, $config );
            $config = str_replace( '%database_user%', $dbUser, $config );
            $config = str_replace( '%database_password%', $dbPassword, $config );
            $config = str_replace( '%ghost_port%', $port, $config );
            $url = "http://$domain" . $subfolder;
            if ( is_dir( "/home/$user/conf/web/$domain/ssl") ) {
                $url = "https://$domain" . $subfolder;
            }
            $config = str_replace( '%ghost_url%', $url, $config );
            $config = str_replace( '%ghost_content%', rtrim($ghost_folder, '/') . '/content', $config );

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

            // Check if default title and name are set in the Ghost database
            sleep(5);
            $dbUpdateRetries = 10;
            while ( $dbUpdateRetries-- > 0 ) {
                try {
                    // Initialize a PDO connection
                    $pdo = new PDO("mysql:host=localhost;dbname=$dbName", $dbUser, $dbPassword);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Check for the presence of 'title' in the 'settings' table
                    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'title'");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && isset($result['value']) && !empty($result['value'])) {
                        
                        // Check the presence of 'name' in the 'users' table
                        $stmt = $pdo->prepare("SELECT name FROM users WHERE `id` = 1");
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && isset($result['name']) && !empty($result['name'])) {
                            $dbUpdateRetries = 0;
                        } else {
                            $hcpp->log("Ghost database not ready yet; no name found. Retrying in 5 seconds.");
                            sleep(5);
                        }
                    } else {
                        $hcpp->log("Ghost database not ready yet; no title found. Retrying in 5 seconds.");
                        sleep(5);
                    }
                } catch (PDOException $e) {

                    // Handle database errors
                    $hcpp->log( "Ghost database not ready yet; error: " . $e->getMessage() );
                    sleep(5);
                }
            }

            // Update the Ghost database with our title, name, email, and password.
            try {
                // Initialize a PDO connection
                $pdo = new PDO("mysql:host=localhost;dbname=$dbName", $dbUser, $dbPassword);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Calculate the slug and hash the password
                $hash = password_hash( $options['ghost_password'], PASSWORD_BCRYPT, ['cost' => 10] );
                $slug = $slug = strtolower( $options['ghost_fullname'] ); 
                $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
                $slug = trim( $slug, '-' );
                $slug = preg_replace( '/-+/', '-', $slug );
                                
                // SQL statements with placeholders
                $updateSettingsSQL = "UPDATE `settings` SET `value` = :title WHERE `key` = 'title';";
                $updateUsersSQL = "UPDATE `users` SET `name` = :name, `slug` = :slug, `email` = :email, `password` = :password, `status` = 'active' WHERE `id` = 1;";
            
                // Prepare and execute the first SQL statement
                $stmtSettings = $pdo->prepare($updateSettingsSQL);
                $stmtSettings->bindParam(':title', $options['ghost_title']);
                $stmtSettings->execute();
            
                // Prepare and execute the second SQL statement
                $stmtUsers = $pdo->prepare($updateUsersSQL);
                $stmtUsers->bindParam(':name', $options['ghost_fullname']);
                $stmtUsers->bindParam(':slug', $slug);
                $stmtUsers->bindParam(':email', $options['ghost_email']);
                $stmtUsers->bindParam(':password', $hash);
                $stmtUsers->execute();
            
                // Log the result or handle errors
                $hcpp->log( "Ghost database updated correctly." );
                $dbUpdateRetries = 0;
            } catch (PDOException $e) {    
                // Handle database errors
                $hcpp->log("Error: " . $e->getMessage());
            }             
        }

        // Customize the install page
        public function hcpp_render_body( $args ) {
            global $hcpp;
            if ( $args['page'] !== 'setup_webapp') return $args;
            if ( strpos( $_SERVER['REQUEST_URI'], '?app=Ghost' ) === false ) return $args;
            $content = $args['content'];
            $user = trim($args['user'], "'");
            $shell = $hcpp->run( "list-user $user json")[$user]['SHELL'];

            // Suppress Data loss alert, and PHP version selector
            $content = '<style>#vstobjects > div > div.u-mt20 > div:nth-child(6),.alert.alert-info{display:none;}</style>' . $content;
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
                $msg .= 'Please be patient; Ghost installation may take <span style="font-weight:bold;font-style:italic;color:darkorange;">';
                $msg .= 'several minutes to complete install!</span> You can check the domain in an adjacent window. ';
                $msg .= 'The specified <b>Install Directory</b> must be non-existent or empty.<br><br>';
                
                // Enforce username and password, remove PHP version
                $msg .= '
                <script>
                    document.addEventListener("DOMContentLoaded", function() { 
                        $("label[for=webapp_php_version]").parent().css("display", "none");
                        let borderColor = $("#webapp_ghost_fullname").css("border-color");
                        let toolbar = $(".l-center.edit").html();
                        function nr_validate() {
                            if ( $("#webapp_ghost_fullname").val().trim() == "" || $("#webapp_ghost_password").val().trim() == "" || $("#webapp_ghost_email").val().trim() == "" ) {
                                $(".l-unit-toolbar__buttonstrip.float-right a").css("opacity", "0.5").css("cursor", "not-allowed");
                                if ($("#webapp_ghost_fullname").val().trim() == "") {
                                    $("#webapp_ghost_fullname").css("border-color", "red");
                                }else{
                                    $("#webapp_ghost_fullname").css("border-color", borderColor);
                                }
                                if ($("#webapp_ghost_password").val().trim().length < 10) {
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
                                $("#webapp_ghost_fullname").css("border-color", borderColor);
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
                        $("#webapp_ghost_fullname").blur(nr_validate).keyup(nr_validate);
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
            if ( strpos( '<div class="app-form">', $content ) !== false ) {
                $content = str_replace( '<div class="app-form">', '<div class="app-form">' . $msg, $content ); // Hestia 1.6.X
            }else{
                $content = str_replace( '<h1 ', $msg . '<h1 style="padding-bottom:0;" ', $content ); // Hestia 1.7.X
            }
            $args['content'] = $content;
            return $args;
        }
    }
    new Ghost();
}
