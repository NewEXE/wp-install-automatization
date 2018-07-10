<?php
/**
 * Script that install WordPress and Woocommerce
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

$cliVersion = exec('wp cli version');

echo '* Script environment' . PHP_EOL;

echo 'PHP '. phpversion() . PHP_EOL;
echo $cliVersion . PHP_EOL;

/*
 * Create dir and DB
 */

if (! is_dir($codeName)) {
    mkdir($codeName, 0755);
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

/*
 * Install WP
 */

$cmd1 = "wp core download --path='$codeName' --version='$wpVersion'" . ($force ? ' --force' : '');
$cmd2 = "wp config create --path='$codeName' --dbname='$codeName' --dbuser='{$dbParams['user']}' --dbpass='{$dbParams['password']}' --dbhost='{$dbParams['host']}' --dbcharset='utf8'" . ($force ? ' --force' : '');
$cmd3 = "wp core install --path='$codeName' --url='$url' --title='$title' --admin_user='$adminUser' --admin_password='$adminPassword' --admin_email='$adminEmail' --skip-email";

echo outputDelimiter();
echo '* Installing and configuring WordPress' . PHP_EOL;

passthru($cmd1);
passthru($cmd2);
passthru($cmd3);

/*
 * Install WC and demo products
 */

echo outputDelimiter();
echo '* Installing and configuring WooCommerce, importing products' . PHP_EOL;

passthru("wp plugin install woocommerce --path='$codeName' --version=$wcVersion --activate"  . ($force ? ' --force' : ''));

passthru("wp plugin install wordpress-importer --path='$codeName' --activate" . ($force ? ' --force' : ''));

echo 'Importing products...' . PHP_EOL;

$cmd4 = "wp import sample_products.xml --authors=create --path='$codeName'";
echo exec($cmd4) . PHP_EOL;

echo outputDelimiter();

/*
 * Install plugin from local zip
 */

if (! isset($pluginPath)) {
    $pluginPath = 'wces.zip';
}

echo "* Installing plugin from zip: $pluginPath..." . PHP_EOL;

if (is_file($pluginPath)) {
    passthru("wp plugin install $pluginPath --path='$codeName' --activate" . ($force ? ' --force' : ''));
} else {
    exit('Provide correct path to plugin\'s ZIP-file');
}

echo outputDelimiter();

/*
 * Elasticsearch sync
 */

$cwd = getcwd();
chdir($codeName);

$exec = [];
exec("wp wces status", $exec); // todo create one param value returns mode (wp wces status --es)
$esConnected = substr($exec[0], 25) === 'NO' ? false : true;

if (! $esConnected) exit('Elasticsearch not connected, exit.' . PHP_EOL);

echo '* Elasticsearch synchronization...' . PHP_EOL;

echo exec('wp wces index') . PHP_EOL;

chdir($cwd);

echo PHP_EOL . 'Script completed.' . PHP_EOL;

/**
 * Returns script's section delimiter.
 *
 * @return string
 */
function outputDelimiter() {
    return str_repeat('=', 25) . PHP_EOL;
}