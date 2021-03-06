<?php
/**
 * Tests: Entity Post to JSON-LD Converter Test.
 *
 * This file defines tests for the {@link Wordlift_Entity_Post_To_Jsonld_Converter} class.
 *
 * @since   3.8.
 * @package Wordlift
 */

/**
 * Test the {@link Wordlift_Entity_Post_To_Jsonld_Converter} class.
 *
 * @since   3.8.0
 * @package Wordlift
 */
class Wordlift_Entity_Post_To_Jsonld_Converter_Test extends Wordlift_Unit_Test_Case {

	/**
	 * A {@link Wordlift_Entity_Type_Service} instance.
	 *
	 * @since  3.8.0
	 * @access private
	 * @var Wordlift_Entity_Type_Service $entity_type_service A {@link Wordlift_Entity_Type_Service} instance.
	 */
	private $entity_type_service;

	/**
	 * A {@link Wordlift_Entity_Service} instance.
	 *
	 * @since  3.8.0
	 * @access private
	 * @var Wordlift_Entity_Service $entity_service A {@link Wordlift_Entity_Service} instance.
	 */
	private $entity_service;

	/**
	 * The {@link Wordlift_Entity_Post_To_Jsonld_Converter} to test.
	 *
	 * @since  3.8.0
	 * @access private
	 * @var Wordlift_Entity_Post_To_Jsonld_Converter $entity_post_to_jsonld_converter A {@link Wordlift_Entity_Post_To_Jsonld_Converter} instance.
	 */
	private $entity_post_to_jsonld_converter;

	/**
	 * A {@link Wordlift_Postid_To_Jsonld_Converter} instance to test.
	 *
	 * @since  3.8.0
	 * @access private
	 * @var \Wordlift_Postid_To_Jsonld_Converter $postid_to_jsonld_converter A {@link Wordlift_Postid_To_Jsonld_Converter} instance.
	 */
	private $postid_to_jsonld_converter;

	/**
	 * {@inheritdoc}
	 */
	public function setUp() {
		parent::setUp();

		// Disable sending SPARQL queries, since we don't need it.
		Wordlift_Unit_Test_Case::turn_off_entity_push();;

		$wordlift = new Wordlift_Test();

		$this->entity_type_service             = $wordlift->get_entity_type_service();
		$this->entity_service                  = $wordlift->get_entity_service();
		$this->entity_post_to_jsonld_converter = $wordlift->get_entity_post_to_jsonld_converter();
		$this->postid_to_jsonld_converter      = $wordlift->get_postid_to_jsonld_converter();

	}

	/**
	 * Test the conversion of an event entity {@link WP_Post} to a JSON-LD array.
	 *
	 * @since 3.8.0
	 */
	public function test_event_conversion() {

		// Create an entity post and assign it the Event type.
		$name     = rand_str();
		$event_id = $this->factory->post->create( array(
			'post_title' => $name,
			'post_type'  => 'entity',
		) );
		$this->entity_type_service->set( $event_id, 'http://schema.org/Event' );
		$event_uri = $this->entity_service->get_uri( $event_id );

		// Set the start date.
		$start_date = date( 'Y/m/d', 1576800000 );
		add_post_meta( $event_id, Wordlift_Schema_Service::FIELD_DATE_START, $start_date );

		// Set the end date.
		$end_date = date( 'Y/m/d', 3153600000 );
		add_post_meta( $event_id, Wordlift_Schema_Service::FIELD_DATE_END, $end_date );

		// Set a random sameAs.
		$same_as = 'http://example.org/aRandomSameAs';
		add_post_meta( $event_id, Wordlift_Schema_Service::FIELD_SAME_AS, $same_as );

		// Create a location entity post and bind it to the location property.
		$place_id = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $place_id, 'http://schema.org/Place' );
		$place_uri = $this->entity_service->get_uri( $place_id );

		// Bind the place to the location property.
		add_post_meta( $event_id, Wordlift_Schema_Service::FIELD_LOCATION, $place_id );


		$post       = get_post( $event_id );
		$references = array();
		$jsonld     = $this->entity_post_to_jsonld_converter->convert( $post->ID, $references );

		$this->assertTrue( is_array( $jsonld ) );
		$this->assertArrayHasKey( '@context', $jsonld );
		$this->assertEquals( 'http://schema.org', $jsonld['@context'] );

		$this->assertArrayHasKey( '@id', $jsonld );
		$this->assertEquals( $event_uri, $jsonld['@id'] );

		$this->assertArrayHasKey( '@type', $jsonld );
		$this->assertEquals( 'Event', $jsonld['@type'] );

		$this->assertArrayHasKey( 'name', $jsonld );
		$this->assertEquals( $name, $jsonld['name'] );

		$this->assertArrayHasKey( 'url', $jsonld );
		$this->assertEquals( get_permalink( $event_id ), $jsonld['url'] );

		$this->assertArrayHasKey( 'startDate', $jsonld );
		$this->assertEquals( $start_date, $jsonld['startDate'] );

		$this->assertArrayHasKey( 'endDate', $jsonld );
		$this->assertEquals( $end_date, $jsonld['endDate'] );

		$this->assertArrayHasKey( 'sameAs', $jsonld );
		$this->assertEquals( $same_as, $jsonld['sameAs'] );

		$this->assertArrayHasKey( 'location', $jsonld );
		$this->assertArrayHasKey( '@id', $jsonld['location'] );
		$this->assertEquals( $place_uri, $jsonld['location']['@id'] );

		$this->assertContains( $place_id, $references );

		$this->assertFalse( isset( $jsonld['alternateName'] ) );

		$references_2 = array();
		$this->assertEquals( $jsonld, $this->postid_to_jsonld_converter->convert( $event_id, $references_2 ) );
		$this->assertEquals( $references, $references_2 );

	}

	/**
	 * Test the conversion of an place entity {@link WP_Post} to a JSON-LD array.
	 *
	 * @since 3.8.0
	 */
	public function test_place_conversion() {

		// Create a location entity post and bind it to the location property.
		$name     = rand_str();
		$place_id = $this->factory->post->create( array(
			'post_title' => $name,
			'post_type'  => 'entity',
		) );
		$this->entity_type_service->set( $place_id, 'http://schema.org/Place' );
		$place_uri = $this->entity_service->get_uri( $place_id );

		// Set a random sameAs.
		$same_as = 'http://example.org/aRandomSameAs';
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_SAME_AS, $same_as );

		// Set the geo coordinates.
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_GEO_LATITUDE, 12.34 );
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_GEO_LONGITUDE, 1.23 );

		$address = rand_str();
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_ADDRESS, $address );

		$po_box = rand_str();
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_ADDRESS_PO_BOX, $po_box );

		$postal_code = rand_str();
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_ADDRESS_POSTAL_CODE, $postal_code );

		$locality = rand_str();
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_ADDRESS_LOCALITY, $locality );

		$region = rand_str();
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_ADDRESS_REGION, $region );

		$country = rand_str();
		add_post_meta( $place_id, Wordlift_Schema_Service::FIELD_ADDRESS_COUNTRY, $country );

		// Set the alternative names.
		$alternate_labels = array( rand_str(), rand_str() );
		$this->entity_service->set_alternative_labels( $place_id, $alternate_labels );

		$post       = get_post( $place_id );
		$references = array();
		$jsonld     = $this->entity_post_to_jsonld_converter->convert( $post->ID, $references );

		$this->assertTrue( is_array( $jsonld ) );
		$this->assertArrayHasKey( '@context', $jsonld );
		$this->assertEquals( 'http://schema.org', $jsonld['@context'] );

		$this->assertArrayHasKey( '@id', $jsonld );
		$this->assertEquals( $place_uri, $jsonld['@id'] );

		$this->assertArrayHasKey( '@type', $jsonld );
		$this->assertEquals( 'Place', $jsonld['@type'] );

		$this->assertArrayHasKey( 'name', $jsonld );
		$this->assertEquals( $name, $jsonld['name'] );

		$this->assertArrayHasKey( 'url', $jsonld );
		$this->assertEquals( get_permalink( $place_id ), $jsonld['url'] );

		$this->assertArrayHasKey( 'sameAs', $jsonld );
		$this->assertEquals( $same_as, $jsonld['sameAs'] );

		$this->assertArrayHasKey( 'geo', $jsonld );

		$this->assertArrayHasKey( '@type', $jsonld['geo'] );
		$this->assertEquals( 'GeoCoordinates', $jsonld['geo']['@type'] );

		$this->assertArrayHasKey( 'latitude', $jsonld['geo'] );
		$this->assertEquals( 12.34, $jsonld['geo']['latitude'] );

		$this->assertArrayHasKey( 'longitude', $jsonld['geo'] );
		$this->assertEquals( 1.23, $jsonld['geo']['longitude'] );

		$this->assertArrayHasKey( 'address', $jsonld );

		$this->assertArrayHasKey( '@type', $jsonld['address'] );
		$this->assertEquals( 'PostalAddress', $jsonld['address']['@type'] );

		$this->assertEquals( $address, $jsonld['address']['streetAddress'] );
		$this->assertEquals( $po_box, $jsonld['address']['postOfficeBoxNumber'] );
		$this->assertEquals( $postal_code, $jsonld['address']['postalCode'] );
		$this->assertEquals( $locality, $jsonld['address']['addressLocality'] );
		$this->assertEquals( $region, $jsonld['address']['addressRegion'] );
		$this->assertEquals( $country, $jsonld['address']['addressCountry'] );

		$this->assertEquals( $alternate_labels, $jsonld['alternateName'] );

		$references_2 = array();
		$this->assertEquals( $jsonld, $this->postid_to_jsonld_converter->convert( $place_id, $references_2 ) );
		$this->assertEquals( $references, $references_2 );

	}

	/**
	 * Test the conversion of an creative work entity {@link WP_Post} to a JSON-LD array.
	 *
	 * @since 3.8.0
	 */
	public function test_create_work_conversion() {

		// Create a location entity post and bind it to the location property.
		$name           = rand_str();
		$create_work_id = $this->factory->post->create( array(
			'post_title' => $name,
			'post_type'  => 'entity',
		) );
		$this->entity_type_service->set( $create_work_id, 'http://schema.org/CreativeWork' );
		$create_work_uri = $this->entity_service->get_uri( $create_work_id );

		// Set a random sameAs.
		$same_as = 'http://example.org/aRandomSameAs';
		add_post_meta( $create_work_id, Wordlift_Schema_Service::FIELD_SAME_AS, $same_as );

		$person_id = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $person_id, 'http://schema.org/Person' );
		$person_uri = $this->entity_service->get_uri( $person_id );

		// Bind the person as author of the creative work.
		add_post_meta( $create_work_id, Wordlift_Schema_Service::FIELD_AUTHOR, $person_id );

		$post       = get_post( $create_work_id );
		$references = array();
		$jsonld     = $this->entity_post_to_jsonld_converter->convert( $post->ID, $references );

		$this->assertTrue( is_array( $jsonld ) );
		$this->assertArrayHasKey( '@context', $jsonld );
		$this->assertEquals( 'http://schema.org', $jsonld['@context'] );

		$this->assertArrayHasKey( '@id', $jsonld );
		$this->assertEquals( $create_work_uri, $jsonld['@id'] );

		$this->assertArrayHasKey( '@type', $jsonld );
		$this->assertEquals( 'CreativeWork', $jsonld['@type'] );

		$this->assertArrayHasKey( 'name', $jsonld );
		$this->assertEquals( $name, $jsonld['name'] );

		$this->assertArrayHasKey( 'url', $jsonld );
		$this->assertEquals( get_permalink( $create_work_id ), $jsonld['url'] );

		$this->assertArrayHasKey( 'sameAs', $jsonld );
		$this->assertEquals( $same_as, $jsonld['sameAs'] );

		$this->assertArrayHasKey( 'author', $jsonld );

		$this->assertArrayHasKey( '@id', $jsonld['author'] );
		$this->assertEquals( $person_uri, $jsonld['author']['@id'] );

		$this->assertContains( $person_id, $references );

		$references_2 = array();
		$this->assertEquals( $jsonld, $this->postid_to_jsonld_converter->convert( $create_work_id, $references_2 ) );
		$this->assertEquals( $references, $references_2 );

	}

	/**
	 * Test the conversion of an organization entity {@link WP_Post} to a JSON-LD array.
	 *
	 * @since 3.8.0
	 */
	public function test_organization_conversion() {

		// Create a location entity post and bind it to the location property.
		$name            = rand_str();
		$organization_id = $this->factory->post->create( array(
			'post_title' => $name,
			'post_type'  => 'entity',
		) );
		$this->entity_type_service->set( $organization_id, 'http://schema.org/Organization' );
		$organization_uri = $this->entity_service->get_uri( $organization_id );

		$email = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_EMAIL, $email );

		$phone = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_TELEPHONE, $phone );

		// Set a random sameAs.
		$same_as = 'http://example.org/aRandomSameAs';
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_SAME_AS, $same_as );

		$address = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_ADDRESS, $address );

		$po_box = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_ADDRESS_PO_BOX, $po_box );

		$postal_code = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_ADDRESS_POSTAL_CODE, $postal_code );

		$locality = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_ADDRESS_LOCALITY, $locality );

		$region = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_ADDRESS_REGION, $region );

		$country = rand_str();
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_ADDRESS_COUNTRY, $country );

		$person_id = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $person_id, 'http://schema.org/Person' );
		$person_uri = $this->entity_service->get_uri( $person_id );

		// Bind the person as author of the creative work.
		add_post_meta( $organization_id, Wordlift_Schema_Service::FIELD_FOUNDER, $person_id );

		$post       = get_post( $organization_id );
		$references = array();
		$jsonld     = $this->entity_post_to_jsonld_converter->convert( $post->ID, $references );

		$this->assertTrue( is_array( $jsonld ) );
		$this->assertArrayHasKey( '@context', $jsonld );
		$this->assertEquals( 'http://schema.org', $jsonld['@context'] );

		$this->assertArrayHasKey( '@id', $jsonld );
		$this->assertEquals( $organization_uri, $jsonld['@id'] );

		$this->assertArrayHasKey( '@type', $jsonld );
		$this->assertEquals( 'Organization', $jsonld['@type'] );

		$this->assertArrayHasKey( 'name', $jsonld );
		$this->assertEquals( $name, $jsonld['name'] );

		$this->assertArrayHasKey( 'url', $jsonld );
		$this->assertEquals( get_permalink( $organization_id ), $jsonld['url'] );

		$this->assertArrayHasKey( 'sameAs', $jsonld );
		$this->assertEquals( $same_as, $jsonld['sameAs'] );

		$this->assertArrayHasKey( 'email', $jsonld );
		$this->assertEquals( $email, $jsonld['email'] );

		$this->assertArrayHasKey( 'telephone', $jsonld );
		$this->assertEquals( $phone, $jsonld['telephone'] );

		$this->assertArrayHasKey( 'address', $jsonld );

		$this->assertArrayHasKey( '@type', $jsonld['address'] );
		$this->assertEquals( 'PostalAddress', $jsonld['address']['@type'] );

		$this->assertEquals( $address, $jsonld['address']['streetAddress'] );
		$this->assertEquals( $po_box, $jsonld['address']['postOfficeBoxNumber'] );
		$this->assertEquals( $postal_code, $jsonld['address']['postalCode'] );
		$this->assertEquals( $locality, $jsonld['address']['addressLocality'] );
		$this->assertEquals( $region, $jsonld['address']['addressRegion'] );
		$this->assertEquals( $country, $jsonld['address']['addressCountry'] );

		$this->assertArrayHasKey( 'founder', $jsonld );

		$this->assertArrayHasKey( '@id', $jsonld['founder'] );
		$this->assertEquals( $person_uri, $jsonld['founder']['@id'] );

		$this->assertContains( $person_id, $references );

		$references_2 = array();
		$this->assertEquals( $jsonld, $this->postid_to_jsonld_converter->convert( $organization_id, $references_2 ) );
		$this->assertEquals( $references, $references_2 );

	}

	/**
	 * Test the conversion of a person entity {@link WP_Post} to a JSON-LD array.
	 *
	 * @since 3.8.0
	 */
	public function test_person_conversion() {

		// Create an entity post and assign it the Event type.
		$name      = rand_str();
		$person_id = $this->factory->post->create( array(
			'post_title' => $name,
			'post_type'  => 'entity',
		) );
		$this->entity_type_service->set( $person_id, 'http://schema.org/Person' );
		$person_uri = $this->entity_service->get_uri( $person_id );

		$email = rand_str();
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_EMAIL, $email );

		// Set the start date.
		$birth_date = date( 'Y/m/d', 1576800000 );
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_BIRTH_DATE, $birth_date );

		// Set a random sameAs.
		$same_as = 'http://example.org/aRandomSameAs';
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_SAME_AS, $same_as );

		// Create a location entity post and bind it to the location property.
		$place_id = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $place_id, 'http://schema.org/Place' );
		$place_uri = $this->entity_service->get_uri( $place_id );

		// Bind the place to the birth place property.
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_BIRTH_PLACE, $place_id );

		// Create a knows connection.
		$knows_id_1 = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $knows_id_1, 'http://schema.org/Person' );
		$knows_uri_1 = $this->entity_service->get_uri( $knows_id_1 );

		// Bind the knows to the person.
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_KNOWS, $knows_id_1 );

		// Create a knows connection.
		$knows_id_2 = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $knows_id_2, 'http://schema.org/Person' );
		$knows_uri_2 = $this->entity_service->get_uri( $knows_id_2 );

		// Bind the knows to the person.
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_KNOWS, $knows_id_2 );

		// Create a knows connection.
		$organization_id = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $organization_id, 'http://schema.org/Organization' );
		$organization_id = $this->entity_service->get_uri( $organization_id );

		// Bind the knows to the person.
		add_post_meta( $person_id, Wordlift_Schema_Service::FIELD_AFFILIATION, $organization_id );


		$post       = get_post( $person_id );
		$references = array();
		$jsonld     = $this->entity_post_to_jsonld_converter->convert( $post->ID, $references );

		$this->assertTrue( is_array( $jsonld ) );
		$this->assertArrayHasKey( '@context', $jsonld );
		$this->assertEquals( 'http://schema.org', $jsonld['@context'] );

		$this->assertArrayHasKey( '@id', $jsonld );
		$this->assertEquals( $person_uri, $jsonld['@id'] );

		$this->assertArrayHasKey( '@type', $jsonld );
		$this->assertEquals( 'Person', $jsonld['@type'] );

		$this->assertArrayHasKey( 'name', $jsonld );
		$this->assertEquals( $name, $jsonld['name'] );

		$this->assertArrayHasKey( 'url', $jsonld );
		$this->assertEquals( get_permalink( $person_id ), $jsonld['url'] );

		$this->assertArrayHasKey( 'birthDate', $jsonld );
		$this->assertEquals( $birth_date, $jsonld['birthDate'] );

		$this->assertArrayHasKey( 'email', $jsonld );
		$this->assertEquals( $email, $jsonld['email'] );

		$this->assertArrayHasKey( 'sameAs', $jsonld );
		$this->assertEquals( $same_as, $jsonld['sameAs'] );

		$this->assertArrayHasKey( 'birthPlace', $jsonld );
		$this->assertArrayHasKey( '@id', $jsonld['birthPlace'] );
		$this->assertEquals( $place_uri, $jsonld['birthPlace']['@id'] );

		$this->assertContains( $place_id, $references );

		$this->assertCount( 2, $jsonld['knows'] );

		$this->assertContains( array( '@id' => $knows_uri_1 ), $jsonld['knows'] );
		$this->assertContains( array( '@id' => $knows_uri_2 ), $jsonld['knows'] );

		$references_2 = array();
		$this->assertEquals( $jsonld, $this->postid_to_jsonld_converter->convert( $person_id, $references_2 ) );
		$this->assertEquals( $references, $references_2 );

	}

	/**
	 * Test the conversion of a local business entity {@link WP_Post} to a JSON-LD array.
	 *
	 * @since 3.8.0
	 */
	public function test_local_business_conversion() {

		// Create a location entity post and bind it to the location property.
		$name              = rand_str();
		$local_business_id = $this->factory->post->create( array(
			'post_title' => $name,
			'post_type'  => 'entity',
		) );
		$this->entity_type_service->set( $local_business_id, 'http://schema.org/LocalBusiness' );
		$local_business_uri = $this->entity_service->get_uri( $local_business_id );

		// Set the geo coordinates.
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_GEO_LATITUDE, 12.34 );
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_GEO_LONGITUDE, 1.23 );

		$email = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_EMAIL, $email );

		$phone = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_TELEPHONE, $phone );

		// Set a random sameAs.
		$same_as = 'http://example.org/aRandomSameAs';
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_SAME_AS, $same_as );

		$address = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_ADDRESS, $address );

		$po_box = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_ADDRESS_PO_BOX, $po_box );

		$postal_code = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_ADDRESS_POSTAL_CODE, $postal_code );

		$locality = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_ADDRESS_LOCALITY, $locality );

		$region = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_ADDRESS_REGION, $region );

		$country = rand_str();
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_ADDRESS_COUNTRY, $country );

		$person_id = $this->factory->post->create( array( 'post_type' => 'entity' ) );
		$this->entity_type_service->set( $person_id, 'http://schema.org/Person' );
		$person_uri = $this->entity_service->get_uri( $person_id );

		// Bind the person as author of the creative work.
		add_post_meta( $local_business_id, Wordlift_Schema_Service::FIELD_FOUNDER, $person_id );

		$post       = get_post( $local_business_id );
		$references = array();
		$jsonld     = $this->entity_post_to_jsonld_converter->convert( $post->ID, $references );

		$this->assertTrue( is_array( $jsonld ) );
		$this->assertArrayHasKey( '@context', $jsonld );
		$this->assertEquals( 'http://schema.org', $jsonld['@context'] );

		$this->assertArrayHasKey( '@id', $jsonld );
		$this->assertEquals( $local_business_uri, $jsonld['@id'] );

		$this->assertArrayHasKey( '@type', $jsonld );
		$this->assertEquals( 'LocalBusiness', $jsonld['@type'] );

		$this->assertArrayHasKey( 'name', $jsonld );
		$this->assertEquals( $name, $jsonld['name'] );

		$this->assertArrayHasKey( 'url', $jsonld );
		$this->assertEquals( get_permalink( $local_business_id ), $jsonld['url'] );

		$this->assertArrayHasKey( 'sameAs', $jsonld );
		$this->assertEquals( $same_as, $jsonld['sameAs'] );

		$this->assertArrayHasKey( 'email', $jsonld );
		$this->assertEquals( $email, $jsonld['email'] );

		$this->assertArrayHasKey( 'telephone', $jsonld );
		$this->assertEquals( $phone, $jsonld['telephone'] );

		$this->assertArrayHasKey( 'geo', $jsonld );

		$this->assertArrayHasKey( '@type', $jsonld['geo'] );
		$this->assertEquals( 'GeoCoordinates', $jsonld['geo']['@type'] );

		$this->assertArrayHasKey( 'latitude', $jsonld['geo'] );
		$this->assertEquals( 12.34, $jsonld['geo']['latitude'] );

		$this->assertArrayHasKey( 'longitude', $jsonld['geo'] );
		$this->assertEquals( 1.23, $jsonld['geo']['longitude'] );


		$this->assertArrayHasKey( 'address', $jsonld );

		$this->assertArrayHasKey( '@type', $jsonld['address'] );
		$this->assertEquals( 'PostalAddress', $jsonld['address']['@type'] );

		$this->assertEquals( $address, $jsonld['address']['streetAddress'] );
		$this->assertEquals( $po_box, $jsonld['address']['postOfficeBoxNumber'] );
		$this->assertEquals( $postal_code, $jsonld['address']['postalCode'] );
		$this->assertEquals( $locality, $jsonld['address']['addressLocality'] );
		$this->assertEquals( $region, $jsonld['address']['addressRegion'] );
		$this->assertEquals( $country, $jsonld['address']['addressCountry'] );

		$this->assertArrayHasKey( 'founder', $jsonld );

		$this->assertArrayHasKey( '@id', $jsonld['founder'] );
		$this->assertEquals( $person_uri, $jsonld['founder']['@id'] );

		$this->assertContains( $person_id, $references );

		$references_2 = array();
		$this->assertEquals( $jsonld, $this->postid_to_jsonld_converter->convert( $local_business_id, $references_2 ) );
		$this->assertEquals( $references, $references_2 );

	}

}
