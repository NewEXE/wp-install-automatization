<?php
/**
 * Script that install WordPress and WooCommerce
 * with Sample Data (WC products).
 *
 * Usage example:
 * php install-wp-wc.php 4.8.6 3.2.1 http://wp-install-automatization.test
 * where:
 * 4.8.6 - WP version;
 * 3.2.1 - WC version;
 * provided url - WP will be available through this url plus slash and versions codename,
 * in this example: http://wp-install-automatization.test/wp-4.8.6-wc-3.2.1/wp-admin
 *
 * Admin credentials: admin / admin
 */

if (! isset($argv[1], $argv[2], $argv[3])) exit('Provide WordPress and WooCommerce versions and domain name.' . PHP_EOL);

$wpVersion = $argv[1];
$wcVersion = $argv[2];
$domainName = trim($argv[3], '/');

$codeName = "wp-$wpVersion-wc-$wcVersion";

/*
 * Script settings
 */

$force = false;

// DB settings
$dbParams = [
    'host'      => 'localhost',
    'user'      => 'homestead',
    'password'  => 'secret',
];

// WP installation settings
$url = "$domainName/$codeName";
$title = "WP v$wpVersion, WC v$wcVersion";
$adminUser = 'admin';
$adminPassword = 'admin';
$adminEmail = "admin@example.com";

/*
 * Get script environment info
 */

$cmd         = 'wp cli version';
$cmdOutput   = [];
$returnedVar = 0;
$cliVersion  = exec($cmd, $cmdOutput, $returnedVar);

commandErrorHandler($cmd, $returnedVar);

echo '* Script environment: ' . PHP_EOL;

echo 'PHP '. phpversion() . PHP_EOL;
echo $cliVersion . PHP_EOL;

/*
 * Create dir and DB
 */

echo outputDelimiter();
echo '* Creating dir and DB... ' . PHP_EOL;

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

echo 'Successfully created.' . PHP_EOL;

/*
 * Install and configure WP
 */

$cmd1 = "wp core download --path='$codeName' --version='$wpVersion'" . ($force ? ' --force' : '');
$cmd2 = "wp config create --path='$codeName' --dbname='$codeName' --dbuser='{$dbParams['user']}' --dbpass='{$dbParams['password']}' --dbhost='{$dbParams['host']}' --dbcharset='utf8'" . ($force ? ' --force' : '');
$cmd3 = "wp core install --path='$codeName' --url='$url' --title='$title' --admin_user='$adminUser' --admin_password='$adminPassword' --admin_email='$adminEmail' --skip-email";

echo outputDelimiter();
echo '* Installing and configuring WordPress...' . PHP_EOL;

passthru($cmd1);

passthru($cmd2);

passthru($cmd3, $returnedVar);
commandErrorHandler($cmd3, $returnedVar, 'WordPress not installed');

/*
 * Install and configure WC
 */

echo outputDelimiter();
echo '* Installing and configuring WooCommerce...' . PHP_EOL;

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

echo outputDelimiter();
echo '* Importing products from sample_products.xml...' . PHP_EOL;

$cmd = "wp import sample_products.xml --authors=create --path='$codeName'";
echo exec($cmd, $cmdOutput, $returnedVar) . PHP_EOL;
commandErrorHandler($cmd, $returnedVar);

/*
 * Install plugin from local zip
 */

if (! isset($pluginPath)) {
    $pluginPath = 'wces.zip';
}

echo outputDelimiter();
echo "* Installing plugin from zip: $pluginPath..." . PHP_EOL;

if (is_file($pluginPath)) {
	$cmd = "wp plugin install $pluginPath --path='$codeName' --activate" . ($force ? ' --force' : '');
    passthru($cmd);
} else {
    exit('Provide correct path to plugin\'s ZIP-file'. PHP_EOL);
}

/*
 * Include WP core
 */

echo outputDelimiter();
echo '* Including WP core...' . PHP_EOL;

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

echo "WordPress v$installedWpVersion was included." . PHP_EOL;

/*
 * Elasticsearch sync
 */

echo outputDelimiter();
echo '* Elasticsearch synchronization...' . PHP_EOL;

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

echo outputDelimiter();
echo '* Testing...' . PHP_EOL;

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

$args = [
    's' => 'Beanie with Logo'
];
$products = wc_get_products($args);

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

    echo $test['description'] . ' => ' . ($test['passed'] ? '✔ passed' : '× NOT PASSED') . PHP_EOL;
}

echo PHP_EOL;
if ($allTestsCount === $passedCount) {
    echo 'All tests have passed';
} elseif($allTestsCount === $failedCount) {
    echo 'All tests have failed';
} else {
	echo 'Some tests have failed';
}
echo " (passed $passedCount/$allTestsCount)." . PHP_EOL;

chdir($cwd);

echo PHP_EOL . 'Script completed.' . PHP_EOL;

/**
 * Returns script's section delimiter.
 *
 * @return string
 */
function outputDelimiter() {
//    return str_repeat('=', 25) . PHP_EOL;
    return PHP_EOL;
}

/**
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