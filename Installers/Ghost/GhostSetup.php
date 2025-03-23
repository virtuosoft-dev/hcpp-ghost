<?php

namespace Hestia\WebApp\Installers\Ghost;
use Hestia\WebApp\Installers\BaseSetup as BaseSetup;

class GhostSetup extends BaseSetup {
	protected $appInfo = [
		"name" => "Ghost",
		"group" => "cms",
		"enabled" => true,
		"version" => "latest",
		"thumbnail" => "ghost-thumb.png",
	];

	protected $appname = "ghost";
	protected $config = [
		"form" => [
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
		$hcpp->run( 'v-invoke-plugin ghost_setup ' . escapeshellarg( json_encode( $options ) ) );
		return true;
	}
}
