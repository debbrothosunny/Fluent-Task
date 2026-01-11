<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'fluent_task' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '#I.]`Y<#^cVJ7cM)DrAS12I(>A(P.UPh8*+5Zd&}#^;xUjq*YVRvcj/0G!gfX^zw' );
define( 'SECURE_AUTH_KEY',  '6DvUca{GNg_yi`uG2%z~W).Rd$jRCq;m@_%*ytaXa6{%&y L UKl`yO7C;W4UJ-B' );
define( 'LOGGED_IN_KEY',    '<lI~-jaKBeBNwQoD;,W]u>,<9h8{?* @/~8Ns>mT^e4|TJB@Yl]]0v$Wb$g9{021' );
define( 'NONCE_KEY',        '77Q{Ugk3$iXeX3Bybo1B|zOW,rkQ)zLkk;}TU]HoR(MxOTJ,#Q!yfYCtV72?OF+L' );
define( 'AUTH_SALT',        '3!gEe+qNJYMf$4USE=S~IjTuDv0I1Q)B/Pb &`T9I@IzM 58<^B&G@?0952nKPFm' );
define( 'SECURE_AUTH_SALT', '[^1:)[-@Msa;qp6o4,{(ca^gm;Rw,R&}smKrP#vH6)s]7PYUi3PZ4c_cLcQ]U]>g' );
define( 'LOGGED_IN_SALT',   '<ibcL$At:s5SU#/CU>D3>9v2VtC^,DIkp!!xnnB1c z>-.vWpW*;r.[L|BY;&Bgr' );
define( 'NONCE_SALT',       '2,}[q(xvG0)JpD_Gm9Z}Fbm9d9bu72/D=Yr`X(B~:`Q /b?w}GBQjm8wa#<X?3NS' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_a123_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);


/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';


/* কাস্টম সেটিংস: লোকালহোস্ট পাথ এবং স্ক্রিপ্ট লোডিং ফিক্স */
define( 'WP_HOME', 'http://localhost/fluent-task' );
define( 'WP_SITEURL', 'http://localhost/fluent-task' );
define( 'CONCATENATE_SCRIPTS', false );
define( 'SCRIPT_DEBUG', true );



