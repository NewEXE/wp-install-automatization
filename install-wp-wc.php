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

if (! isset($argv[1], $argv[2], $argv[3])) exit('Provide WordPress and WooCommerce versions and domain name' . PHP_EOL);

echo 'Initialization...' . PHP_EOL;

$wpVersion = $argv[1];
$wcVersion = $argv[2];
$domainName = trim($argv[3], '/');

$cliVersion = exec('wp cli version');

echo $cliVersion . PHP_EOL;

$codeName = "wp-$wpVersion-wc-$wcVersion";

/*
 * Create dir
 */
if (! is_dir($codeName)) {
    mkdir($codeName, 0755);
}

/*
 * Create DB
 */
$dbParams = [
    'host'      => 'localhost',
    'user'      => 'homestead',
    'password'  => 'secret',
];

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

$url = "$domainName/$codeName";
$title = "WP v$wpVersion, WC v$wcVersion";
$adminUser = 'admin';
$adminPassword = 'admin';
$adminEmail = "admin@example.com";

$cmd1 = "wp core download --path='$codeName' --version='$wpVersion' --force";
$cmd2 = "wp config create --path='$codeName' --dbname='$codeName' --dbuser='{$dbParams['user']}' --dbpass='{$dbParams['password']}' --dbhost='{$dbParams['host']}' --dbcharset='utf8' --force";
$cmd3 = "wp core install --path='$codeName' --url='$url' --title='$title' --admin_user='$adminUser' --admin_password='$adminPassword' --admin_email='$adminEmail' --skip-email";

passthru($cmd1);
passthru($cmd2);
passthru($cmd3);

/*
 * Install WC and demo products
 */

passthru("wp plugin install woocommerce --path='$codeName' --version=$wcVersion --force --activate");

$cwd = getcwd();
chdir($codeName);

//passthru("wp wc");

chdir($cwd);

/*
 * Install plugin from local zip
 */

if (! isset($pluginPath)) {
    $pluginPath = 'wces.zip';
}

if (is_file($pluginPath)) {
    echo "Installing plugin from zip: $pluginPath..." . PHP_EOL;
    passthru("wp plugin install $pluginPath --path='$codeName' --force --activate");
} else {
    exit('Provide correct path to plugin\'s ZIP-file');
}

echo 'Script completed.' . PHP_EOL;