<?php
/**
 * This file provides a Wordlift_Ajax_Unit_Test_Case abstract class to support
 * WP's AJAX testing while configuring Wordlift.
 *
 * @since   3.8.0
 * @package Wordlift
 */

require_once( 'functions.php' );

/**
 * Define the {@link Wordlift_Ajax_Unit_Test_Case} class.
 *
 * @since 3.8.0
 */
abstract class Wordlift_Ajax_Unit_Test_Case extends WP_Ajax_UnitTestCase {
	/**
	 * The {@link Wordlift_UnitTest_Factory_For_Entity} instance.
	 *
	 * @since  3.14.0
	 * @access protected
	 * @var \Wordlift_UnitTest_Factory_For_Entity $entity_factory The {@link Wordlift_UnitTest_Factory_For_Entity} instance.
	 */
	protected $entity_factory;

	function setUp() {
		parent::setUp();

		delete_option( 'wl_db_version' );

		wl_core_update_db_check();

		// Default behaviour: push entities to the remote Linked Data store.
		Wordlift_Unit_Test_Case::turn_off_entity_push();

		// Configure WordPress with the test settings.
		wl_configure_wordpress_test();

		// Disable WordPress updates to avoid filtered wp_remote_* requests to
		// WordPress' own servers to fail miserably.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		$this->entity_factory = new Wordlift_UnitTest_Factory_For_Entity( $this->factory );

	}

	/**
	 * Mimic the ajax handling of admin-ajax.php
	 * Capture the output via output buffering, and if there is any, store
	 * it in $this->_last_message.
	 *
	 * @param string      $action
	 * @param string|null $body The http request body.
	 */
	protected function _handleAjax( $action, $body = null ) {

		// Start output buffering
		ini_set( 'implicit_flush', false );
		ob_start();

		// Build the request
		$_POST['action'] = $action;
		$_GET['action']  = $action;
		$_REQUEST        = array_merge( $_POST, $_GET );

		// Call the hooks
		do_action( 'admin_init' );
		do_action( 'wp_ajax_' . $_REQUEST['action'], $body );

		// Save the output
		$buffer = ob_get_clean();
		if ( ! empty( $buffer ) ) {
			$this->_last_response = $buffer;
		}
	}

}
