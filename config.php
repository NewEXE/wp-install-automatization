<?php

return [

    /*
     * Script settings.
     *
     * Optional:
     * 'log_dir' Directory for creating log files (script's dir by default).
     */
    'log_dir'       => '',

    /*
     * Database settings.
     *
     * Optional:
     * 'db_host'     Database host ('localhost' by default).
     * 'db_user'     Database user ('root' by default).
     * 'db_password' Database password (empty string by default).
     */
    'db_host'       => 'localhost',
    'db_user'       => 'homestead',
    'db_password'   => 'secret',

    /*
     * WordPress and WooCommerce settings.
     *
     * Required:
     * 'wp_host' WP host for web access, with http(s):// (for example, 'http://wp.dev').
     *
     * Optional:
     * 'wp_dir'            Directory for WP install (script's subdirectory by default).
     * 'wp_admin_user'     WP Administrator's login ('admin' by default).
     * 'wp_admin_password' WP Administrator's password ('admin' by default).
     * 'wp_admin_email'    WP Administrator's email ('admin@example.com' by default).
     * 'wc_import_xml'     Path to XML file for import ('sample_products.xml' by default).
     */
    'wp_host'          => 'http://wp.dev',
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