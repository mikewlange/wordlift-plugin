<?php
/*
Plugin Name: WordLift
Plugin URI: http://wordlift.it
Description: Supercharge your WordPress Site with Smart Tagging and #Schemaorg support - a brand new way to write, organise and publish your contents to the Linked Data Cloud.
Version: 3.0.0-SNAPSHOT
Author: InsideOut10
Author URI: http://www.insideout.io
License: APL
*/

// Include WordLift constants.
require_once( 'wordlift_constants.php' );

/**
 * Log to the debug.log file.
 *
 * @since 3.0.0
 *
 * @uses wl_write_log_handler to write the log output.
 *
 * @param string|mixed $log The log data.
 */
function wl_write_log( $log ) {

	$handler = apply_filters( 'wl_write_log_handler', null );

	$callers         = debug_backtrace();
	$caller_function = $callers[1]['function'];

	if ( is_null( $handler ) ) {
		wl_write_log_handler( $log, $caller_function );

		return;
	}

	call_user_func( $handler, $log, $caller_function );
}

/**
 * The default log handler prints out the log.
 *
 * @since 3.0.0
 *
 * @param string|array $log The log data.
 * @param string $caller The calling function.
 */
function wl_write_log_handler( $log, $caller = null ) {

	if ( true === WP_DEBUG ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( "[ $caller ] " . print_r( $log, true ) );
		} else {
			error_log( "[ $caller ] " . $log );
		}
	}

}


/**
 * Write the query to the buffer file.
 *
 * @since 3.0.0
 *
 * @param string $query A SPARQL query.
 */
function wl_queue_sparql_update_query( $query ) {

	$filename = WL_TEMP_DIR . WL_REQUEST_ID . '.sparql';
	file_put_contents( $filename, $query . "\n", FILE_APPEND );

	wl_write_log( "wl_queue_sparql_update_query [ filename :: $filename ]" );
}

/**
 * Execute the SPARQL query from the buffer saved for the specified request id.
 *
 * @param int $request_id The request ID.
 */
function wl_execute_saved_sparql_update_query( $request_id ) {

	$filename = WL_TEMP_DIR . $request_id . '.sparql';

	// If the file doesn't exist, exit.
	if ( ! file_exists( $filename ) ) {
		wl_write_log( "wl_execute_saved_sparql_update_query : file doesn't exist [ filename :: $filename ]" );

		return;
	}

	wl_write_log( "wl_execute_saved_sparql_update_query [ filename :: $filename ]" );

	// Get the query saved in the file.
	$query = file_get_contents( $filename );

	// Execute the SPARQL query.
	rl_execute_sparql_update_query( $query, false );

	// Reindex the triple store.
	wordlift_reindex_triple_store();

	// Delete the temporary file.
	unlink( $filename );
}

add_action( 'wl_execute_saved_sparql_update_query', 'wl_execute_saved_sparql_update_query', 10, 1 );

/**
 * Add buttons hook for the TinyMCE editor. This method is called by the WP init hook.
 */
function wordlift_buttonhooks() {

	// Only add hooks when the current user has permissions AND is in Rich Text editor mode
	if ( ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) && get_user_option( 'rich_editing' ) ) {
		add_filter( 'mce_external_plugins', 'wordlift_register_tinymce_javascript' );
		add_filter( 'mce_buttons', 'wordlift_register_buttons' );
	}
}

/**
 * Register the TinyMCE buttons. This method is called by the WP mce_buttons hook.
 *
 * @param array $buttons The existing buttons array.
 *
 * @return array The modified buttons array.
 */
function wordlift_register_buttons( $buttons ) {
	// push the wordlift button the array.
	array_push( $buttons, 'wordlift' );
	// push the create entity wordlift
	array_push( $buttons, 'wordlift_add_entity' );

	return $buttons;
}

/**
 * Load the TinyMCE plugin. This method is called by the WP mce_external_plugins hook.
 *
 * @param array $plugin_array The existing plugins array.
 *
 * @return array The modified plugins array.
 */
function wordlift_register_tinymce_javascript( $plugin_array ) {
	// add the wordlift plugin.
	$plugin_array['wordlift'] = plugins_url( 'js/wordlift-reloaded.js', __FILE__ );

	return $plugin_array;
}

/**
 * Enable microdata schema.org tagging.
 * see http://vip.wordpress.com/documentation/register-additional-html-attributes-for-tinymce-and-wp-kses/
 */
function wordlift_allowed_post_tags() {
	global $allowedposttags;

	$tags           = array( 'span' );
	$new_attributes = array(
		'itemscope' => array(),
		'itemtype'  => array(),
		'itemprop'  => array(),
		'itemid'    => array()
	);

	foreach ( $tags as $tag ) {
		if ( isset( $allowedposttags[ $tag ] ) && is_array( $allowedposttags[ $tag ] ) ) {
			$allowedposttags[ $tag ] = array_merge( $allowedposttags[ $tag ], $new_attributes );
		}
	}
}

// init process for button control
add_action( 'init', 'wordlift_buttonhooks' );
// add allowed post tags.
add_action( 'init', 'wordlift_allowed_post_tags' );


/**
 * Register additional scripts for the admin UI.
 */
function wordlift_admin_enqueue_scripts() {

	// Added for compatibility with WordPress 3.9 (see http://make.wordpress.org/core/2014/04/16/jquery-ui-and-wpdialogs-in-wordpress-3-9/)
	wp_enqueue_script( 'wpdialogs' );
	wp_enqueue_style( 'wp-jquery-ui-dialog' );

	wp_register_style( 'wordlift_css', plugins_url( 'css/wordlift-reloaded.css', __FILE__ ) );
	wp_enqueue_style( 'wordlift_css' );

	wp_enqueue_script( 'jquery-ui-autocomplete' );
	wp_enqueue_script( 'angularjs', plugins_url( 'bower_components/angular/angular.min.js', __FILE__ ) );

}

add_action( 'admin_enqueue_scripts', 'wordlift_admin_enqueue_scripts' );

function wl_enqueue_scripts() {
	wp_enqueue_style( 'wordlift-ui', plugins_url( 'css/wordlift.ui.css', __FILE__ ) );
}

add_action( 'wp_enqueue_scripts', 'wl_enqueue_scripts' );

/**
 * Hooked to *wp_kses_allowed_html* filter, adds microdata attributes.
 *
 * @param array $allowedtags The array with the currently configured elements and attributes.
 * @param string $context The context.
 *
 * @return array An array which contains allowed microdata attributes.
 */
function wordlift_allowed_html( $allowedtags, $context ) {

	if ( 'post' !== $context ) {
		return $allowedtags;
	}

	return array_merge_recursive( $allowedtags, array(
		'span' => array(
			'itemscope' => true,
			'itemtype'  => true,
			'itemid'    => true,
			'itemprop'  => true
		)
	) );
}

add_filter( 'wp_kses_allowed_html', 'wordlift_allowed_html', 10, 2 );

/**
 * Get the coordinates for the specified post ID.
 *
 * @param int $post_id The post ID.
 *
 * @return array|null An array of coordinates or null.
 */
function wl_get_coordinates( $post_id ) {

	$latitude  = get_post_meta( $post_id, WL_CUSTOM_FIELD_GEO_LATITUDE, true );
	$longitude = get_post_meta( $post_id, WL_CUSTOM_FIELD_GEO_LONGITUDE, true );

	if ( empty( $latitude ) || empty( $longitude ) ) {
		return null;
	}

	return array(
		'latitude'  => $latitude,
		'longitude' => $longitude
	);
}

/**
 * Set the sameAs URIs for the specified post ID.
 *
 * @param int $post_id A post ID.
 * @param array|string $same_as An array of same as URIs or a single URI string.
 */
function wl_set_same_as( $post_id, $same_as ) {

	// Prepare the same as array.
	$same_as_array = array_unique( is_array( $same_as ) ? $same_as : array( $same_as ) );

	wl_write_log( "wl_set_same_as [ post id :: $post_id ][ same as :: " . join( ',', $same_as_array ) . " ]" );

	// Replace the existing same as with the new one.
	delete_post_meta( $post_id, 'entity_same_as' );

	foreach ( $same_as_array as $item ) {
		if ( ! empty( $item ) ) {
			add_post_meta( $post_id, 'entity_same_as', $item, false );
		}
	}
}

/**
 * Get the sameAs URIs for the specified post ID.
 *
 * @param int $post_id A post ID.
 *
 * @return array An array of sameAs URIs.
 */
function wl_get_same_as( $post_id ) {

	// Get the related array (single _must_ be true, refer to http://codex.wordpress.org/Function_Reference/get_post_meta)
	$same_as = get_post_meta( $post_id, 'entity_same_as', false );

	if ( empty( $same_as ) ) {
		return array();
	}

	// Ensure an array is returned.
	return ( is_array( $same_as ) ? $same_as : array( $same_as ) );
}

/**
 * Get the modified time of the provided post. If the time is negative, return the published date.
 *
 * @param object $post A post instance.
 *
 * @return string A datetime.
 */
function wl_get_post_modified_time( $post ) {

	$date_modified = get_post_modified_time( 'c', true, $post );

	if ( '-' === substr( $date_modified, 0, 1 ) ) {
		return get_the_time( 'c', $post );
	}

	return $date_modified;
}

/**
 * Get all the images bound to a post.
 *
 * @param int $post_id The post ID.
 *
 * @return array An array of image URLs.
 */
function wl_get_image_urls( $post_id ) {

	wl_write_log( "wl_get_image_urls [ post id :: $post_id ]" );

	$images = get_children( array(
		'post_parent'    => $post_id,
		'post_type'      => 'attachment',
		'post_mime_type' => 'image'
	) );

	// Return an empty array if no image is found.
	if ( empty( $images ) ) {
		return array();
	}

	// Prepare the return array.
	$image_urls = array();

	// Collect the URLs.
	foreach ( $images as $attachment_id => $attachment ) {
		$image_url = wp_get_attachment_url( $attachment_id );
		// Ensure the URL isn't collected already.
		if ( ! in_array( $image_url, $image_urls ) ) {
			array_push( $image_urls, $image_url );
		}
	}

	wl_write_log( "wl_get_image_urls [ post id :: $post_id ][ image urls count :: " . count( $image_urls ) . " ]" );

	return $image_urls;
}

/**
 * Get a SPARQL fragment with schema:image predicates.
 *
 * @param string $uri The URI subject of the statements.
 * @param int $post_id The post ID.
 *
 * @return string The SPARQL fragment.
 */
function wl_get_sparql_images( $uri, $post_id ) {

	$sparql = '';

	// Get the escaped URI.
	$uri_e = esc_html( $uri );

	// Add SPARQL stmts to write the schema:image.
	$image_urls = wl_get_image_urls( $post_id );
	foreach ( $image_urls as $image_url ) {
		$image_url_esc = wordlift_esc_sparql( $image_url );
		$sparql .= " <$uri_e> schema:image <$image_url_esc> . \n";
	}

	return $sparql;
}

/**
 * Get an attachment with the specified parent post ID and source URL.
 *
 * @param int $parent_post_id The parent post ID.
 * @param string $source_url The source URL.
 *
 * @return WP_Post|null A post instance or null if not found.
 */
function wl_get_attachment_for_source_url( $parent_post_id, $source_url ) {

	wl_write_log( "wl_get_attachment_for_source_url [ parent post id :: $parent_post_id ][ source url :: $source_url ]" );

	$posts = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'post_parent'    => $parent_post_id,
		'meta_key'       => 'wl_source_url',
		'meta_value'     => $source_url
	) );

	// Return the found post.
	if ( 1 === count( $posts ) ) {
		return $posts[0];
	}

	// Return null.
	return null;
}

/**
 * Set the source URL.
 *
 * @param int $post_id The post ID.
 * @param string $source_url The source URL.
 */
function wl_set_source_url( $post_id, $source_url ) {

	delete_post_meta( $post_id, 'wl_source_url' );
	add_post_meta( $post_id, 'wl_source_url', $source_url );
}


/**
 * This function is called by the *flush_rewrite_rules_hard* hook. It recalculates the URI for all the posts.
 *
 * @since 3.0.0
 *
 * @uses rl_sparql_prefixes to get the SPARQL prefixes.
 * @uses wordlift_esc_sparql to escape the SPARQL query.
 * @uses wl_get_entity_uri to get an entity URI.
 * @uses rl_execute_sparql_update_query to post the DELETE and INSERT queries.
 *
 * @param bool $hard True if the rewrite involves configuration updates in Apache/IIS.
 */
function wl_flush_rewrite_rules_hard( $hard ) {

	// Get all published posts.
	$posts = get_posts( array(
		'posts_per_page' => - 1,
		'post_type'      => 'any',
		'post_status'    => 'publish'
	) );

	// Holds the delete part of the query.
	$delete_query = rl_sparql_prefixes();
	// Holds the insert part of the query.
	$insert_query = 'INSERT DATA { ';

	// Cycle in each post to build the query.
	foreach ( $posts as $post ) {

		// Ignore revisions.
		if ( wp_is_post_revision( $post->ID ) ) {
			continue;
		}

		// Get the entity URI.
		$uri = wordlift_esc_sparql( wl_get_entity_uri( $post->ID ) );

		// Get the post URL.
		$url = wordlift_esc_sparql( get_permalink( $post->ID ) );

		// Prepare the DELETE and INSERT commands.
		$delete_query .= "DELETE { <$uri> schema:url ?u . } WHERE  { <$uri> schema:url ?u . };\n";
		$insert_query .= " <$uri> schema:url <$url> . \n";

		// wl_write_log( "[ uri :: $uri ][ url :: $url ]" );
	}

	$insert_query .= ' };';

	// Execute the query.
	rl_execute_sparql_update_query( $delete_query . $insert_query );
}

add_filter( 'flush_rewrite_rules_hard', 'wl_flush_rewrite_rules_hard', 10, 1 );

/**
 * Sanitizes an URI path by replacing the non allowed characters with an underscore.
 *
 * @param string $path The path to sanitize.
 * @param string $char The replacement character (by default an underscore).
 *
 * @return The sanitized path.
 */
function wl_sanitize_uri_path( $path, $char = '_' ) {

	wl_write_log( "wl_sanitize_uri_path [ path :: $path ][ char :: $char ]" );

	// According to RFC2396 (http://www.ietf.org/rfc/rfc2396.txt) these characters are reserved:
	// ";" | "/" | "?" | ":" | "@" | "&" | "=" | "+" |
	// "$" | ","
	// Plus the ' ' (space).
	// TODO: We shall use the same regex used by MediaWiki (http://stackoverflow.com/questions/23114983/mediawiki-wikipedia-url-sanitization-regex)

	return preg_replace( '/[;\/?:@&=+$,\s]/', $char, $path );
}

/**
 * Schedule the execution of SPARQL Update queries before the WordPress look ends.
 */
function wl_shutdown() {

	// Get the filename to the temporary SPARQL file.
	$filename = WL_TEMP_DIR . WL_REQUEST_ID . '.sparql';

	// If WordLift is buffering SPARQL queries, we're admins and a buffer exists, then schedule it.
	if ( WL_ENABLE_SPARQL_UPDATE_QUERIES_BUFFERING && is_admin() && file_exists( $filename ) ) {

		// The request ID.
		$args = array( WL_REQUEST_ID );

		// Schedule the execution of the SPARQL query with the request ID.
		wp_schedule_single_event( time(), 'wl_execute_saved_sparql_update_query', $args );

		// Check that the request is scheduled.
		$timestamp = wp_next_scheduled( 'wl_execute_saved_sparql_update_query', $args );

		// Spawn the cron.
		spawn_cron();

		wl_write_log( "wl_shutdown [ request id :: " . WL_REQUEST_ID . " ][ timestamp :: $timestamp ]" );
	}
}

add_action( 'shutdown', 'wl_shutdown' );

/**
 * Replaces the *itemid* attributes URIs with the WordLift URIs.
 *
 * @param string $content The post content.
 *
 * @return string The updated post content.
 */
function wl_replace_item_id_with_uri( $content ) {

	wl_write_log( "wl_replace_item_id_with_uri" );

	// Strip slashes, see https://core.trac.wordpress.org/ticket/21767
	$content = stripslashes( $content );

	// If any match are found.
	$matches = array();
	if ( 0 < preg_match_all( '/ itemid="([^"]+)"/i', $content, $matches, PREG_SET_ORDER ) ) {

		foreach ( $matches as $match ) {

			// Get the item ID.
			$item_id = $match[1];

			// Get the post bound to that item ID (looking both in the 'official' URI and in the 'same-as' .
			$post = wl_get_entity_post_by_uri( $item_id );

			// If no entity is found, continue to the next one.
			if ( null === $post ) {
				continue;
			}

			// Get the URI for that post.
			$uri = wl_get_entity_uri( $post->ID );

			wl_write_log( "wl_replace_item_id_with_uri [ item id :: $item_id ][ uri :: $uri ]" );

			// If the item ID and the URI differ, replace the item ID with the URI saved in WordPress.
			if ( $item_id !== $uri ) {
				$uri_e = esc_html( $uri );
				$content = str_replace( " itemid=\"$item_id\"", " itemid=\"$uri_e\"", $content );
			}
		}
	}

	// Reapply slashes.
	$content = addslashes( $content );

	return $content;
}

add_filter( 'content_save_pre', 'wl_replace_item_id_with_uri', 1, 1 );


/**
 * Install known types in WordPress.
 */
function wl_install_entity_type_data() {

	// Ensure the custom type and the taxonomy are registered.
	wl_entity_type_register();
	wl_entity_type_taxonomy_register();

	// Create a blank application key
	wl_configuration_set_key('');

	// Set the taxonomy data.
        // Note: parent types must be defined before child types.
	// TODO: Manage both generic and custom fields as fields
        // TODO: inherit on microdata template also
	$terms = array(
                'thing'         => array(
                        'label'              => 'Thing',
                        'description'        => 'A generic thing (something that doesn\'t fit in the previous definitions.',
                        'css'                => 'wl-thing',
                        'uri'                => 'http://schema.org/Thing',
                        'same_as'            => array( '*' ), // set as default.
                        'custom_fields'      => array(
				WL_CUSTOM_FIELD_SAME_AS => array(
					'predicate'   => 'http://schema.org/sameAs',
					'type'        => WL_DATA_TYPE_URI,
                                        'export_type' => 'http://schema.org/Thing',
					'constraints' => ''
				)
			),
                        'microdata_template' => '{{sameAs}}',
                        'templates'          => array(
                                'subtitle' => '{{id}}'
                        )
		),
		'creative-work' => array(
			'label'              => 'Creative Work',
			'description'        => 'A creative work (or a Music Album).',
                        'parents'             => array( 'thing' ),
			'css'                => 'wl-creative-work',
			'uri'                => 'http://schema.org/CreativeWork',
			'same_as'            => array(
				'http://schema.org/MusicAlbum', // TODO: not correct
				'http://schema.org/Product'     // TODO: not correct
			),
			'custom_fields'      => array(),
			'microdata_template' => '',
			'templates'          => array(
				'subtitle' => '{{id}}'
			)
		),
		'event'         => array(
			'label'              => 'Event',
			'description'        => 'An event.',
                        'parents'             => array( 'thing' ),
			'css'                => 'wl-event',
			'uri'                => 'http://schema.org/Event',
			'same_as'            => array( 'http://dbpedia.org/ontology/Event' ),
			'custom_fields'      => array(
				WL_CUSTOM_FIELD_CAL_DATE_START => array(
					'predicate'   => 'http://schema.org/startDate',
					'type'        => WL_DATA_TYPE_DATE,
                                        'export_type' => 'xsd:date',
					'constraints' => ''
				),
				WL_CUSTOM_FIELD_CAL_DATE_END   => array(
					'predicate'   => 'http://schema.org/endDate',
					'type'        => WL_DATA_TYPE_DATE,
                                        'export_type' => 'xsd:date',
					'constraints' => ''
				),
				WL_CUSTOM_FIELD_LOCATION       => array(
					'predicate'   => 'http://schema.org/location',
					'type'        => WL_DATA_TYPE_URI,
                                        'export_type' => 'http://schema.org/PostalAddress',
					'constraints' => array(
						'uri_type' => 'Place'
					)
				)
			),
			'microdata_template' =>
				'{{startDate}}
                                {{endDate}}
                                {{location}}',
			'templates'          => array(
				'subtitle' => '{{id}}'
			)
		),
		'organization'  => array(
			'label'              => 'Organization',
			'description'        => 'An organization, including a government or a newspaper.',
                        'parents'             => array( 'thing' ),
			'css'                => 'wl-organization',
			'uri'                => 'http://schema.org/Organization',
			'same_as'            => array(
				'http://rdf.freebase.com/ns/organization.organization',
				'http://rdf.freebase.com/ns/government.government',
				'http://schema.org/Newspaper'
			),
			'custom_fields'      => array(
                            WL_CUSTOM_FIELD_FOUNDER  => array(
                                        'predicate'        => 'http://schema.org/founder',
					'type'        => WL_DATA_TYPE_URI,
                                        'export_type' => 'http://schema.org/Person',
					'constraints' => array(
						'uri_type' => 'Person'
					)
				),
                        ),
			'microdata_template' => '{{founder}}',
			'templates'          => array(
				'subtitle' => '{{id}}'
			)
		),
		'person'        => array(
			'label'              => 'Person',
			'description'        => 'A person (or a music artist).',
                        'parents'             => array( 'thing' ),
			'css'                => 'wl-person',
			'uri'                => 'http://schema.org/Person',
			'same_as'            => array(
				'http://rdf.freebase.com/ns/people.person',
				'http://rdf.freebase.com/ns/music.artist',
				'http://dbpedia.org/class/yago/LivingPeople'
			),
			'custom_fields'      => array(),
			'microdata_template' => '',
			'templates'          => array(
				'subtitle' => '{{id}}'
			)
		),
		'place'         => array(
			'label'              => 'Place',
			'description'        => 'A place.',
                        'parents'             => array( 'thing' ),
			'css'                => 'wl-place',
			'uri'                => 'http://schema.org/Place',
			'same_as'            => array(
				'http://rdf.freebase.com/ns/location.location',
				'http://www.opengis.net/gml/_Feature'
			),
			'custom_fields'      => array(
				WL_CUSTOM_FIELD_GEO_LATITUDE  => array(
                                        'predicate'        => 'http://schema.org/latitude',
					'type'             => WL_DATA_TYPE_DOUBLE,
                                        'export_type'      => 'xsd:double',
					'constraints' => '',
					'input_field' => 'coordinates'
				),
				WL_CUSTOM_FIELD_GEO_LONGITUDE => array(
					'predicate'   => 'http://schema.org/longitude',
					'type'        => WL_DATA_TYPE_DOUBLE,
                                        'export_type'      => 'xsd:double',
					'constraints' => '',
					'input_field' => 'coordinates'
				),
				WL_CUSTOM_FIELD_ADDRESS       => array(
					'predicate' => 'http://schema.org/address',
					'type'        => WL_DATA_TYPE_STRING,
                                        'export_type'      => 'http://schema.org/PostalAddress',
					'constraints' => ''
				)
			),
			'microdata_template' =>
				'<span itemprop="geo" itemscope itemtype="http://schema.org/GeoCoordinates">
                                    {{latitude}}
                                    {{longitude}}
                                </span>
                                {{address}}',
			'templates'          => array(
				'subtitle' => '{{id}}'
			)
		),
                'localbusiness'         => array(
                        'label'              => 'LocalBusiness',
                        'description'        => 'A local business.',
                        'parents'            => array( 'place', 'organization' ),
                        'css'                => 'wl-organization',
                        'uri'                => 'http://schema.org/LocalBusiness',
                        'same_as'            => array(
                                'http://rdf.freebase.com/ns/business/business_location',
                                'https://schema.org/Store'
                        ),
                        'custom_fields'      => array(
                                
                        ),
                        'microdata_template' => '',
                        'templates'          => array(
                                'subtitle' => '{{id}}'
                        )
                ),
            
	);
        
	foreach ( $terms as $slug => $term ) {
		
                // Create the term if it does not exist, then get its ID
                $term_id = term_exists( $term['label'] );
                if( $term_id == 0 || is_null( $term_id ) ) {
                    $result = wp_insert_term( $term['label'], WL_ENTITY_TYPE_TAXONOMY_NAME );
                } else {
                    $result = get_term( $term_id, WL_ENTITY_TYPE_TAXONOMY_NAME, ARRAY_A );
                }

		// Check for errors.
		if ( is_wp_error( $result ) ) {
			wl_write_log( 'wl_install_entity_type_data [ ' . $result->get_error_message() . ' ]' );
			continue;
                }
                
                // Check if 'parent' corresponds to an actual term and get its ID.
                if( !isset( $term['parents'] ) ) {
                    $term['parents'] = array();
                }
                
                $parent_ids = array();
                foreach( $term['parents'] as $parent_slug ) {
                    $parent_id = get_term_by( 'slug', $parent_slug, WL_ENTITY_TYPE_TAXONOMY_NAME );
                    $parent_ids[] = intval( $parent_id->term_id );  // Note: int casting is suggested by Codex: http://codex.wordpress.org/Function_Reference/get_term_by
                }
                
                // Define a parent in the WP taxonomy style (not important for WL)
                if( empty( $parent_ids ) ) {
                    // No parent
                    $parent_id = 0;
                } else {
                    // Get first parent
                    $parent_id = $parent_ids[0];
                }
                
                // Update term with description, slug and parent    
                wp_update_term( $result['term_id'], WL_ENTITY_TYPE_TAXONOMY_NAME, array(
                    'description'   => $term['description'],
                    'slug'          => $slug,
                    'parent'        => $parent_id   // We give to WP taxonomy just one parent. TODO: see if can give more than one
                ));
                
                // Inherit custom fields and microdata template from parent.
                $term = wl_entity_type_taxonomy_type_inheritage( $term, $parent_ids );
                
		// Add custom metadata to the term.
		wl_entity_type_taxonomy_update_term( $result['term_id'], $term['css'], $term['uri'], $term['same_as'], $term['custom_fields'], $term['templates'], $term['microdata_template'] );
        }

}

/**
 * Merge the custom_fields and microdata_templates of an entity type with the ones from parents.
 * This function is used by *wl_install_entity_type_data* at installation time.
 * 
 * @param $child_term Array Child entity type (expanded as array).
 * @param $parent_term_ids Array containing the ids of the parent types.
 *
 * @return Array $child_term enriched with parents' custom_fields and microdata_template
 */
function wl_entity_type_taxonomy_type_inheritage( $child_term, $parent_term_ids ) {
    
    // If we re at the top of hierarchy ...
    if( empty( $parent_term_ids ) || $parent_term_ids[0] == 0 ) {
        // ... return term as it is.
        return $child_term;
    }
    
    // Loop over parents
    $merged_custom_fields = $child_term['custom_fields'];
    $merged_microdata_template = $child_term['microdata_template'];
    foreach( $parent_term_ids as $parent_term_id ) {
        
        // Get a parent's custom fields
        $parent_term = wl_entity_type_taxonomy_get_term_options( $parent_term_id );
        $parent_term_custom_fields = $parent_term['custom_fields'];
        $parent_term_microdata_template = $parent_term['microdata_template'];
        
        // Merge custom fields (array)
        $merged_custom_fields = array_merge( $merged_custom_fields, $parent_term_custom_fields );
        // Merge microdata templates (string)
        $merged_microdata_template = $merged_microdata_template . $parent_term_microdata_template;
    }
    
    // Ensure there are no duplications in microdata_templates
    $exploded_microdata_template = explode( '}}' , $merged_microdata_template );
    $unique_microdata_template = array_unique( $exploded_microdata_template );
    $merged_microdata_template = implode( '}}' , $unique_microdata_template );
    
    // Update child_term with inherited structures
    $child_term['custom_fields'] = $merged_custom_fields;
    $child_term['microdata_template'] = $merged_microdata_template;
    
    // Return new version of the term
    return $child_term;
}

/**
 * Change *plugins_url* response to return the correct path of WordLift files when working in development mode.
 *
 * @param $url The URL as set by the plugins_url method.
 * @param $path The request path.
 * @param $plugin The plugin folder.
 *
 * @return string The URL.
 */
function wl_plugins_url( $url, $path, $plugin ) {

	wl_write_log( "wl_plugins_url [ url :: $url ][ path :: $path ][ plugin :: $plugin ]" );

	// Check if it's our pages calling the plugins_url.
	if ( 1 !== preg_match( '/\/wordlift[^.]*.php$/i', $plugin ) ) {
		return $url;
	}

	// Set the URL to plugins URL + wordlift, in order to support the plugin being symbolic linked.
	$plugin_url = plugins_url() . '/wordlift/' . $path;

	wl_write_log( "wl_plugins_url [ match :: yes ][ plugin url :: $plugin_url ][ url :: $url ][ path :: $path ][ plugin :: $plugin ]" );

	return $plugin_url;
}

add_filter( 'plugins_url', 'wl_plugins_url', 10, 3 );

// TODO - Check installation
add_action( 'activate_wordlift/wordlift.php', 'wl_install_entity_type_data' );

require_once( 'wordlift_entity_functions.php' );

// add editor related methods.
require_once( 'wordlift_editor.php' );

// add the WordLift entity custom type.
require_once( 'wordlift_entity_type.php' );
require_once( 'wordlift_entity_type_taxonomy.php' );

// filters the post content when saving posts.
require_once( 'wordlift_content_filter.php' );
// add callbacks on post save to notify data changes from wp to redlink triple store
require_once( 'wordlift_to_redlink_data_push_callbacks.php' );

// Load modules
require_once( 'modules/core/wordlift_core.php' );
require_once( 'modules/configuration/wordlift_configuration.php' );
require_once( 'modules/analyzer/wordlift_analyzer.php' );
require_once( 'modules/linked_data/wordlift_linked_data.php' );
require_once( 'modules/prefixes/wordlift_prefixes.php' );
require_once( 'modules/caching/wordlift_caching.php' );
require_once( 'modules/profiling/wordlift_profiling.php' );
require_once( 'modules/redirector/wordlift_redirector.php' );
require_once( 'modules/freebase_image_proxy/wordlift_freebase_image_proxy.php' );

// Shortcodes

// Entity view shortcode just with php >= 5.4
if ( version_compare( phpversion(), '5.4.0', '>=' ) ) {
	require_once( 'modules/entity_view/wordlift_entity_view.php' );
}

require_once( 'modules/geo_widget/wordlift_geo_widget.php' );
require_once( 'modules/timeline_widget/wordlift_timeline_widget.php' );
require_once( 'shortcodes/wordlift_shortcode_chord.php' );
require_once( 'shortcodes/wordlift_shortcode_timeline.php' );
require_once( 'shortcodes/wordlift_shortcode_geomap.php' );
require_once( 'shortcodes/wordlift_shortcode_field.php' );
require_once( 'shortcodes/wordlift_shortcode_faceted_search.php' );
require_once( 'shortcodes/wordlift_shortcode_navigator.php' );

// disable In-Depth Articles
//require_once('wordlift_indepth_articles.php');

require_once( 'wordlift_user.php' );

require_once( 'widgets/wordlift_widget_geo.php' );
require_once( 'widgets/wordlift_widget_chord.php' );
require_once( 'widgets/wordlift_widget_timeline.php' );

require_once( 'wordlift_sparql.php' );
require_once( 'wordlift_redlink.php' );

require_once( 'modules/sparql/wordlift_sparql.php' );

// Add admin functions.
// TODO: find a way to make 'admin' UI tests work.
//if ( is_admin() ) {

require_once( 'admin/wordlift_admin.php' );
require_once( 'admin/wordlift_admin_edit_post.php' );
require_once( 'admin/wordlift_admin_save_post.php' );

// add the WordLift admin bar.
require_once( 'admin/wordlift_admin_bar.php' );

// add the entities meta box.
require_once( 'admin/wordlift_admin_meta_box_entities.php' );
require_once( 'admin/wordlift_admin_entity_type_taxonomy.php' );

// add the search entity AJAX.
require_once( 'admin/wordlift_admin_ajax_search.php' );
// add the entity creation AJAX.
require_once( 'admin/wordlift_admin_ajax_add_entity.php' );
// add the entity creation AJAX.
require_once( 'admin/wordlift_admin_ajax_related_posts.php' );

// Load the wl_chord TinyMCE button and configuration dialog.
require_once( 'admin/wordlift_admin_shortcodes.php' );

// Provide syncing features.
require_once( 'admin/wordlift_admin_sync.php' );
//}

// load languages.
// TODO: the following call gives for granted that the plugin is in the wordlift directory,
//       we're currently doing this because wordlift is symbolic linked.
load_plugin_textdomain( 'wordlift', false, '/wordlift/languages' );
