<?php

namespace Hestia\WebApp\Installers\Ghost;
use Hestia\WebApp\Installers\BaseSetup as BaseSetup;
require_once( '/usr/local/hestia/web/pluginable.php' );

class GhostSetup extends BaseSetup {
	protected $appInfo = [
		"name" => "Ghost",
		"group" => "cms",
		"enabled" => true,
		"version" => "Latest",
		"thumbnail" => "ghost-thumb.png",
	];

	protected $appname = "ghost";
	protected $config = [
		"form" => [
			"ghost_title" => ["type" => "text", "value" => "", "placeholder" => "My Site Title", "label" => "Site Title"],
			"ghost_fullname" => ["value" => "", "placeholder" => "John Doe"],
			"ghost_password" => "password",
            "ghost_email" => ["value" => "", "placeholder" => "john@example.com"],
			"ghost_folder" => ["type" => "text", "value" => "", "placeholder" => "/", "label" => "Install Directory"],
		],
		"database" => true,
		"resources" => [
		],
		"server" => [
			"nginx" => [],
			"php" => [
				"supported" => ["7.3", "7.4", "8.0", "8.1", "8.2"],
			],
		],
	];

	public function install(array $options = null) {
		global $hcpp;

		$parse = explode( '/', $this->getDocRoot() );
		$options['user'] = $parse[2];
		$options['domain'] = $parse[4];
		$hcpp->run( 'invoke-plugin ghost_install ' . escapeshellarg( json_encode( $options ) ) );
		return true;
	}
}
