<?php

return [

    /*
     * Script settings.
     *
     * Optional:
     * 'logs_dir' Directory for creating log files (dir 'logs' if empty).
     * 'verbose' Set up to true for print advanced output.
     */
    'logs_dir'      => '',
    'verbose'       => true,

    /*
     * Database settings.
     *
     * Optional:
     * 'db_host'     Database host ('localhost' if empty).
     * 'db_user'     Database user ('root' if empty).
     * 'db_password' Database password (empty string if empty).
     */
    'db_host'       => 'localhost',
    'db_user'       => 'homestead',
    'db_password'   => 'secret',

    /*
     * WordPress and WooCommerce settings.
     *
     * Required:
     * 'wp_host'           WP host for web access, with http(s):// (for example, 'http://wp.dev').
     *
     * Optional:
     * 'wp_dir'            Directory for WP install (script's subdirectory if empty).
     * 'wp_admin_user'     WP Administrator's login ('admin' if empty).
     * 'wp_admin_password' WP Administrator's password ('admin' if empty).
     * 'wp_admin_email'    WP Administrator's email ('admin@example.com' if empty).
     * 'wc_import_xml'     Path to XML file for import ('sample_products.xml' if empty).
     */
    'wp_host'          => 'http://wp-install-automatization.test',
    'wp_dir'           => '',
    'wp_admin_user'    => '',
    'wp_admin_password'=> '',
    'wp_admin_email'   => '',
    'wc_import_xml'    => '',

    /*
     * Plugin automatization settings.
     *
     * Required:
     * 'plugin_location' Location of plugin's install ZIP.
     */
    'plugin_location'      => 'wces.zip',
];