<?php
/**
 * Script that install WordPress and WooCommerce
 * with Sample Data (WC products) and doing some other work.
 *
 * How to use:
 *
 * 1. Set up at least required params in config file:
 * return [
 *  // ...
 *
 *  'wp_host'         => 'http://wp-install-automatization.test',
 *  'plugin_location' => 'wces.zip',
 *
 *  // ...
 * ];
 *
 * 2. Execute this script.
 *
 * Required arguments:
 * --wp             WordPress version to install.
 * --wc             WooCommerce version to install.
 *
 * Optional arguments:
 * --force          Set up for passing "force" argument to WP-CLI commands (where it's possible).
 * --config_path    Set path to config file (default 'config.php' in script's folder).
 *
 * In this example WP will be available through this url:
 * http://wp-install-automatization.test/wp-4.8.6-wc-3.2.1/wp-admin
 *
 * Default admin credentials: admin / admin.
 * You can set another credentials in config file.
 */

/*
 * Received arguments validation and their assignment.
 */

$args = getopt('', [
	// Required arguments
	'wp:', 'wc:',

	// Optional arguments
	'force',
	'config_path:',
]);

if (! isset($args['wp'], $args['wc'])) {
    echo outputString('Some of required arguments not passed');
    echo outputString('Usage: --wp=4.8.1 --wc=3.2.1');
    echo outputString('Do not forget set up config file');
    exit(2);
}

$configPath = isset($args['config_path']) ? $args['config_path'] : 'config.php';

$args = $args + require $configPath;

if (! empty($args['wp_host'])) {
    $parsed = parse_url($args['wp_host']);
    if (empty($parsed['scheme']) || empty($parsed['host'])) {
        echo outputString('Provide correct "wp_host" param in config file, with scheme (http:// or https://)');
        exit(2);
    }
} else {
    echo outputString('Required param "wp_host" missed (in config file)');
    exit(2);
}

if (! empty($args['plugin_location'])) {
    if (! is_file($args['plugin_location']))  {
        echo outputString("File {$args['plugin_location']} not exists. Provide correct 'plugin_location' param in config file. For example, 'wces.zip'");
        exit(2);
    }
} else {
    echo outputString('Required param "plugin_location" missed (in config file)');
    exit(2);
}

if (! empty($args['wp_dir'])) {
    if (! is_dir($args['wp_dir']) || ! is_writable($args['wp_dir']))  {
        echo outputString("Directory {$args['wp_dir']} not exists or not writable. Provide correct 'wp_dir' param in config file'");
        exit(2);
    }
}

if (! empty($args['wc_import_xml'])) {
    if (! is_file($args['wc_import_xml']))  {
        echo outputString("File {$args['wc_import_xml']} not exists. Provide correct 'wc_import_xml' param in config file. For example, 'sample_products.xml'");
        exit(2);
    }
}

if (! empty($args['wp_admin_email'])) {
	if (! filter_var($args['wp_admin_email'], FILTER_VALIDATE_EMAIL)) {
		echo outputString("Provide correct admin's email");
	    exit(2);
	}
}

// Script settings
$force      = (isset($args['force']));
$wpVersion  = $args['wp'];
$wcVersion  = $args['wc'];
$domainName = trim($args['wp_host'], '/');

$codeName = "wp-$wpVersion-wc-$wcVersion";

// DB settings
$dbParams = [
    'db'        => $codeName,
    'host'      => ! empty($args['db_host']) ? $args['db_host'] : 'localhost',
    'user'      => ! empty($args['db_user']) ? $args['db_user'] : 'root',
    'password'  => ! empty($args['db_password']) ? $args['db_password']: '',
];

// WP installation settings
$wpDir          = ! empty($args['wp_dir']) ? rtrim($args['wp_dir'], '/\\') . DIRECTORY_SEPARATOR . $codeName : $codeName;
$url            = "$domainName/$codeName";
$title          = "WP v$wpVersion, WC v$wcVersion";
$adminUser      = ! empty($args['wp_admin_user']) ? $args['wp_admin_user'] : 'admin';
$adminPassword  = ! empty($args['wp_admin_password']) ? $args['wp_admin_password'] : 'admin';
$adminEmail     = ! empty($args['wp_admin_email']) ? $args['wp_admin_email'] : 'admin@example.com';

$xmlFilePath    = $args['wc_import_xml'];

// Plugin automatization settings
$pluginPath = ! empty($args['plugin_location']) ? $args['plugin_location'] : '';

/*
 * Get script environment info
 */

$cmd         = 'wp cli version';
$cmdOutput   = [];
$returnedVar = 0;
$cliVersion  = exec($cmd, $cmdOutput, $returnedVar);

commandErrorHandler($cmd, $returnedVar);

echo outputString('Script environment:', false);

echo outputString('PHP '. phpversion());
echo outputString($cliVersion);

/*
 * Create dir and DB
 */

echo outputTitle('Creating dir and DB');

if (! is_dir($wpDir)) {
    if (! mkdir($wpDir, 0755)) {
	    commandErrorHandler("mkdir($wpDir, 0755)", 1);
    }
}

try {
    $dbh = new PDO("mysql:host={$dbParams['host']}", $dbParams['user'], $dbParams['password']);
    $dbh->exec("set names utf8");
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE DATABASE IF NOT EXISTS `{$dbParams['db']}`";
    $dbh->exec($sql);
} catch (PDOException $e) {
    echo outputString('Database error: ' .  $e->getMessage());
    exit(2);
}

echo outputString('Successfully created');

/*
 * Install and configure WP
 */

$cmd1 = "wp core download --path='$wpDir' --version='$wpVersion'" . ($force ? ' --force' : '');
$cmd2 = "wp config create --path='$wpDir' --dbname='{$dbParams['db']}' --dbuser='{$dbParams['user']}' --dbpass='{$dbParams['password']}' --dbhost='{$dbParams['host']}' --dbcharset='utf8'" . ($force ? ' --force' : '');
$cmd3 = "wp core install --path='$wpDir' --url='$url' --title='$title' --admin_user='$adminUser' --admin_password='$adminPassword' --admin_email='$adminEmail' --skip-email";

echo outputTitle('Installing and configuring WordPress');

passthru($cmd1);

passthru($cmd2);

passthru($cmd3, $returnedVar);
commandErrorHandler($cmd3, $returnedVar, 'WordPress not installed');

/*
 * Install and configure WC
 */

echo outputTitle('Installing and configuring WooCommerce');

$cmd1 = "wp plugin install woocommerce --path='$wpDir' --version=$wcVersion --activate"  . ($force ? ' --force' : '');
$cmd2 = "wp plugin install wordpress-importer --path='$wpDir' --activate" . ($force ? ' --force' : '');
$cmd3 = "wp theme install storefront --path='$wpDir' --activate" . ($force ? ' --force' : '');

passthru($cmd1, $returnedVar);
commandErrorHandler($cmd1, $returnedVar);

passthru($cmd2, $returnedVar);
commandErrorHandler($cmd2, $returnedVar);

passthru($cmd3, $returnedVar);
commandErrorHandler($cmd3, $returnedVar);

/*
 * Import products from sample_products.xml
 */

echo outputTitle('Importing products from sample_products.xml');

// todo add command for deleting all products

if (! file_exists($xmlFilePath)) {
    echo outputString("File $xmlFilePath not exists");
    exit(2);
}
$cmd = "wp import $xmlFilePath --authors=create --path='$wpDir'";
echo exec($cmd, $cmdOutput, $returnedVar) . PHP_EOL;
commandErrorHandler($cmd, $returnedVar);

/*
 * Install plugin from local zip
 */

if (empty($pluginPath)) {
    echo outputString("Provide correct plugin path in config");
    exit(2);
}

echo outputTitle("Installing plugin from zip: $pluginPath");

if (is_file($pluginPath)) {
	$cmd = "wp plugin install $pluginPath --path='$wpDir' --activate" . ($force ? ' --force' : '');
    passthru($cmd);
} else {
	commandErrorHandler("is_file($pluginPath)", 1, 'Provide correct path to plugin\'s ZIP-file');
}

$cmd = "wp plugin activate wces --path='$wpDir'";
exec($cmd, $cmdOutput, $returnedVar);
commandErrorHandler($cmd, $returnedVar);


/*
 * Include WP core
 */

echo outputTitle('Including WP core');

$cwd = getcwd();

if ( ! chdir( $wpDir ) ) {
	commandErrorHandler("chdir($wpDir)", 1, "chdir error (dir: $wpDir)");
}

if ( ! file_exists( 'wp-load.php' ) ) {
	commandErrorHandler("file_exists('wp-load.php')", 1, 'wp-load.php not exists');
}

// Connect to WP
define('WP_USE_THEMES', false);

// Suppress warnings in parent theme
if ( !isset($_SERVER['REMOTE_ADDR']) )      $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if ( !isset($_SERVER['REQUEST_METHOD']) )   $_SERVER['REQUEST_METHOD'] = 'GET';
if ( !isset($_SERVER['SERVER_NAME']) )      $_SERVER['SERVER_NAME'] = '';

require 'wp-load.php';

if (! defined('ABSPATH') ) {
    commandErrorHandler("defined('ABSPATH')", 1, 'ABSPATH not defined');
}

if (! function_exists('get_bloginfo')) {
	commandErrorHandler("function_exists('get_bloginfo')", 1, 'WordPress not included correctly');
}

$installedWpVersion = get_bloginfo('version');

echo outputString("WordPress v$installedWpVersion was included");

/*
 * Elasticsearch sync
 */

echo outputTitle('Elasticsearch synchronization');

$exec = [];
exec('wp wces status', $exec); // todo create one param value returns mode (wp wces status --es)
$esConnected = substr($exec[0], 25, 2) !== 'NO' ? true : false;

if (! $esConnected) {
	commandErrorHandler('wp wces status', 1, 'Elasticsearch not connected');
}

echo exec('wp wces index') . PHP_EOL;


/*
 * Testing
 */

echo outputTitle('Testing');

$tests = [
    'wp_versions_equals' => [
        'description' => 'Required and installed WP versions are equals',
        'passed' => false,
    ],
    'wc_versions_equals' => [
        'description' => 'Required and installed WC versions are equals',
        'passed' => false,
    ],
    'search_results_equals' => [
        'description' => 'Expected and received products are equals',
        'passed' => false,
    ],
];

$tests['wp_versions_equals']['passed'] = $wpVersion === $installedWpVersion;

$tests['wc_versions_equals']['passed'] = $wcVersion === WC()->version;

$products = wc_get_products([
	's' => 'Beanie with Logo'
]);

$receivedNames = [];
foreach ($products as $product) {
    $receivedNames[] = $product->get_name();
}

$expectedNames = [
    'WordPress Pennant',
    'Logo Collection',
    'Beanie with Logo',
    'T-Shirt with Logo',
    'Album',
    'Single',
    'Polo',
    'Long Sleeve Tee',
    'Hoodie with Pocket',
    'Hoodie with Zipper',
];

// if Elasticsearch is off:
// Beanie with Logo

$tests['search_results_equals']['passed'] = isArrayEquals($receivedNames, $expectedNames);

$passedCount = $failedCount = $allTestsCount = 0;

foreach ($tests as $test) {
    $allTestsCount++;

    $test['passed'] ? ++$passedCount : ++$failedCount;

    echo outputString($test['description'] . ' => ' . ($test['passed'] ? '✔ passed' : '× NOT PASSED'), false);
}

echo PHP_EOL;
if ($allTestsCount === $passedCount) {
    echo outputString('All tests have passed', false, false);
} elseif($allTestsCount === $failedCount) {
    echo outputString('All tests have failed', false, false);
} else {
	echo outputString('Some tests have failed', false, false);
}
echo outputString(" (passed $passedCount/$allTestsCount)");

chdir($cwd);

echo outputString(PHP_EOL . 'Script completed');

/**
 * @param string $str
 * @param bool $withDot
 * @param bool $withBreakline
 *
 * @return string
 */
function outputString($str, $withDot = true, $withBreakline = true) {
	if ($withDot) {
		$str = rtrim($str, '.') . '.';
	}
	if ($withBreakline) {
		$str = $str . PHP_EOL;
	}
	return $str;
}

/**
 * @param string $str
 *
 * @return string
 */
function outputTitle($str) {
	$str = rtrim($str, '.');

	//$outputDelimiter = str_repeat('=', 25) . PHP_EOL;
	$outputDelimiter = PHP_EOL;

	$str = $outputDelimiter . "* $str..." . PHP_EOL;

	return $str;
}

/**
 * Prints error and exit if returnedCode !== 0.
 *
 * @param string $cmd
 * @param int $returnedCode
 * @param string $errMsg
 *
 * @return void
 */
function commandErrorHandler($cmd, $returnedCode, $errMsg = '') {
	if ($returnedCode !== 0) {
		$backtrace = debug_backtrace();
		$line = $backtrace[0]['line'];

		$firstParamPosition = strpos($cmd, '--');

		$substrLength = ($firstParamPosition === false) ? strlen($cmd) : $firstParamPosition;

		$cmd = trim(substr($cmd, 0, $substrLength));

		if ($errMsg !== '') {
			echo "$errMsg." . PHP_EOL;
		}
		echo "Command '$cmd' returns error code: $returnedCode, exit. [line $line]" . PHP_EOL;
		exit(1);
	}
}

/**
 * @param array $a
 * @param array $b
 * @return bool
 */
function isArrayEquals($a, $b) {
    return (
        is_array($a)
        && is_array($b)
        && count($a) === count($b)
        && array_diff($a, $b) === array_diff($b, $a)
    );
}