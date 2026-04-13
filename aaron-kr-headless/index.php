<?php
/**
 * Aaron KR Headless Theme — index.php
 *
 * This WordPress installation is headless. All content is served
 * through the REST API to the Next.js frontend at aaron.kr.
 *
 * If someone navigates directly to lab.aaron.kr they get redirected
 * to the real site. Change the URL if your frontend domain changes.
 */

$frontend_url = 'https://aaron.kr';
$path         = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

// Redirect wp-login, wp-admin, and REST API requests — don't touch those.
if (
    strpos( $path, '/wp-login' ) === false &&
    strpos( $path, '/wp-admin' ) === false &&
    strpos( $path, '/wp-json' )  === false
) {
    wp_redirect( $frontend_url, 301 );
    exit;
}
