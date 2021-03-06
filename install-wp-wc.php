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
 *
 * Script return status codes:
 * 0 - in case of all tests have passed.
 * 1 - in case of critical internal WP-CLI command error.
 * 2 - in case of incorrect params passing.
 * 3 - in case of some or all tests have failed.
 */

/*
 * Set up PHP config settings.
 */

date_default_timezone_set('Europe/Kiev');

/*
 * Received arguments validation and their assignment.
 */

/**
 * Full log file path for current script's run.
 *
 * @var string
 */
global $logFile;

/**
 * Verbose mode flag.
 *
 * @var bool
 */
global $verbose;

$args = getopt('', [
	// Required arguments
	'wp:', 'wc:',

	// Optional arguments
	'force',
	'verbose',
	'config_path:',
]);

if (! isset($args['wp'], $args['wc'])) {
    echo outputError('Some of required arguments not passed');
    echo outputError('Usage: --wp=4.8.1 --wc=3.2.1');
    echo outputError('Do not forget set up config file');
    exit(2);
}

$verbose = isset($args['verbose']) ? $args['verbose'] : null;

$configPath = ! empty($args['config_path']) ? $args['config_path'] : 'config.php';

$config = require $configPath;

$args = $args + $config;

if (! empty($args['logs_dir']) ) {
    $args['logs_dir'] = realpath($args['logs_dir']);
    if (! is_dir($args['logs_dir']) || ! is_writable($args['logs_dir']) ) {
        echo outputError("Directory for logs {$args['logs_dir']} not exists or not writable. Provide correct 'logs_dir' param in config file");
        exit(2);
    }

    $logsDir = rtrim($args['logs_dir'], '/\\');
} else {
    $defaultLogsDir = realpath('logs');
    if (! is_dir($defaultLogsDir)) {
        if (! @mkdir($defaultLogsDir)) {
            echo outputError("mkdir('$defaultLogsDir') fail. Check script directory for write permissions");
            exit(2);
        }
    }

    $logsDir = $defaultLogsDir;
}

$logFile = $logsDir . DIRECTORY_SEPARATOR . date('Ymd-His') . '.log';

if (! empty($args['wp_host'])) {
    $parsed = parse_url($args['wp_host']);
    if (empty($parsed['scheme']) || empty($parsed['host'])) {
        echo outputError('Provide correct "wp_host" param in config file, with scheme (http:// or https://)');
        exit(2);
    }
} else {
    echo outputError('Required param "wp_host" missed (in config file)');
    exit(2);
}

if (! empty($args['plugin_location'])) {
    if (! is_file($args['plugin_location'])) {
        echo outputError("File {$args['plugin_location']} not exists. Provide correct 'plugin_location' param in config file. For example, 'wces.zip'");
        exit(2);
    }
} else {
    echo outputError('Required param "plugin_location" missed (in config file)');
    exit(2);
}

if (! empty($args['wp_dir'])) {
    if (! is_dir($args['wp_dir']) || ! is_writable($args['wp_dir'])) {
        echo outputError("Directory {$args['wp_dir']} not exists or not writable. Provide correct 'wp_dir' param in config file'");
        exit(2);
    }
}

if (! empty($args['wc_import_xml'])) {
    if (! is_file($args['wc_import_xml']))  {
        echo outputError("File {$args['wc_import_xml']} not exists. Provide correct 'wc_import_xml' param in config file. For example, 'sample_products.xml'");
        exit(2);
    }
}

if (! empty($args['wp_admin_email'])) {
	if (! filter_var($args['wp_admin_email'], FILTER_VALIDATE_EMAIL)) {
		echo outputError("Provide correct admin's email");
	    exit(2);
	}
}

// Script settings
$force = (isset($args['force']));

if (is_null($verbose)) {
    $verbose = (! empty($args['verbose'])) ? (bool) $args['verbose'] : false;
} else {
    $verbose = true;
}

$forceSuffix    = $force ? '--force' : '';
$devNullSuffix  = $verbose ? '' : '>/dev/null 2>/dev/null';

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
$wpDir          = ! empty($args['wp_dir']) ? rtrim($args['wp_dir'], '/\\') . DIRECTORY_SEPARATOR . $codeName : realpath($codeName);
$url            = "$domainName/$codeName";
$title          = "WP v$wpVersion, WC v$wcVersion";
$adminUser      = ! empty($args['wp_admin_user']) ? $args['wp_admin_user'] : 'admin';
$adminPassword  = ! empty($args['wp_admin_password']) ? $args['wp_admin_password'] : 'admin';
$adminEmail     = ! empty($args['wp_admin_email']) ? $args['wp_admin_email'] : 'admin@example.com';

$xmlFilePath    = ! empty($args['wc_import_xml']) ? $args['wc_import_xml'] : 'sample_products.xml';

// Plugin automatization settings
$pluginPath = ! empty($args['plugin_location']) ? $args['plugin_location'] : '';

/*
 * Get script environment info
 */

$cmd         = 'wp cli version';
$execOutput   = [];
$returnedVar = 0;
$cliVersion  = exec($cmd, $execOutput, $returnedVar);

commandErrorHandler($cmd, $returnedVar, $execOutput);
$execOutput = null;

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
} else {
    if($force) {
        customRmdir($wpDir);
    }
}

try {
    $dbh = new PDO("mysql:host={$dbParams['host']}", $dbParams['user'], $dbParams['password']);
    $dbh->exec("set names utf8");
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($force) {
        $dbh->exec("DROP DATABASE IF EXISTS `{$dbParams['db']}`");
    }

    $sql = "CREATE DATABASE IF NOT EXISTS `{$dbParams['db']}`";
    $dbh->exec($sql);
} catch (PDOException $e) {
    echo outputError('Database error: ' .  $e->getMessage());
    exit(2);
}

echo outputString('Successfully created');

/*
 * Install and configure WP
 */

$cmd1 = "wp core download --path='$wpDir' --version='$wpVersion' $forceSuffix $devNullSuffix";
$cmd2 = "wp config create --path='$wpDir' --dbname='{$dbParams['db']}' --dbuser='{$dbParams['user']}' --dbpass='{$dbParams['password']}' --dbhost='{$dbParams['host']}' --dbcharset='utf8' $forceSuffix $devNullSuffix";
$cmd3 = "wp core install --path='$wpDir' --url='$url' --title='$title' --admin_user='$adminUser' --admin_password='$adminPassword' --admin_email='$adminEmail' --skip-email $devNullSuffix";

echo outputTitle('Installing and configuring WordPress');

customPassthru($cmd1, $returnedVar);

customPassthru($cmd2);

customPassthru($cmd3, $returnedVar);
commandErrorHandler($cmd3, $returnedVar, 'WordPress not installed');

/*
 * Install and configure WC
 */

echo outputTitle('Installing and configuring WooCommerce');

$cmd1 = "wp plugin install woocommerce --path='$wpDir' --version=$wcVersion --activate $forceSuffix $devNullSuffix";
$cmd2 = "wp plugin install wordpress-importer --path='$wpDir' --activate $forceSuffix $devNullSuffix";
$cmd3 = "wp theme install storefront --path='$wpDir' --activate  $forceSuffix $devNullSuffix";
$cmd4 = "wp plugin install query-monitor --path='$wpDir' $forceSuffix $devNullSuffix"; // Plugin for web-interface debugging

customPassthru($cmd1, $returnedVar);
commandErrorHandler($cmd1, $returnedVar);

customPassthru($cmd2, $returnedVar);
commandErrorHandler($cmd2, $returnedVar);

$returnedVar = 0;
customPassthru($cmd3, $returnedVar);
commandErrorHandler($cmd3, $returnedVar);

customPassthru($cmd4);

/*
 * Import products from XML file
 */

echo outputTitle("Importing products from provided $xmlFilePath file");

// todo add command for deleting all products

if (! file_exists($xmlFilePath)) {
    echo outputError("File $xmlFilePath not exists");
    exit(2);
}

$cmd = "wp import $xmlFilePath --authors=create --path='$wpDir' $devNullSuffix";

$execOutput = [];
$exec = exec($cmd, $execOutput, $returnedVar);
echo outputString($exec);

commandErrorHandler($cmd, $returnedVar, $exec);
$execOutput = null;

/*
 * Install plugin from local zip
 */

if (empty($pluginPath)) {
    echo outputError("Provide correct 'plugin_path' in config");
    exit(2);
}

echo outputTitle("Installing plugin from zip: $pluginPath");

if (is_file($pluginPath)) {
	$cmd = "wp plugin install $pluginPath --path='$wpDir' --activate $forceSuffix $devNullSuffix";
    customPassthru($cmd);
} else {
	commandErrorHandler("is_file($pluginPath)", 1, 'Provide correct path to plugin\'s ZIP-file');
}

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

$execOutput = [];
$cmd = "wp wces status --only='is_connected'";
$exec = exec($cmd);

if ( $exec === 'false') {
	commandErrorHandler($cmd, 1, 'Elasticsearch not connected');
}
$execOutput = null;

$cmd = 'wp wces index';
$exec = exec($cmd);
echo outputString($exec);

echo outputString('Sleep 5 seconds after reindex...', false);
sleep(5);

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
    'search_results1_equals' => [
        'description' => 'Expected and received products are equals (1)',
        'passed' => false,
    ],
    'search_results2_equals' => [
        'description' => 'Expected and received products are equals (2)',
        'passed' => false,
    ],
];

$tests['wp_versions_equals']['passed'] = $wpVersion === $installedWpVersion;

$installedWcVersion = WC()->version;

$tests['wc_versions_equals']['passed'] = $wcVersion === $installedWcVersion;

$products = get_posts([
    's'         => 'Beanie with Logo',
    'post_type' => 'product',
]);

$receivedNames1 = [];
foreach ($products as $product) {
    $receivedNames1[$product->post_name] = $product->post_title;
}

$expectedNames1 = [
    'beanie-with-logo'  => 'Beanie with Logo',
    't-shirt-with-logo' => 'T-Shirt with Logo',
    'beanie'            => 'Beanie',
    'hoodie-with-pocket'=> 'Hoodie with Pocket',
    'hoodie-with-logo'  => 'Hoodie with Logo',
];

// if Elasticsearch is off:
// 'beanie-with-logo'  => 'Beanie with Logo'

$tests['search_results1_equals']['passed'] = isArrayEquals($receivedNames1, $expectedNames1);

$products = get_posts([
    's'           => 'Beanie with Logo',
    'post_type'   => 'product',
    'product_cat' => 'clothing',
]);

$receivedNames2 = [];
foreach ($products as $product) {
    $receivedNames2[$product->post_name] = $product->post_title;
}

$expectedNames2 = [
    'logo-collection' => 'Logo Collection',
];

$tests['search_results2_equals']['passed'] = isArrayEquals($receivedNames2, $expectedNames2);

$passedCount = $failedCount = $allTestsCount = 0;
$scriptResultCode = 0;

$logTestsData = '';
foreach ($tests as $test) {
    $allTestsCount++;

    $test['passed'] ? ++$passedCount : ++$failedCount;

    $str = outputString($test['description'] . ' => ' . ($test['passed'] ? '✔ passed' : '× NOT PASSED'), false);
    $logTestsData .= $str;
    echo $str;
}
logData($logTestsData);

$outputStuff = compact(
    'installedWpVersion',
    'installedWcVersion',
    'receivedNames1',
    'expectedNames1',
    'receivedNames2',
    'expectedNames2'
    );

$outputSummary = outputSummary($allTestsCount, $passedCount, $failedCount, $outputStuff);

echo $outputSummary['message'];

logData($outputSummary['message']);

exit($outputSummary['code']);

/**
 * @param string $cmd
 * @param int|null $returnVar
 *
 * @return void
 */
function customPassthru($cmd, &$returnVar = null) {
    global $verbose;

    if ($verbose) {
        passthru($cmd, $returnVar);
    } else {
        $tempOutput = [];
        exec($cmd, $tempOutput, $returnVar);
        $tempOutput = null;
        unset($tempOutput);
    }
}

/**
 * @param mixed $data
 *
 * @return void
 */
function logData($data) {
    global $logFile;

    if (is_null($logFile)) return;

    $data_type = gettype($data);
    if ( is_array($data) ) {
        $data = json_encode($data);
    } elseif ( is_object($data) ) {
        $data = print_r($data, true);
    } elseif (is_resource($data)) {
        $data_type .= ' (' . get_resource_type($data) . ')';
        $data = (string) $data;
    } else {
        if ( ! settype($data, 'string') ) {
            $data = 'Logger error: can not convert input data to string';
        }
    }
    $data = '[' . date('Y-m-d H:i:s') . ']' . " [$data_type] " . $data;
    $data .= PHP_EOL;

    file_put_contents($logFile, $data, FILE_APPEND);
}

/**
 * @param string $str
 * @param bool $withDot
 * @param bool $withBreakline
 *
 * @return string
 */
function outputString($str, $withDot = true, $withBreakline = true) {
    global $verbose;

    if (! $verbose) return '';

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
 * @param bool $withDot
 * @param bool $withBreakline
 *
 * @return string
 */
function outputError($str, $withDot = true, $withBreakline = true) {
    if ($withDot) {
        $str = rtrim($str, '.') . '.';
    }
    if ($withBreakline) {
        $str = $str . PHP_EOL;
    }
    logData($str);
    return $str;
}

/**
 * @param string $str
 *
 * @return string
 */
function outputTitle($str) {
    global $verbose;

    if (! $verbose) return '';

    $str = rtrim($str, '.');

	//$outputDelimiter = str_repeat('=', 25) . PHP_EOL;
	$outputDelimiter = PHP_EOL;

	$str = $outputDelimiter . "* $str..." . PHP_EOL;

	return $str;
}

function outputSummary($allTestsCount, $passedCount, $failedCount, $stuff) {
    /**
     * @var string $installedWpVersion
     * @var string $installedWcVersion
     *
     * @var array $receivedNames1
     * @var array $expectedNames1
     * @var array $receivedNames2
     * @var array $expectedNames2
     */
    extract($stuff);

    if (!isset($installedWpVersion, $installedWcVersion)) {
        commandErrorHandler('outputSummary', 1,
            'Missed required params for outputSummary call. Provide $installedWpVersion and $installedWcVersion in $stuff array');
    }

    $code = 0;

    $message = '';

    $message .= PHP_EOL;
    $message .= '----------------- ';
    if ($allTestsCount === $passedCount) {
        $code = 0;
        $message.= 'All tests have passed';
    } else {
        $code = 3;
        $message.= 'Some tests have failed. See log file for details';

        logData('receivedNames1: ');
        logData($receivedNames1);

        logData('expectedNames1: ');
        logData($expectedNames1);

        logData('receivedNames2: ');
        logData($receivedNames2);

        logData('expectedNames2');
        logData($expectedNames2);
    }

    $apacheVer = customApacheGetVersion();

    if (! $apacheVer) $apacheVer = 'unknown';

    $message .= " (passed $passedCount/$allTestsCount)" . PHP_EOL;
    $message .= 'Apache: ' . $apacheVer . PHP_EOL;
    $message .= 'PHP: ' . phpversion() . PHP_EOL;
    $message .= 'Elasticsearch: ' . exec("wp wces status --only='number'") . PHP_EOL;
    $message .= 'WordPress: ' . $installedWpVersion . PHP_EOL;
    $message .= 'WooCommerce: ' . $installedWcVersion . PHP_EOL;
    $message .= 'WCES: ' . wces()->get_version() . PHP_EOL;

    return compact('code', 'message');
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

		$logMsg = "Command '$cmd' returns error code: $returnedCode, exit. [line $line]" . PHP_EOL;
        $logMsg .= "$errMsg.";
        logData($logMsg);

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

/**
 * Fetch the Apache version
 * from apache_get_version() function (if available)
 * or from some Unix commands.
 *
 * @return string|false the Apache version on success or FALSE on failure.
 */
function customApacheGetVersion() {
    $apacheVer = false;

    if (function_exists('apache_get_version')) {
        $apacheVer = apache_get_version();
    } else {
        $commands = [
            'apache2 -v',
            'apache2ctl -v',
            'httpd -v',
            '/usr/sbin/apache2 -v',
            'dpkg -l | grep apache',
        ];

        foreach ($commands as $command) {
            $output = $returned = null;
            exec("$command 2>/dev/null",$output, $returned);
            if ($returned === 0 && ! empty($output)) {
                $apacheVer = $output[0];
                break;
            }
        }
    }

    return $apacheVer;
}

/**
 * Removes directory with files.
 *
 * @param string $dirName Path to the directory.
 * @return bool true on success or false on failure.
 */
function customRmdir($dirName) {
    if (empty($dirName) || $dirName === '/' || $dirName === DIRECTORY_SEPARATOR) return false;

    $returned = 1;
    $output = []; // for passing $returned as exec() third param
    if (PHP_OS !== 'Windows') {
        exec(sprintf("rm -rf --preserve-root %s", escapeshellarg($dirName)), $output, $returned);
    } else {
        exec(sprintf("rd /s /q %s", escapeshellarg($dirName)), $output, $returned);
    }
    $output = null;

    return $returned === 0;
}

function customRmdir2($dirName) {
    if (empty($dirName) || $dirName === '/' || $dirName === DIRECTORY_SEPARATOR) return false;

    $files = array_diff(scandir($dirName), array('.','..'));

    foreach ($files as $file) {
        $path = "$dirName/$file";

        is_dir($path) ? customRmdir($path) : unlink($path);
    }

    return rmdir($dirName);
}
