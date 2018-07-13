<?php

return [

    /*
     * Script settings.
     *
     * 'log_dir' is script's dir by default.
     */
    'log_dir'       => '',

    /*
     * Database settings.
     *
     * 'db_host'     is 'localhost'.
     * 'db_user'     is 'root' by default.
     * 'db_password' is empty string by default.
     */
    'db_host'       => 'localhost',
    'db_user'       => 'homestead',
    'db_password'   => 'secret',

    /*
     * WordPress settings.
     *
     * 'wp_host' is required.
     *
     * 'wp_dir'            is script's subdirectory by default.
     * 'wp_admin_user'     is 'admin' by default.
     * 'wp_admin_password' is 'admin' by default.
     * 'wp_admin_email'    is 'admin@example.com' by default.
     */
    'wp_host'          => 'http://wp.dev',
    'wp_dir'           => '',
    'wp_admin_user'    => '',
    'wp_admin_password'=> '',
    'wp_admin_email'   => '',

    /*
     * Plugin automatization settings.
     *
     * 'plugin_location' is required.
     */
    'plugin_location'      => 'wces.zip',
];