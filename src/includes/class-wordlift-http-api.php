<?php
/**
 * Service: Http Api.
 *
 * Handle calls to `/wl-api`.
 *
 * See https://make.wordpress.org/plugins/2012/06/07/rewrite-endpoints-api/.
 *
 * @since 3.15.3
 */

/**
 * Define the {@link Wordlift_Http_Api} class.
 *
 * @since 3.15.3
 */
class Wordlift_Http_Api {

	/**
	 * A {@link Wordlift_Log_Service} instance.
	 *
	 * @since 3.15.3
	 *
	 * @var \Wordlift_Log_Service $log A {@link Wordlift_Log_Service} instance.
	 */
	private $log;

	/**
	 * Create a {@link Wordlift_End_Point} instance.
	 *
	 * @since 3.15.3
	 */
	public function __construct() {

		$this->log = Wordlift_Log_Service::get_logger( get_class() );

		add_action( 'init', array( $this, 'add_rewrite_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_action( 'admin_post_wl_hello_world', array( $this, 'hello_world' ) );
		add_action( 'admin_post_nopriv_wl_hello_world', array( $this, 'nopriv_hello_world' ) );

	}

	/**
	 * Add the `wl-api` rewrite end-point.
	 *
	 * @since 3.15.3
	 */
	public function add_rewrite_endpoint() {

		add_rewrite_endpoint( 'wl-api', EP_ROOT );
		$this->ensure_rewrite_rules_are_flushed();

	}

	/**
	 * Handle `template_redirect` hooks.
	 *
	 * @since 3.15.3
	 */
	public function template_redirect() {

		global $wp_query;

		if ( ! isset( $wp_query->query_vars['wl-api'] ) ) {
			$this->log->trace( 'Skipping, not a `wl-api` call.' );

			return;
		}

		$this->do_action( $_REQUEST['action'] );

		exit;

	}

	/**
	 * Do the requested action.
	 *
	 * @since 3.15.3
	 *
	 * @param string $action The action to execute.
	 */
	private function do_action( $action ) {

		if ( empty( $action ) ) {
			return;
		}

		if ( ! wp_validate_auth_cookie( '', 'logged_in' ) ) {
			/**
			 * Fires on a non-authenticated admin post request for the given action.
			 *
			 * The dynamic portion of the hook name, `$action`, refers to the given
			 * request action.
			 *
			 * @since 2.6.0
			 */
			do_action( "admin_post_nopriv_{$action}" );
		} else {
			/**
			 * Fires on an authenticated admin post request for the given action.
			 *
			 * The dynamic portion of the hook name, `$action`, refers to the given
			 * request action.
			 *
			 * @since 2.6.0
			 */
			do_action( "admin_post_{$action}" );
		}

	}

	/**
	 * Test function, anonymous.
	 *
	 * @since 3.15.3
	 */
	public function nopriv_hello_world() {

		wp_die( 'Hello World! (from anonymous)' );

	}

	/**
	 * Test function, authenticated.
	 *
	 * @since 3.15.3
	 */
	public function hello_world() {

		wp_die( 'Hello World! (from authenticated)' );

	}

	/**
	 * Ensure that the rewrite rules are flushed the first time.
	 *
	 * @since 3.15.3
	 */
	public static function ensure_rewrite_rules_are_flushed() {

		if ( 1 !== get_option( 'wl_http_api' ) ) {
			flush_rewrite_rules();
			add_option( 'wl_http_api', 1 );
		}

	}

}
