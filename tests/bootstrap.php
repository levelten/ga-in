<?php
/**
 * PHPUnit bootstrap for unit tests (no WordPress loaded).
 *
 * @package GAinWP
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/' );
define( 'GAINWP_VERSION', '6.0.0-test' );
define( 'GAINWP_FILE', dirname( __DIR__ ) . '/gainwp.php' );

require __DIR__ . '/Unit/stubs.php';
