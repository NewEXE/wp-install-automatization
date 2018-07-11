<?php
/**
 * Script that install WordPress and WooCommerce
 * with Sample Data (WC products).
 *
 * Usage example:
 * php install-wp-wc.php --wp=4.8.6 --wc=3.2.1 --host=http://wp-install-automatization.test
 * OR without '=' arguments delimiter
 * php install-wp-wc.php --wp 4.8.6 --wc 3.2.1 --host http://wp-install-automatization.test
 *
 * In this example WP will be available through this url:
 * http://wp-install-automatization.test/wp-4.8.6-wc-3.2.1/wp-admin
 *
 * Default admin credentials: admin / admin
 */

/*
 * Received arguments validation and their assignment
 */

$args = getopt('', [
	// Required arguments
	'wp:', 'wc:', 'host:',

	// Optional arguments
	'force',
	'db_host:', 'db_user:', 'db_password:',
	'admin_user:', 'admin_password:', 'admin_email:'
]);

if (! isset($args['wp'], $args['wc'], $args['host'])) {
	exit(outputString('Provide WordPress version, WooCommerce versions and host name with http:// (https://)'));
}

if (isset($args['db_host'])) {
	$parsed = parse_url($args['db_host']);
	if (empty($parsed['scheme']) || empty($parsed['host'])) {
		exit(outputString('Provide correct host name with http:// (https://)'));
	}
}

if (isset($args['admin_email'])) {
	if (! filter_var($args['admin_email'], FILTER_VALIDATE_EMAIL)) {
		exit(outputString('Provide correct admin\'s email'));
	}
}

// Script settings
$force = (isset($args['force']));
$wpVersion  = $args['wp'];
$wcVersion  = $args['wc'];
$domainName = trim($args['host'], '/');

$codeName = "wp-$wpVersion-wc-$wcVersion";

// DB settings
$dbParams = [
    'host'      => isset($args['db_host']) ? $args['db_host'] : 'localhost',
    'user'      => isset($args['db_user']) ? $args['db_user'] : 'homestead',
    'password'  => isset($args['db_password']) ? $args['db_password']: 'secret',
];

// WP installation settings
$url            = "$domainName/$codeName";
$title          = "WP v$wpVersion, WC v$wcVersion";
$adminUser      = isset($args['admin_user']) ? $args['admin_user'] : 'admin';
$adminPassword  = isset($args['admin_password']) ? $args['admin_password'] : 'admin';
$adminEmail     = isset($args['admin_email']) ? $args['admin_email'] : 'admin@example.com';

/*
 * Get script environment info
 */

$cmd         = 'wp cli version';
$cmdOutput   = [];
$returnedVar = 0;
$cliVersion  = exec($cmd, $cmdOutput, $returnedVar);

commandErrorHandler($cmd, $returnedVar);

echo ltrim(outputTitle('Script environment'));

echo outputString('PHP '. phpversion());
echo outputString($cliVersion);

/*
 * Create dir and DB
 */

echo outputTitle('Creating dir and DB');

if (! is_dir($codeName)) {
    if (! mkdir($codeName, 0755)) {
	    commandErrorHandler("mkdir($codeName, 0755)", 1);
    }
}

try {
    $dbh = new PDO("mysql:host={$dbParams['host']}", $dbParams['user'], $dbParams['password']);
    $dbh->exec("set names utf8");
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE DATABASE IF NOT EXISTS `$codeName`";
    $dbh->exec($sql);
} catch (PDOException $e) {
    exit('Database error: ' .  $e->getMessage() . PHP_EOL);
}

echo outputString('Successfully created');

/*
 * Install and configure WP
 */

$cmd1 = "wp core download --path='$codeName' --version='$wpVersion'" . ($force ? ' --force' : '');
$cmd2 = "wp config create --path='$codeName' --dbname='$codeName' --dbuser='{$dbParams['user']}' --dbpass='{$dbParams['password']}' --dbhost='{$dbParams['host']}' --dbcharset='utf8'" . ($force ? ' --force' : '');
$cmd3 = "wp core install --path='$codeName' --url='$url' --title='$title' --admin_user='$adminUser' --admin_password='$adminPassword' --admin_email='$adminEmail' --skip-email";

echo outputTitle('Installing and configuring WordPress');

passthru($cmd1);

passthru($cmd2);

passthru($cmd3, $returnedVar);
commandErrorHandler($cmd3, $returnedVar, 'WordPress not installed');

/*
 * Install and configure WC
 */

echo outputTitle('Installing and configuring WooCommerce');

$cmd1 = "wp plugin install woocommerce --path='$codeName' --version=$wcVersion --activate"  . ($force ? ' --force' : '');
$cmd2 = "wp plugin install wordpress-importer --path='$codeName' --activate" . ($force ? ' --force' : '');
$cmd3 = "wp theme install storefront --path='$codeName' --activate" . ($force ? ' --force' : '');

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

$cmd = "wp import sample_products.xml --authors=create --path='$codeName'";
echo exec($cmd, $cmdOutput, $returnedVar) . PHP_EOL;
commandErrorHandler($cmd, $returnedVar);

/*
 * Install plugin from local zip
 */

if (! isset($pluginPath)) {
    $pluginPath = 'wces.zip';
}

echo outputTitle("Installing plugin from zip: $pluginPath");

if (is_file($pluginPath)) {
	$cmd = "wp plugin install $pluginPath --path='$codeName' --activate" . ($force ? ' --force' : '');
    passthru($cmd);
} else {
	commandErrorHandler("is_file($pluginPath)", 1, 'Provide correct path to plugin\'s ZIP-file');
}

/*
 * Include WP core
 */

echo outputTitle('Including WP core');

$cwd = getcwd();

if ( ! chdir( $codeName ) ) {
	commandErrorHandler("chdir($codeName)", 1, "chdir error (dir: $codeName)");
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
$esConnected = substr($exec[0], 25, 2) === 'NO' ? false : true;

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
		exit;
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