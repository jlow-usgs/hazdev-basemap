<?php

date_default_timezone_set('UTC');

$OLD_PWD = $_SERVER['PWD'];

// work from lib directory
chdir(dirname($argv[0]));

if ($argv[0] === './pre-install.php' || $_SERVER['PWD'] !== $OLD_PWD) {
	// pwd doesn't resolve symlinks
	$LIB_DIR = $_SERVER['PWD'];
} else {
	// windows doesn't update $_SERVER['PWD']...
	$LIB_DIR = getcwd();
}
$APP_DIR = dirname($LIB_DIR);
$HTDOCS_DIR = $APP_DIR . '/htdocs';
$CONF_DIR = $APP_DIR . '/conf';

$HTTPD_CONF = $CONF_DIR . '/httpd.conf';
$CONFIG_FILE_INI = $CONF_DIR . '/config.ini';
$CONFIG_FILE_PHP = $CONF_DIR . '/config.inc.php';

chdir($LIB_DIR);

if (!is_dir($CONF_DIR)) {
	mkdir($CONF_DIR, 0755, true);
}

// Interactively prompts user for config. Writes CONFIG_FILE_INI
include_once 'configure.inc.php';

// Parse the configuration
include '../conf/config.inc.php';

//Make certain tile directory exists
if (!file_exists($TILE_DIR)) {
	mkdir($TILE_DIR, 0775, true);
}

include 'downloadLayers.php';

// Write the HTTPD configuration file
file_put_contents($HTTPD_CONF, '
	## autogenerated at ' . date('r') . '

	Alias ' . $MOUNT_PATH . ' ' . $DATA_DIR . '
	Alias ' . $MOUNT_PATH . '_htdocs ' . $HTDOCS_DIR . '

	RewriteEngine On

	# if file exists, serve it
	RewriteCond %{REQUEST_URI} ^' . $MOUNT_PATH . '/(.*(png|jpg))$
	RewriteCond ' . $DATA_DIR . '/%1 -f
	RewriteRule .* - [L,PT]

	# php script for retrieving mbtiles / esri map tiles
	RewriteRule ^' . $MOUNT_PATH . '/tiles/([^/]+)/(\d+)/(\d+)/(\d+)\.(png|jpg)$ ' .
			$MOUNT_PATH . '_htdocs/getTileImage.php?layer=$1&zoom=$2&x=$3&y=$4&ext=$5 [L,PT]
	RewriteRule ^' . $MOUNT_PATH . '/tiles/([^/]+)/(\d+)/(\d+)/(\d+)\.grid\.json$ ' .
			$MOUNT_PATH . '_htdocs/getTileGrid.php?layer=$1&zoom=$2&x=$3&y=$4 [L,PT,QSA]

	RewriteCond %{REQUEST_URI} ^' . $MOUNT_PATH . '(.*jpg)$
	RewriteCond ' . $DATA_DIR . '/%1 !-f
	RewriteRule .* ' . $MOUNT_PATH . '_htdocs/images/white-256x256.jpg [L,PT]

	RewriteCond %{REQUEST_URI} ^' . $MOUNT_PATH . '(.*png)$
	RewriteCond ' . $DATA_DIR . '/%1 !-f
	RewriteRule .* ' . $MOUNT_PATH . '_htdocs/images/clear-256x256.png [L,PT]

	<Location ' . $MOUNT_PATH . '>
		# apache 2.2
		<IfModule !mod_authz_core.c>
			Order allow,deny
			Allow from all

			<LimitExcept GET>
				Deny from all
			</LimitExcept>
		</IfModule>

		# apache 2.4
		<IfModule mod_authz_core.c>
			Require all granted

			<LimitExcept GET>
				Require all denied
			</LimitExcept>
		</IfModule>

		ExpiresActive on
		ExpiresDefault "access plus 1 years"
	</Location>

	<Location ' . $MOUNT_PATH . '_htdocs>
		# apache 2.2
		<IfModule !mod_authz_core.c>
			Order allow,deny
			Allow from all

			<LimitExcept GET>
				Deny from all
			</LimitExcept>
		</IfModule>

		# apache 2.4
		<IfModule mod_authz_core.c>
			Require all granted

			<LimitExcept GET>
				Require all denied
			</LimitExcept>
		</IfModule>

		ExpiresActive on
		ExpiresDefault "access plus 1 years"
	</Location>
');
