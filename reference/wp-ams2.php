<?php

/*
 * AMS Enrolment v2 — WordPress reference implementation.
 *
 * Verbatim extract of the ams2_* section of the live WordPress child-theme
 * functions.php: lines 12510-15412 of docs/ENROLMENT_V2.md, which is a dump
 * of that whole file pasted twice (both copies are identical).
 *
 * Reference only - never autoloaded or executed. The Laravel port must match
 * the BEHAVIOUR here, not the structure.
 */

/* 1. Admin menu: "Enrolment Emails" post type */
add_action( 'init', function () {
	register_post_type( 'ams_enrol_email', array(
		'labels'          => array(
			'name'          => 'Enrolment Emails',
			'singular_name' => 'Enrolment Email',
			'add_new'       => 'Add Email',
			'add_new_item'  => 'Add Enrolment Email',
			'edit_item'     => 'Edit Enrolment Email',
			'all_items'     => 'All Enrolment Emails',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_position'   => 26,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
	) );
} );
 
/* 2. ACF fields */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
 
	acf_add_local_field_group( array(
		'key'      => 'group_ams_enrol_email',
		'title'    => 'Enrolment Email',
		'location' => array( array( array(
			'param'    => 'post_type',
			'operator' => '==',
			'value'    => 'ams_enrol_email',
		) ) ),
		'fields'   => array(
			array(
				'key'          => 'field_ams_course_id',
				'name'         => 'course_id',
				'label'        => 'Course ID',
				'type'         => 'number',
				'required'     => 1,
				'instructions' => 'course_id from course_data (e.g. 32)',
				'wrapper'      => array( 'width' => 50 ),
			),
			array(
				'key'          => 'field_ams_plan_id',
				'name'         => 'plan_id',
				'label'        => 'Plan ID',
				'type'         => 'number',
				'instructions' => 'plan_id from course_data (e.g. 38)',
				'wrapper'      => array( 'width' => 50 ),
			),
			array(
				'key'          => 'field_ams_email_body',
				'name'         => 'email_body',
				'label'        => 'Email Message Body (HTML)',
				'type'         => 'textarea',
				'rows'         => 30,
				'new_lines'    => '', // keep pasted HTML exactly as-is
				'instructions' => 'Paste the full HTML template. Placeholders: {first_name} {last_name}',
			),
			array(
				'key'           => 'field_ams_attachment_1',
				'name'          => 'attachment_1',
				'label'         => 'Attachment 1',
				'type'          => 'file',
				'return_format' => 'id',
				'instructions'  => 'e.g. Learner Handbook',
				'wrapper'       => array( 'width' => 33 ),
			),
			array(
				'key'           => 'field_ams_attachment_2',
				'name'          => 'attachment_2',
				'label'         => 'Attachment 2',
				'type'          => 'file',
				'return_format' => 'id',
				'instructions'  => 'e.g. Learner Guide',
				'wrapper'       => array( 'width' => 33 ),
			),
			array(
				'key'           => 'field_ams_attachment_3',
				'name'          => 'attachment_3',
				'label'         => 'Attachment 3',
				'type'          => 'file',
				'return_format' => 'id',
				'instructions'  => 'e.g. Schedule of Fees and Charges',
				'wrapper'       => array( 'width' => 33 ),
			),
		),
	) );
} );
 
/* 3. Show Course ID / Plan ID in the list screen */
add_filter( 'manage_ams_enrol_email_posts_columns', function ( $cols ) {
	$cols['course_id'] = 'Course ID';
	$cols['plan_id']   = 'Plan ID';
	return $cols;
} );
 
add_action( 'manage_ams_enrol_email_posts_custom_column', function ( $col, $post_id ) {
	if ( in_array( $col, array( 'course_id', 'plan_id' ), true ) ) {
		echo esc_html( get_post_meta( $post_id, $col, true ) );
	}
}, 10, 2 );
 /**
 * TEST shortcode – AMS enrolment email
 * Paste at the bottom of functions.php (below the Enrolment Emails code).
 *
 * Usage on any page:   [ams_test_enrolment_email]
 * Optional attributes: [ams_test_enrolment_email course_id="32" plan_id="38" to="you@example.com"]
 *
 * Only visible to logged-in admins. Nothing sends until you click the button.
 */
add_shortcode( 'ams_test_enrolment_email', function ( $atts ) {

	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$atts = shortcode_atts( array(
		'course_id'  => 32,
		'plan_id'    => 38,
		'to'         => wp_get_current_user()->user_email,
		'first_name' => 'TEST ARI',
		'last_name'  => 'TEST ARI',
		'subject'    => 'TEST Enrolment confirmation RIIWHS204E - Work Safely at Heights Online Refresher',
	), $atts );

	// Find the Enrolment Email post for this course + plan.
	$posts = get_posts( array(
		'post_type'      => 'ams_enrol_email',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array( 'key' => 'course_id', 'value' => (int) $atts['course_id'] ),
			array( 'key' => 'plan_id', 'value' => (int) $atts['plan_id'] ),
		),
	) );

	$out  = '<div style="border:2px dashed #999;padding:20px;margin:20px 0;font-family:Arial,sans-serif;">';
	$out .= '<strong>Enrolment email test</strong><br>';

	if ( ! $posts ) {
		return $out . '❌ No published Enrolment Email found for course_id ' . (int) $atts['course_id'] . ' / plan_id ' . (int) $atts['plan_id'] . '.</div>';
	}

	$template = $posts[0];
	$out     .= 'Template: ' . esc_html( $template->post_title ) . '<br>';
	$out     .= 'Send to: ' . esc_html( $atts['to'] ) . '<br>';

	// Send only when the button is clicked.
	if ( isset( $_POST['ams_test_send'] ) && wp_verify_nonce( $_POST['_ams_test_nonce'] ?? '', 'ams_test_send' ) ) {

		$body = (string) get_post_meta( $template->ID, 'email_body', true );
		$body = str_replace(
			array( '{first_name}', '{last_name}' ),
			array( esc_html( $atts['first_name'] ), esc_html( $atts['last_name'] ) ),
			$body
		);

		$attachments = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$file_id = (int) get_post_meta( $template->ID, 'attachment_' . $i, true );
			$path    = $file_id ? get_attached_file( $file_id ) : '';
			if ( $path && file_exists( $path ) ) {
				$attachments[] = $path;
			}
		}

		// Capture any mail error to show on screen.
		$error = '';
		$catch = function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );

		$sent = wp_mail(
			sanitize_email( $atts['to'] ),
			$atts['subject'],
			$body,
			array( 'Content-Type: text/html; charset=UTF-8' ),
			$attachments
		);

		remove_action( 'wp_mail_failed', $catch );

		$out .= '<p style="margin-top:10px;">' . ( $sent
			? '✅ Sent with ' . count( $attachments ) . ' attachment(s).'
			: '❌ Failed: ' . esc_html( $error ?: 'unknown error – check WP Mail SMTP email log.' ) ) . '</p>';
	}

	$out .= '<form method="post" style="margin-top:10px;">'
		. wp_nonce_field( 'ams_test_send', '_ams_test_nonce', true, false )
		. '<button type="submit" name="ams_test_send" value="1" style="padding:8px 16px;cursor:pointer;">Send test email</button>'
		. '</form></div>';

	return $out;
} );



/**
 * ============================================================
 * AMS2 enrolment flow (ADDITIVE, runs alongside the existing flow)
 *
 * Enrolment links for the courses in AMS2_TEST_COURSES are routed to a new page.
 * Each enrolment then goes through these steps in a background cron:
 *   1. Enrol in RTO Data with the real details and a placeholder email (nothing is sent)
 *   2. Send the documents to that enrolment
 *   3. Update email and other_email to the real address, clear contact_email
 *   4. Send the confirmation email from Enrolment Emails
 *
 * Needs in wp-config.php:   define( 'AMS_RTODATA_PUBLIC_KEY', '...' );
 * Needs a page at AMS2_PAGE_PATH containing [ams2_enrolment_form]
 * Status page (admin only): [ams2_status]
 *
 * Reuses from the existing code: enrolment_form_shortcode70(), ams_course_map(),
 * convert_indigenous_status(), rtodata_upload_document(), ams_docs_dir(),
 * ams_detect_mime(), ams_log(), ams_log_client, ams_alert_recipients().
 * ============================================================
 */

if ( ! defined( 'AMS2_TEST_COURSES' ) ) {
	define( 'AMS2_TEST_COURSES', array(
		array( 'course_id' => 18, 'plan_id' => 61 ),
	) );
}
if ( ! defined( 'AMS2_PAGE_PATH' ) ) {
	define( 'AMS2_PAGE_PATH', '/enrolment-v2/' );
}
if ( ! defined( 'AMS2_ADMIN_ONLY' ) ) {
	define( 'AMS2_ADMIN_ONLY', false ); // Set to false to route real students.
}
if ( ! defined( 'AMS2_MAX_ATTEMPTS' ) ) {
	define( 'AMS2_MAX_ATTEMPTS', 5 );
}
if ( ! defined( 'AMS2_ALERTS_ENABLED' ) ) {
	define( 'AMS2_ALERTS_ENABLED', true ); // true = send "Enrolment needs attention" emails
}
/* ---------- Basics ---------- */

function ams2_table() {
	global $wpdb;
	return $wpdb->prefix . 'ams2_enrolments';
}

function ams2_docs_table() {
	global $wpdb;
	return $wpdb->prefix . 'ams2_documents';
}

function ams2_doc_types() {
	return array(
		'id_document'    => array( 'file' => 'ID-Document', 'label' => 'ID document' ),
		'vet_transcript' => array( 'file' => 'VET-Transcript', 'label' => 'VET transcript' ),
	);
}

if ( ! defined( 'AMS2_ALL_COURSES' ) ) {
	define( 'AMS2_ALL_COURSES', true ); // true = any course/plan with a published Enrolment Email
}

function ams2_has_enrolment_email( $course_id, $plan_id ) {
	static $cache = array();
	$key = (int) $course_id . ':' . (int) $plan_id;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$ids = get_posts( array(
		'post_type'      => 'ams_enrol_email',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => 'course_id', 'value' => (int) $course_id ),
			array( 'key' => 'plan_id', 'value' => (int) $plan_id ),
		),
	) );
	return $cache[ $key ] = ! empty( $ids );
}

function ams2_is_test_course( $course_id, $plan_id ) {
	if ( ! (int) $course_id || ! (int) $plan_id ) {
		return false;
	}
	if ( AMS2_ALL_COURSES ) {
		return ams2_has_enrolment_email( $course_id, $plan_id );
	}
	foreach ( AMS2_TEST_COURSES as $c ) {
		if ( (int) $c['course_id'] === (int) $course_id && (int) $c['plan_id'] === (int) $plan_id ) {
			return true;
		}
	}
	return false;
}

function ams2_course_id_from_code( $code ) {
	$code = strtoupper( sanitize_text_field( (string) $code ) );
	if ( '' !== $code && ctype_digit( $code ) ) {
		return (int) $code;
	}
	$map = function_exists( 'ams_course_map' ) ? ams_course_map() : array();
	return (int) ( $map[ $code ] ?? 0 );
}

function ams2_log( $level, $event, $id, $message = '', $extra = array() ) {
	$extra = array_merge( array( 'flow' => 'ams2', 'request_id' => (int) $id ), $extra );
	if ( function_exists( 'ams_log' ) ) {
		ams_log( $level, 'ams2_' . $event, $message, $extra );
		return;
	}
	error_log( '[AMS2 ' . strtoupper( $level ) . '] ' . $event . ' id=' . (int) $id . ' ' . $message );
}

function ams2_row( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ams2_table() . ' WHERE id = %d', (int) $id ) );
}

function ams2_row_by_key( $secret_key ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ams2_table() . ' WHERE secret_key = %s LIMIT 1', $secret_key ) );
}

function ams2_set( $id, $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql' );
	$wpdb->update( ams2_table(), $data, array( 'id' => (int) $id ) );
}

// Atomically move a row from one status to another. Only one process can win.
function ams2_claim( $id, $from, $to ) {
	global $wpdb;
	return 1 === (int) $wpdb->query( $wpdb->prepare(
		'UPDATE ' . ams2_table() . ' SET status = %s, updated_at = %s WHERE id = %d AND status = %s',
		$to,
		current_time( 'mysql' ),
		(int) $id,
		$from
	) );
}

function ams2_schedule( $id ) {
	if ( ! wp_next_scheduled( 'ams2_process', array( (int) $id ) ) ) {
		wp_schedule_single_event( time() + 5, 'ams2_process', array( (int) $id ) );
	}
}

/* ---------- Tables (created once) ---------- */

add_action( 'init', function () {
	if ( get_option( 'ams2_db_version' ) === '1' ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$collate = $wpdb->get_charset_collate();

	dbDelta( 'CREATE TABLE ' . ams2_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		secret_key varchar(128) NOT NULL,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		course_id int(11) NOT NULL DEFAULT 0,
		plan_id int(11) NOT NULL DEFAULT 0,
		course_code varchar(64) NOT NULL DEFAULT '',
		course_title varchar(255) NOT NULL DEFAULT '',
		student_data longtext NOT NULL,
		real_email varchar(255) NOT NULL DEFAULT '',
		docs_expected varchar(255) NOT NULL DEFAULT '',
		docs_final tinyint(1) NOT NULL DEFAULT 0,
		enrolment_id varchar(32) NULL,
		learner_id varchar(32) NULL,
		status varchar(32) NOT NULL DEFAULT 'received',
		resume_status varchar(32) NOT NULL DEFAULT '',
		attempts int(11) NOT NULL DEFAULT 0,
		error_message text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		email_sent_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY secret_key (secret_key),
		KEY status (status)
	) {$collate};" );

	dbDelta( 'CREATE TABLE ' . ams2_docs_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		enrolment_row bigint(20) unsigned NOT NULL,
		doc_type varchar(32) NOT NULL,
		file_path varchar(500) NOT NULL,
		original_name varchar(255) NOT NULL DEFAULT '',
		mime varchar(100) NOT NULL DEFAULT '',
		size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'pending',
		attempts int(11) NOT NULL DEFAULT 0,
		error_message text NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		sent_at datetime NULL,
		PRIMARY KEY  (id),
		KEY enrolment_row (enrolment_row),
		KEY status (status)
	) {$collate};" );

	update_option( 'ams2_db_version', '1' );
} );

/* ---------- Enrolment link lookup ---------- */

function ams2_link_info( $row ) {
	$invalid = array( 'state' => 'invalid' );

	if ( ! $row || empty( $row->OrderID ) || ! function_exists( 'wc_get_order' ) ) {
		return $invalid;
	}

	$order = wc_get_order( (int) $row->OrderID );
	if ( ! $order ) {
		return $invalid;
	}

	$created = $order->get_date_created();
	$expired = $created && ( time() - $created->getTimestamp() ) > 125 * DAY_IN_SECONDS;

	$q = array();
	parse_str( (string) parse_url( (string) $row->secretBase, PHP_URL_QUERY ), $q );
	$code = strtoupper( sanitize_text_field( $q['code'] ?? '' ) );

	return array(
		'state'       => $expired ? 'expired' : 'ok',
		'order_id'    => (int) $row->OrderID,
		'course_code' => $code,
		'course_id'   => ams2_course_id_from_code( $code ),
		'plan_id'     => absint( $q['plan'] ?? 0 ),
		'client_id'   => sanitize_text_field( $q['company'] ?? '' ),
		'first_name'  => (string) ( $row->firstName ?? '' ),
		'last_name'   => (string) ( $row->lastName ?? '' ),
		'email'       => (string) ( $row->emailaddress ?? '' ),
	);
}

function ams2_key_info( $secret_key ) {
	global $wpdb;

	if ( ! preg_match( '/^[A-Za-z0-9]{16,128}$/', (string) $secret_key ) ) {
		return array( 'state' => 'invalid' );
	}

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT OrderID, secretBase, firstName, lastName, emailaddress FROM {$wpdb->prefix}secret_keys WHERE secret_key = %s LIMIT 1",
		$secret_key
	) );

	return ams2_link_info( $row );
}

/* ---------- Routing: /enrol/{id} for test courses goes to the new page ---------- */

// Priority 5 runs before the existing /enrol/ handler. Anything not routed here falls through to it unchanged.
add_action( 'template_redirect', 'ams2_route_enrol_link', 5 );

function ams2_in_old_flow( $secret_key ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$wpdb->prefix}enrolment_requests WHERE secret_key = %s LIMIT 1",
		$secret_key
	) );
}

function ams2_route_enrol_link() {
	$link_id = absint( get_query_var( 'order_id' ) );
	if ( ! $link_id ) {
		return;
	}

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT secret_key, OrderID, secretBase FROM {$wpdb->prefix}secret_keys WHERE ID = %d LIMIT 1",
		$link_id
	) );

	if ( ! $row || empty( $row->secret_key ) ) {
		return;
	}

	$to_v2 = add_query_arg( 'secretKey', rawurlencode( $row->secret_key ), home_url( AMS2_PAGE_PATH ) );

	// Already submitted through v2: always send them back there, even after a rollback.
	if ( ams2_row_by_key( $row->secret_key ) ) {
		nocache_headers();
		wp_redirect( $to_v2, 302 );
		exit;
	}

	if ( AMS2_ADMIN_ONLY && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Started on the old form: leave it with the old flow.
	if ( ams2_in_old_flow( $row->secret_key ) ) {
		return;
	}

	$info = ams2_link_info( $row );
	if ( 'invalid' === $info['state'] || ! ams2_is_test_course( $info['course_id'], $info['plan_id'] ) ) {
		return;
	}

	nocache_headers();
	wp_redirect( $to_v2, 302 );
	exit;
}

/* ---------- AJAX helpers ---------- */

function ams2_json_fail( $message, $code, $status = 400, $id = 0, $doc_type = '' ) {
	ams2_log( $status >= 500 ? 'error' : 'warning', 'ajax_' . $code, $id, $message, array( 'doc_type' => $doc_type ) );
	wp_send_json( array(
		'success' => false,
		'data'    => array( 'code' => $code, 'message' => $message ),
	), $status );
}

function ams2_check_nonce() {
	$nonce = sanitize_text_field( wp_unslash( $_POST['ams2_nonce'] ?? '' ) );
	if ( ! wp_verify_nonce( $nonce, 'ams2_enrol' ) ) {
		ams2_json_fail( 'Your session has expired. Refresh the page and try again.', 'nonce', 403 );
	}
}

add_action( 'wp_ajax_ams2_nonce', 'ams2_nonce' );
add_action( 'wp_ajax_nopriv_ams2_nonce', 'ams2_nonce' );
function ams2_nonce() {
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'ams2_enrol' ) ) );
}

/* ---------- Stage 1: save the enrolment (no files) ---------- */

add_action( 'wp_ajax_ams2_submit', 'ams2_submit' );
add_action( 'wp_ajax_nopriv_ams2_submit', 'ams2_submit' );

function ams2_submit() {
	global $wpdb;

	ams2_check_nonce();

	$key  = sanitize_text_field( wp_unslash( $_POST['secretKey'] ?? '' ) );
	$info = ams2_key_info( $key );

	if ( 'expired' === $info['state'] ) {
		ams2_json_fail( 'This enrolment link has expired. Your enrolment has not been submitted. Contact AMS Training for a new link.', 'expired', 403 );
	}
	if ( 'ok' !== $info['state'] ) {
		ams2_json_fail( 'This enrolment link is not valid. Your enrolment has not been submitted. Contact AMS Training for a new link.', 'invalid_key', 403 );
	}
	if ( ! ams2_is_test_course( $info['course_id'], $info['plan_id'] ) ) {
		ams2_json_fail( 'This enrolment link is not set up for this form. Your enrolment has not been submitted. Contact AMS Training.', 'not_test_course', 403 );
	}
   	if ( ams2_in_old_flow( $key ) ) {
		ams2_json_fail( 'Your enrolment has already been submitted. Contact AMS Training if you need to change anything.', 'already_submitted_old', 409 );
	}
	// Resubmitted after a dropped connection: return the same row so documents can still attach.
	$existing = ams2_row_by_key( $key );
	if ( $existing ) {
		wp_send_json_success( array( 'id' => (int) $existing->id, 'already_submitted' => true ) );
	}

	foreach ( array( 'first_name', 'last_name', 'date_of_birth', 'email' ) as $field ) {
		if ( '' === trim( (string) ( $_POST[ $field ] ?? '' ) ) ) {
			ams2_json_fail( 'Some required details are missing. Check the form and try again. Your enrolment has not been submitted.', 'missing_field' );
		}
	}

	$sd = wp_unslash( $_POST );
	foreach ( array( 'action', 'ams2_nonce', 'enrolment_nonce', '_wp_http_referer', 'submit_enrolment', 'ams_action', 'ams2_noop', 'secretKey', 'course_id', 'plan_id', 'client_id', 'doc_expected' ) as $k ) {
		unset( $sd[ $k ] );
	}
	array_walk_recursive( $sd, function ( &$v ) {
		$v = sanitize_text_field( (string) $v );
	} );

	$state_map = array(
		'NSW' => '01', 'VIC' => '02', 'QLD' => '03', 'SA' => '04',
		'WA'  => '05', 'TAS' => '06', 'NT'  => '07', 'ACT' => '08',
	);
	$sd['state']     = $state_map[ $sd['state'] ?? '' ] ?? '';
	$sd['client_id'] = $info['client_id']; // From the link, not the browser.

	$email = sanitize_email( $sd['email'] ?? '' );
	if ( ! is_email( $email ) ) {
		ams2_json_fail( 'The email address is not valid. Check it and try again. Your enrolment has not been submitted.', 'bad_email' );
	}

	$expected = array_values( array_intersect(
		array_keys( ams2_doc_types() ),
		array_map( 'sanitize_key', explode( ',', (string) ( $_POST['doc_expected'] ?? '' ) ) )
	) );

	$now = current_time( 'mysql' );
	$ok  = $wpdb->insert( ams2_table(), array(
		'secret_key'    => $key,
		'order_id'      => $info['order_id'],
		'course_id'     => $info['course_id'],
		'plan_id'       => $info['plan_id'],
		'course_code'   => $info['course_code'],
		'student_data'  => wp_json_encode( $sd ),
		'real_email'    => $email,
		'docs_expected' => implode( ',', $expected ),
		'docs_final'    => $expected ? 0 : 1,
		'status'        => 'received',
		'attempts'      => 0,
		'created_at'    => $now,
		'updated_at'    => $now,
	) );

	if ( ! $ok ) {
		$existing = ams2_row_by_key( $key );
		if ( $existing ) {
			wp_send_json_success( array( 'id' => (int) $existing->id, 'already_submitted' => true ) );
		}
		ams2_json_fail( 'We could not save your enrolment. Your enrolment has not been submitted. Try again in a minute.', 'db', 500 );
	}

	$id = (int) $wpdb->insert_id;
	ams2_log( 'info', 'enrolment_saved', $id, '', array( 'docs_expected' => implode( ',', $expected ) ) );
	ams2_schedule( $id );

	wp_send_json_success( array( 'id' => $id, 'already_submitted' => false ) );
}

/* ---------- Stage 2: one document per request ---------- */

add_action( 'wp_ajax_ams2_upload', 'ams2_upload' );
add_action( 'wp_ajax_nopriv_ams2_upload', 'ams2_upload' );

function ams2_upload() {
	global $wpdb;

	ams2_check_nonce();

	$key   = sanitize_text_field( wp_unslash( $_POST['secretKey'] ?? '' ) );
	$id    = absint( $_POST['id'] ?? 0 );
	$type  = sanitize_key( $_POST['doc_type'] ?? '' );
	$types = ams2_doc_types();

	if ( ! isset( $types[ $type ] ) ) {
		ams2_json_fail( 'Unknown document type.', 'bad_type', 400, $id );
	}

	$row = $wpdb->get_row( $wpdb->prepare(
		'SELECT id, enrolment_id, created_at FROM ' . ams2_table() . ' WHERE id = %d AND secret_key = %s LIMIT 1',
		$id,
		$key
	) );

	if ( ! $row ) {
		ams2_json_fail( 'We could not match this document to your enrolment. Email it to sales@amstraining.com.au instead.', 'no_match', 403, $id, $type );
	}
	if ( strtotime( $row->created_at ) < current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS ) {
		ams2_json_fail( 'Online uploads for this enrolment have closed. Email the document to sales@amstraining.com.au.', 'closed', 403, $id, $type );
	}
	if ( empty( $_FILES['document'] ) ) {
		ams2_json_fail( 'No file was received. Try again.', 'no_file', 400, $id, $type );
	}

	$file = $_FILES['document'];
	$max  = defined( 'AMS_DOC_MAX_BYTES' ) ? AMS_DOC_MAX_BYTES : 10 * 1024 * 1024;

	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE  => 'This file is too large.',
			UPLOAD_ERR_FORM_SIZE => 'This file is too large.',
			UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Check your connection and try again.',
			UPLOAD_ERR_NO_FILE   => 'No file was received. Try again.',
		);
		ams2_json_fail( $messages[ $file['error'] ] ?? 'The server could not receive this file. Try again.', 'upload_error_' . (int) $file['error'], 400, $id, $type );
	}
	if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
		ams2_json_fail( 'The server could not receive this file. Try again.', 'not_uploaded', 400, $id, $type );
	}
	if ( $file['size'] > $max ) {
		ams2_json_fail( 'This file is too large.', 'too_large', 400, $id, $type );
	}

	$allowed = array(
		'application/pdf' => 'pdf',
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
		'image/webp'      => 'webp',
	);
	$mime = function_exists( 'ams_detect_mime' ) ? ams_detect_mime( $file['tmp_name'] ) : (string) mime_content_type( $file['tmp_name'] );

	if ( ! isset( $allowed[ $mime ] ) ) {
		ams2_json_fail( 'This file type is not accepted. Upload a PDF, JPG, PNG or WEBP file.', 'bad_mime', 400, $id, $type );
	}

	$dir = function_exists( 'ams_docs_dir' ) ? ams_docs_dir() : false;
	if ( ! $dir ) {
		ams2_json_fail( 'The server could not store this file. Try again later.', 'no_dir', 500, $id, $type );
	}

	$target = $dir . '/ams2-' . $types[ $type ]['file'] . '-' . $id . '-' . wp_generate_password( 12, false ) . '.' . $allowed[ $mime ];

	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
		ams2_json_fail( 'The server could not store this file. Try again later.', 'move_failed', 500, $id, $type );
	}
	chmod( $target, 0640 );

	$docs = ams2_docs_table();
	$now  = current_time( 'mysql' );

	// A retry replaces any unsent file of the same type.
	$old = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, file_path FROM {$docs} WHERE enrolment_row = %d AND doc_type = %s AND status IN ('pending','retry','failed')",
		$id,
		$type
	) );
	foreach ( $old as $o ) {
		if ( file_exists( $o->file_path ) ) {
			unlink( $o->file_path );
		}
		$wpdb->update( $docs, array( 'status' => 'replaced', 'updated_at' => $now ), array( 'id' => $o->id ) );
	}

	$wpdb->insert( $docs, array(
		'enrolment_row' => $id,
		'doc_type'      => $type,
		'file_path'     => $target,
		'original_name' => sanitize_file_name( $file['name'] ),
		'mime'          => $mime,
		'size_bytes'    => (int) $file['size'],
		'status'        => 'pending',
		'attempts'      => 0,
		'created_at'    => $now,
		'updated_at'    => $now,
	) );

	if ( ! $wpdb->insert_id ) {
		unlink( $target );
		ams2_json_fail( 'The server could not store this file. Try again later.', 'db', 500, $id, $type );
	}

	ams2_log( 'info', 'document_stored', $id, basename( $target ), array(
		'doc_type'  => $type,
		'file_name' => sanitize_file_name( $file['name'] ),
		'file_size' => (int) $file['size'],
		'mime'      => $mime,
	) );

	if ( ! empty( $row->enrolment_id ) ) {
		ams2_schedule( $id );
	}

	wp_send_json_success( array( 'doc_type' => $type ) );
}

/* ---------- Browser says it has finished uploading ---------- */

add_action( 'wp_ajax_ams2_finalise', 'ams2_finalise' );
add_action( 'wp_ajax_nopriv_ams2_finalise', 'ams2_finalise' );

function ams2_finalise() {
	global $wpdb;

	$key = sanitize_text_field( wp_unslash( $_POST['secretKey'] ?? '' ) );
	$id  = absint( $_POST['id'] ?? 0 );

	$match = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT id FROM ' . ams2_table() . ' WHERE id = %d AND secret_key = %s LIMIT 1',
		$id,
		$key
	) );

	if ( $match ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . ams2_table() . ' SET docs_final = 1 WHERE id = %d', $id ) );
		ams2_log( 'info', 'uploads_finalised', $id );
		ams2_schedule( $id );
	}

	wp_send_json_success();
}

/* ---------- RTO Data request ---------- */

function ams2_api( $args, $endpoint ) {
	if ( ! defined( 'AMS_RTODATA_PUBLIC_KEY' ) || '' === AMS_RTODATA_PUBLIC_KEY ) {
		return array( 'sent' => false, 'error' => 'AMS_RTODATA_PUBLIC_KEY is not defined in wp-config.php', 'http' => 0, 'body' => null, 'raw' => '' );
	}

	$args['public_key'] = AMS_RTODATA_PUBLIC_KEY;
	$args['subdomain']  = 'amstraining';

	$ch = curl_init( 'https://amstraining.rtodata.com.au/api/v2/' . $endpoint );
	curl_setopt_array( $ch, array(
		CURLOPT_CUSTOMREQUEST  => 'POST',
		CURLOPT_POSTFIELDS     => json_encode( $args ),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_POSTREDIR      => 3,
		CURLOPT_HTTPHEADER     => array( 'Origin: https://' . ( $_SERVER['SERVER_NAME'] ?? parse_url( home_url(), PHP_URL_HOST ) ) ),
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_TIMEOUT        => 90,
	) );

	$raw   = curl_exec( $ch );
	$errno = curl_errno( $ch );
	$error = curl_error( $ch );
	$http  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	return array(
		'sent'  => ! $errno,
		'error' => $errno ? $error : '',
		'http'  => $http,
		'body'  => $errno ? null : json_decode( (string) $raw, true ),
		'raw'   => substr( (string) $raw, 0, 2000 ),
	);
}

/* ---------- Failure handling and alerts ---------- */
/**
 * Sends a plain-text alert through Two Moons' SMTP2GO account (API, not wp_mail).
 * Bypasses WP Mail SMTP and AMS's SMTP2GO account entirely.
 */

/**
 * Sends a plain-text alert through Two Moons' SMTP2GO account (API, not wp_mail).
 * On failure the reason is in $GLOBALS['ams_tm_mail_error'] and the PHP error log.
 */
function ams_twomoons_mail( $to, $subject, $body ) {
	$GLOBALS['ams_tm_mail_error'] = '';

	$fail = function ( $message ) {
		$GLOBALS['ams_tm_mail_error'] = $message;
		error_log( '[AMS] SMTP2GO send failed: ' . $message );
		return false;
	};

	if ( ! defined( 'TM_SMTP2GO_API_KEY' ) || '' === trim( (string) TM_SMTP2GO_API_KEY ) ) {
		return $fail( 'TM_SMTP2GO_API_KEY is not defined in wp-config.php (or wp-config.php was not saved).' );
	}

	$res = wp_remote_post( 'https://api.smtp2go.com/v3/email/send', array(
		'timeout'     => 20,
		'headers'     => array(
			'Content-Type'      => 'application/json',
			'Accept'            => 'application/json',
			'X-Smtp2go-Api-Key' => trim( (string) TM_SMTP2GO_API_KEY ),
		),
		'body'        => wp_json_encode( array(
			'sender'    => 'AMS Enrolment Alerts <info@twomoonsconsulting.com.au>',
			'to'        => array_values( (array) $to ),
			'subject'   => $subject,
			'text_body' => $body,
		) ),
		'data_format' => 'body',
	) );

	if ( is_wp_error( $res ) ) {
		return $fail( 'Could not connect to SMTP2GO: ' . $res->get_error_message() );
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $raw, true );
	$data = ( is_array( $json ) && isset( $json['data'] ) && is_array( $json['data'] ) ) ? $json['data'] : array();

	if ( $code >= 200 && $code < 300 && ! empty( $data['succeeded'] ) ) {
		return true;
	}

	$detail = array_filter( array(
		isset( $data['error_code'] ) ? 'error_code: ' . $data['error_code'] : '',
		isset( $data['error'] ) ? 'error: ' . ( is_scalar( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] ) ) : '',
		! empty( $data['failures'] ) ? 'failures: ' . wp_json_encode( $data['failures'] ) : '',
	) );

	return $fail( 'HTTP ' . $code . '. ' . ( $detail ? implode( ' | ', $detail ) : 'Raw response: ' . substr( $raw, 0, 500 ) ) );
}

/**
 * [ams_tm_mail_test] Admin-only: sends one test alert through Two Moons' SMTP2GO and shows the result.
 */
add_shortcode( 'ams_tm_mail_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$out = '';
	if ( isset( $_POST['ams_tm_mail_test'] ) && check_admin_referer( 'ams_tm_mail_test' ) ) {
		$sent = ams_twomoons_mail(
			array( 'developer@twomoonsconsulting.com.au' ),
			'AMS enrolment alerts: test (Two Moons SMTP2GO)',
			'Test alert from ' . home_url() . ' sent ' . wp_date( 'd/m/Y H:i:s' ) . '.'
		);

		$key  = defined( 'TM_SMTP2GO_API_KEY' ) ? trim( (string) TM_SMTP2GO_API_KEY ) : '';
		$out .= $sent
			? '<p><strong>Sent.</strong> Check developer@ and your SMTP2GO activity.</p>'
			: '<p><strong>Failed:</strong></p><pre style="white-space:pre-wrap;word-break:break-all">' . esc_html( $GLOBALS['ams_tm_mail_error'] ?? 'unknown' ) . '</pre>';
		$out .= '<p style="font-size:12px;color:#555">Key in use: ' . esc_html( $key ? substr( $key, 0, 8 ) . '…' . substr( $key, -4 ) : 'none' ) . '</p>';
	}

	return $out . '<form method="post">' . wp_nonce_field( 'ams_tm_mail_test', '_wpnonce', true, false )
		. '<button type="submit" name="ams_tm_mail_test" value="1">Send test alert</button></form>';
} );

function ams2_alert( $row, $heading, $details = array() ) {
	if ( ! $row ) {
		return;
	}

	if ( ! AMS2_ALERTS_ENABLED ) {
		ams2_log( 'info', 'alert_suppressed', $row->id, $heading );
		return;
	}

	$sd   = json_decode( (string) $row->student_data, true ) ?: array();
	$name = trim( ( $sd['first_name'] ?? '' ) . ' ' . ( $sd['last_name'] ?? '' ) );

	$lines = array_merge( array(
		$heading,
		'',
		'Reference: AMS2-' . $row->id,
		'Student: ' . ( $name ?: 'unknown' ),
		'Email: ' . $row->real_email,
		'Mobile: ' . ( $sd['mobile_phone'] ?? '' ),
		'Course ID / Plan ID: ' . $row->course_id . ' / ' . $row->plan_id,
		'RTO Data enrolment ID: ' . ( $row->enrolment_id ?: 'not created' ),
		'RTO Data learner ID: ' . ( $row->learner_id ?: 'not known' ),
		'Submitted: ' . date_i18n( 'd/m/Y H:i', strtotime( $row->created_at ) ),
		'',
	), $details );

	$to = function_exists( 'ams_alert_recipients' ) ? ams_alert_recipients() : array( get_option( 'admin_email' ) );

	$sent = ams_twomoons_mail(
		array( 'developer@twomoonsconsulting.com.au' ),
		'Enrolment needs attention: ' . ( $name ?: 'Unknown student' ) . ' (AMS2-' . $row->id . ')',
		implode( "\n", $lines )
	);

	ams2_log( $sent ? 'info' : 'error', $sent ? 'alert_sent' : 'alert_failed', $row->id, $heading );
}

/**
 * $back_to is the status the row returns to for a retry, and where Resume restarts it.
 * $retry false (or attempts used up) parks the row in needs_review and emails the alert list.
 */
function ams2_fail( $id, $back_to, $message, $retry = true ) {
	$row      = ams2_row( $id );
	$attempts = (int) $row->attempts + 1;

	if ( $retry && $attempts < AMS2_MAX_ATTEMPTS ) {
		ams2_set( $id, array( 'status' => $back_to, 'attempts' => $attempts, 'error_message' => $message ) );
		ams2_log( 'warning', 'step_failed', $id, $message, array( 'attempt' => $attempts, 'back_to' => $back_to ) );
		return;
	}

	ams2_set( $id, array(
		'status'        => 'needs_review',
		'resume_status' => $back_to,
		'attempts'      => $attempts,
		'error_message' => $message,
	) );
	ams2_log( 'error', 'needs_review', $id, $message, array( 'resume_status' => $back_to ) );

	$steps = array(
		'received'       => 'creating the enrolment in RTO Data',
		'enrolled'       => 'sending documents to RTO Data',
		'documents_done' => 'updating the student email in RTO Data',
		'updated'        => 'sending the confirmation email',
	);

	ams2_alert( ams2_row( $id ), 'This enrolment stopped and needs checking.', array(
		'Stopped while: ' . ( $steps[ $back_to ] ?? $back_to ),
		'Reason: ' . $message,
		'',
		'Fix the cause, then use Resume on the [ams2_status] page.',
	) );
}

/* ---------- Step 1: enrol with real details and placeholder emails ---------- */

function ams2_enrol_args( $sd, $row ) {
	$dob_obj = DateTime::createFromFormat( '!Y-m-d', (string) ( $sd['date_of_birth'] ?? '' ) );
	$fake    = 'processing' . (int) $row->id . 'hold.invalid';

	$disability_types = array_fill_keys( array( 11, 12, 13, 14, 15, 16, 17, 18, 19, 99 ), 0 );
	foreach ( (array) ( $sd['disability_type'] ?? array() ) as $code ) {
		$code = (int) $code;
		if ( isset( $disability_types[ $code ] ) ) {
			$disability_types[ $code ] = 1;
		}
	}
	if ( 1 === $disability_types[99] ) {
		foreach ( $disability_types as $k => $v ) {
			if ( 99 !== $k ) {
				$disability_types[ $k ] = 0;
			}
		}
	}

	$args = array(
		'first_name'           => strtoupper( trim( $sd['first_name'] ?? '' ) ),
		'last_name'            => strtoupper( trim( $sd['last_name'] ?? '' ) ),
		'date_of_birth'        => $dob_obj ? $dob_obj->format( 'Y-m-d' ) : '',
		'course_id'            => (int) $row->course_id,
		'plan_id'              => (int) $row->plan_id,
		'salutation'           => $sd['title'] ?? '',
		'gender'               => $sd['gender'] ?? '@',
		'town_of_birth'        => $sd['city_of_birth'] ?? '',
		'country_of_birth'     => $sd['country_of_birth'] ?? '',
		'building_property'    => $sd['building_name'] ?? '',
		'unit_flat'            => $sd['unit_number'] ?? '',
		'street_number'        => $sd['street_number'] ?? '',
		'street_name'          => $sd['street_name'] ?? '',
		'suburb'               => $sd['suburb'] ?? '',
		'state'                => $sd['state'] ?? '',
		'postcode'             => $sd['postcode'] ?? '',
		'country'              => '1101',
		'main_language'        => $sd['main_language'] ?? '',
		'disability_flag'      => $sd['disability'] ?? '',
		'disability_type'      => $disability_types,
		'labour_force'         => $sd['labour_force'] ?? '',
		'individual_needs'     => ( 'Yes' === ( $sd['individual_needs'] ?? '' ) ) ? ( $sd['individual_needs_specify'] ?? '' ) : '',
		'mobile_phone'         => $sd['mobile_phone'] ?? '',
		'usi_number'           => strtoupper( $sd['usi'] ?? '' ),
		'highest_school_level' => $sd['school_level'] ?? '',
		'at_school_flag'       => $sd['currently_at_school'] ?? '',
		'study_reason'         => $sd['study_reason'] ?? '',
		'client_id'            => $sd['client_id'] ?? '',
		'indigenous_status'    => function_exists( 'convert_indigenous_status' ) ? convert_indigenous_status( $sd['atsi_status'] ?? '' ) : '@',
		'email'                => $fake,
		'other_email'          => $fake,
	);

	if ( 'different' === ( $sd['postal_address_type'] ?? '' ) ) {
		$args['postal_flag']              = 1;
		$args['postal_box']               = $sd['postal_box'] ?? '';
		$args['postal_building_property'] = $sd['postal_building_property'] ?? '';
		$args['postal_unit_flat']         = $sd['postal_unit_flat'] ?? '';
		$args['postal_street_number']     = $sd['postal_street_number'] ?? '';
		$args['postal_street_name']       = $sd['postal_street_name'] ?? '';
		$args['postal_suburb']            = $sd['postal_suburb'] ?? '';
		$args['postal_state']             = $sd['postal_state'] ?? '';
		$args['postal_postcode']          = $sd['postal_postcode'] ?? '';
	} else {
		$args['postal_flag'] = 0;
	}

	$allowed_codes = array( '008', '410', '420', '511', '514', '521', '524', '990' );
	$prior         = array( 'all' => array(), 'vic' => array() );

	if ( 'Yes' === ( $sd['other_qualifications'] ?? '' ) ) {
		foreach ( (array) ( $sd['qualification_level'] ?? array() ) as $code ) {
			$code = trim( (string) $code );
			if ( in_array( $code, $allowed_codes, true ) ) {
				$prior['all'][ $code ] = 1;
				$prior['vic'][ $code ] = 'A';
			}
		}
	}

	if ( $prior['all'] ) {
		$args['prior_achievement_flag'] = 'Y';
		$args['prior_achievement']      = $prior;
	} else {
		$args['prior_achievement_flag'] = 'N';
	}

	return $args;
}

function ams2_step_enrol( $row ) {
	if ( ! ams2_claim( $row->id, 'received', 'enrolling' ) ) {
		return false;
	}

	$sd   = json_decode( (string) $row->student_data, true ) ?: array();
	$args = ams2_enrol_args( $sd, $row );

	foreach ( array( 'first_name', 'last_name', 'date_of_birth', 'course_id', 'plan_id' ) as $k ) {
		if ( empty( $args[ $k ] ) ) {
			ams2_fail( $row->id, 'received', 'Missing required field: ' . $k, false );
			return false;
		}
	}

	$res  = ams2_api( array( 'values' => http_build_query( $args ) ), 'enrol/' );
	$body = $res['body'];

	if ( ! empty( $body['success'] ) && ! empty( $body['enrolment_id'] ) ) {
		ams2_set( $row->id, array(
			'status'        => 'enrolled',
			'attempts'      => 0,
			'error_message' => null,
			'enrolment_id'  => (string) $body['enrolment_id'],
			'learner_id'    => (string) ( $body['enrolment_data']['learner_id'] ?? '' ),
			'course_title'  => sanitize_text_field( $body['enrolment_data']['course_title'] ?? '' ),
		) );
		ams2_log( 'info', 'enrolled', $row->id, '', array(
			'enrolment_id' => $body['enrolment_id'],
			'learner_id'   => $body['enrolment_data']['learner_id'] ?? '',
		) );
		return true;
	}

	// No clear answer: the enrolment may exist. Check RTO Data before trying again.
	if ( ! $res['sent'] || $res['http'] >= 500 || ! is_array( $body ) || ! empty( $body['success'] ) ) {
		ams2_start_verify( $row->id, 'No clear answer from RTO Data (' . ( $res['error'] ?: 'HTTP ' . $res['http'] ) . ').' );
		return false;
	}

	ams2_fail( $row->id, 'received', 'RTO Data refused the enrolment: ' . $res['raw'] );
	return false;
}

/* ---------- Step 2: documents ---------- */

function ams2_send_documents( $row ) {
	global $wpdb;

	$docs   = ams2_docs_table();
	$types  = ams2_doc_types();
	$failed = array();

	$items = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$docs} WHERE enrolment_row = %d AND status IN ('pending','retry') ORDER BY id ASC",
		$row->id
	) );

	foreach ( $items as $doc ) {
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$docs} SET status = 'sending', updated_at = %s WHERE id = %d AND status = %s",
			current_time( 'mysql' ),
			$doc->id,
			$doc->status
		) );
		if ( 1 !== (int) $claimed ) {
			continue;
		}

		if ( ! file_exists( $doc->file_path ) ) {
			$wpdb->update( $docs, array( 'status' => 'failed', 'error_message' => 'File missing on server', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $doc->id ) );
			$failed[ $doc->doc_type ] = 'The file is missing from the website server.';
			ams2_log( 'error', 'document_file_missing', $row->id, basename( $doc->file_path ), array( 'doc_type' => $doc->doc_type ) );
			continue;
		}

				$name   = (string) $doc->original_name;
		if ( '' === $name ) {
			$name = basename( $doc->file_path );
		}
		$result = rtodata_upload_document( $row->enrolment_id, $doc->file_path, $name );
		$ok     = ! empty( $result['raw_response'] ) && false !== strpos( $result['raw_response'], '"success":1' );

		if ( $ok ) {
			unlink( $doc->file_path );
			$wpdb->update( $docs, array(
				'status'        => 'sent',
				'error_message' => null,
				'sent_at'       => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			), array( 'id' => $doc->id ) );
			ams2_log( 'info', 'document_sent', $row->id, basename( $doc->file_path ), array( 'doc_type' => $doc->doc_type ) );
			continue;
		}

		$attempts = (int) $doc->attempts + 1;
		$final    = $attempts >= AMS2_MAX_ATTEMPTS;

		$wpdb->update( $docs, array(
			'status'        => $final ? 'failed' : 'retry',
			'attempts'      => $attempts,
			'error_message' => wp_json_encode( $result ),
			'updated_at'    => current_time( 'mysql' ),
		), array( 'id' => $doc->id ) );

		ams2_log( $final ? 'error' : 'warning', 'document_send_failed', $row->id, 'Attempt ' . $attempts . ' of ' . AMS2_MAX_ATTEMPTS, array(
			'doc_type'     => $doc->doc_type,
			'http_status'  => $result['http_status'] ?? null,
			'curl_error'   => $result['curl_error'] ?? null,
			'raw_response' => substr( (string) ( $result['raw_response'] ?? '' ), 0, 1000 ),
		) );

		if ( $final ) {
			$failed[ $doc->doc_type ] = 'RTO Data rejected it ' . $attempts . ' times. The file is still on the website server: ' . basename( $doc->file_path );
		}
	}

	$pending = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$docs} WHERE enrolment_row = %d AND status IN ('pending','retry','sending')",
		$row->id
	) );

	return array( 'pending' => $pending, 'failed' => $failed );
}

function ams2_step_documents( $row ) {
	global $wpdb;

	// Wait for the browser to finish uploading, or 15 minutes, whichever comes first.
	$ready = (int) $row->docs_final || strtotime( $row->created_at ) < current_time( 'timestamp' ) - 15 * MINUTE_IN_SECONDS;
	if ( ! $ready ) {
		return false;
	}

	if ( ! ams2_claim( $row->id, 'enrolled', 'sending_documents' ) ) {
		return false;
	}

	$res = ams2_send_documents( $row );

	if ( $res['pending'] > 0 ) {
		ams2_set( $row->id, array( 'status' => 'enrolled' ) );
		return false;
	}

	$types    = ams2_doc_types();
	$expected = array_filter( explode( ',', (string) $row->docs_expected ) );
	$sent     = $wpdb->get_col( $wpdb->prepare(
		'SELECT DISTINCT doc_type FROM ' . ams2_docs_table() . " WHERE enrolment_row = %d AND status = 'sent'",
		$row->id
	) );

	$problems = array();
	foreach ( $expected as $type ) {
		if ( in_array( $type, $sent, true ) ) {
			continue;
		}
		$reason     = $res['failed'][ $type ] ?? 'The upload never reached the website (connection dropped or the page was closed).';
		$problems[] = '- ' . ( $types[ $type ]['label'] ?? $type ) . ': ' . $reason;
	}
	foreach ( $res['failed'] as $type => $reason ) {
		if ( ! in_array( $type, $expected, true ) ) {
			$problems[] = '- ' . ( $types[ $type ]['label'] ?? $type ) . ': ' . $reason;
		}
	}

	if ( $problems ) {
		ams2_log( 'warning', 'documents_missing', $row->id, count( $problems ) . ' document(s) not in RTO Data' );
		ams2_alert( ams2_row( $row->id ), 'The enrolment is in RTO Data, but these documents are not attached to it:', array_merge(
			$problems,
			array( '', 'The student was not shown an error. The enrolment will continue. Please contact them for the missing documents.' )
		) );
	}

	ams2_set( $row->id, array( 'status' => 'documents_done', 'attempts' => 0, 'error_message' => null ) );
	return true;
}

/* ---------- Step 3: real email into RTO Data ---------- */

function ams2_step_update( $row ) {
	if ( ! ams2_claim( $row->id, 'documents_done', 'updating' ) ) {
		return false;
	}

	if ( ! is_email( $row->real_email ) ) {
		ams2_fail( $row->id, 'documents_done', 'The stored email address is not valid: ' . $row->real_email, false );
		return false;
	}

	$res = ams2_api( array(
		'type'         => 'enrolment',
		'enrolment_id' => (int) $row->enrolment_id,
		'values'       => http_build_query( array(
			'email'         => $row->real_email,
			'other_email'   => $row->real_email,
		) ),
	), 'update' );

	if ( ! empty( $res['body']['success'] ) ) {
		ams2_set( $row->id, array( 'status' => 'updated', 'attempts' => 0, 'error_message' => null ) );
		ams2_log( 'info', 'email_updated', $row->id );
		return true;
	}

		ams2_fail( $row->id, 'documents_done', 'Email update failed (HTTP ' . $res['http'] . '): ' . ( $res['error'] ?: $res['raw'] ) );
	return false;
}

/* ---------- Step 4: confirmation email ---------- */

function ams2_step_email( $row ) {
	if ( ! ams2_claim( $row->id, 'updated', 'emailing' ) ) {
		return false;
	}

	$posts = get_posts( array(
		'post_type'      => 'ams_enrol_email',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array( 'key' => 'course_id', 'value' => (int) $row->course_id ),
			array( 'key' => 'plan_id', 'value' => (int) $row->plan_id ),
		),
	) );

	if ( ! $posts ) {
		ams2_fail( $row->id, 'updated', 'No published Enrolment Email for course ' . $row->course_id . ' / plan ' . $row->plan_id . '.', false );
		return false;
	}

	$tpl   = $posts[0];
	$sd    = json_decode( (string) $row->student_data, true ) ?: array();
	$title = trim( (string) get_post_meta( $tpl->ID, 'course_title', true ) ) ?: (string) $row->course_title;

		$body = str_replace(
		array( '{first_name}', '{last_name}', '{course_title}' ),
		array(
			esc_html( mb_strtoupper( trim( $sd['first_name'] ?? '' ), 'UTF-8' ) ),
			esc_html( mb_strtoupper( trim( $sd['last_name'] ?? '' ), 'UTF-8' ) ),
			esc_html( $title ),
		),
		(string) get_post_meta( $tpl->ID, 'email_body', true )
	);

	$attachments = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$file_id = (int) get_post_meta( $tpl->ID, 'attachment_' . $i, true );
		$path    = $file_id ? get_attached_file( $file_id ) : '';
		if ( $path && file_exists( $path ) ) {
			$attachments[] = $path;
		}
	}

	$mail_error = '';
	$catch      = function ( $wp_error ) use ( &$mail_error ) {
		$mail_error = $wp_error->get_error_message();
	};
	add_action( 'wp_mail_failed', $catch );

	$sent = wp_mail(
		$row->real_email,
		trim( 'Enrolment confirmation ' . $title ),
		$body,
		array( 'Content-Type: text/html; charset=UTF-8', 'From: AMS Training <info@amstraining.com.au>' ),
		$attachments
	);

	remove_action( 'wp_mail_failed', $catch );

	if ( $sent ) {
		ams2_set( $row->id, array(
			'status'        => 'complete',
			'attempts'      => 0,
			'error_message' => null,
			'email_sent_at' => current_time( 'mysql' ),
		) );
		ams2_log( 'info', 'complete', $row->id, 'Confirmation sent with ' . count( $attachments ) . ' attachment(s)' );
		return true;
	}

	ams2_fail( $row->id, 'updated', 'Confirmation email failed: ' . ( $mail_error ?: 'wp_mail returned false' ) );
	return false;
}

/* ---------- Runner ---------- */

add_action( 'ams2_process', 'ams2_process_row', 10, 1 );

function ams2_process_row( $id ) {
	for ( $i = 0; $i < 5; $i++ ) {
		$row = ams2_row( $id );
		if ( ! $row ) {
			return;
		}

		switch ( $row->status ) {
			case 'received':
				$moved = ams2_step_enrol( $row );
				break;
			case 'enrolled':
				$moved = ams2_step_documents( $row );
				break;
			case 'documents_done':
				$moved = ams2_step_update( $row );
				break;
			case 'updated':
				$moved = ams2_step_email( $row );
				break;
			default:
				$moved = false;
		}

		if ( ! $moved ) {
			return;
		}
	}
}

add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['ams2_one_min'] ) ) {
		$schedules['ams2_one_min'] = array( 'interval' => 60, 'display' => 'AMS2 Every Minute' );
	}
	return $schedules;
} );

add_action( 'init', function () {
	$event = wp_get_scheduled_event( 'ams2_cron' );

	// Swap out the old five-minute schedule if it's still registered.
	if ( $event && 'ams2_one_min' !== $event->schedule ) {
		wp_clear_scheduled_hook( 'ams2_cron' );
		$event = false;
	}

	if ( ! $event ) {
		wp_schedule_event( time() + 60, 'ams2_one_min', 'ams2_cron' );
	}
} );

add_action( 'ams2_cron', 'ams2_cron' );

function ams2_cron() {
	global $wpdb;

	$t    = ams2_table();
	$docs = ams2_docs_table();
	$cut  = wp_date( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );

	// Steps that started but never finished. Park them, never resend blindly.
	$stuck = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, status FROM {$t} WHERE status IN ('enrolling','sending_documents','updating','emailing') AND updated_at < %s",
		$cut
	) );
	$back = array(
		'enrolling'         => 'received',
		'sending_documents' => 'enrolled',
		'updating'          => 'documents_done',
		'emailing'          => 'updated',
	);
	foreach ( $stuck as $s ) {
		ams2_fail( (int) $s->id, $back[ $s->status ], 'The step "' . $s->status . '" started but did not finish within 30 minutes.', false );
	}

	// A document send that never finished. Resending a document is safe.
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$docs} SET status = 'retry', updated_at = %s WHERE status = 'sending' AND updated_at < %s",
		current_time( 'mysql' ),
		$cut
	) );

	$ids = $wpdb->get_col( "SELECT id FROM {$t} WHERE status IN ('received','enrolled','documents_done','updated') ORDER BY id ASC LIMIT 10" );
	foreach ( $ids as $id ) {
		ams2_process_row( (int) $id );
	}

	// Documents that arrived after their enrolment had moved past the documents step.
	$late = $wpdb->get_col(
		"SELECT DISTINCT d.enrolment_row FROM {$docs} d
		 INNER JOIN {$t} e ON e.id = d.enrolment_row
		 WHERE d.status IN ('pending','retry')
		   AND e.enrolment_id IS NOT NULL AND e.enrolment_id <> ''
		   AND e.status IN ('documents_done','updated','complete')
		 LIMIT 10"
	);
	foreach ( $late as $id ) {
		$row = ams2_row( $id );
		$res = ams2_send_documents( $row );
		if ( $res['failed'] ) {
			$types = ams2_doc_types();
			$lines = array();
			foreach ( $res['failed'] as $type => $reason ) {
				$lines[] = '- ' . ( $types[ $type ]['label'] ?? $type ) . ': ' . $reason;
			}
			ams2_alert( $row, 'A late document could not be attached to this enrolment in RTO Data:', $lines );
		}
	}

	update_option( 'ams2_cron_last_run', current_time( 'mysql' ), false );
}

/* ---------- Enrolment page: [ams2_enrolment_form] ---------- */

function ams2_page_message( $message, $redirect = '' ) {
	$html = '<div class="ams-box ams-box--bad">' . esc_html( $message );
	if ( $redirect ) {
		$html .= ' <a href="' . esc_url( $redirect ) . '">Continue</a></div><script>window.location.replace(' . wp_json_encode( $redirect ) . ');</script>';
	} else {
		$html .= '</div>';
	}
	return $html;
}

add_shortcode( 'ams2_enrolment_form', 'ams2_enrolment_form' );

function ams2_enrolment_form() {
	$key = sanitize_text_field( wp_unslash( $_GET['secretKey'] ?? '' ) );

	// Already submitted through v2: say so, whatever the admin-only or course settings are.
	$existing = $key ? ams2_row_by_key( $key ) : null;
	if ( $existing ) {
		return '<div class="ams-box ams-box--ok" style="padding:12px 14px;border:1px solid #b7dfb9;background:#edf7ed;color:#1e4620;border-radius:6px">'
			. '<strong>Your enrolment has already been submitted.</strong><br>Reference AMS2-' . (int) $existing->id
			. '. If you still need to send a document, email it to <a href="mailto:sales@amstraining.com.au">sales@amstraining.com.au</a>.</div>';
	}

	if ( AMS2_ADMIN_ONLY && ! current_user_can( 'manage_options' ) ) {
		return '<p>This page is not available.</p>';
	}
	if ( ! function_exists( 'enrolment_form_shortcode70' ) ) {
		return '<p>The enrolment form is missing. Contact AMS Training.</p>';
	}

	$info = ams2_key_info( $key );

	if ( 'expired' === $info['state'] ) {
		return ams2_page_message( 'This enrolment link has expired.', home_url( '/link-expired/' ) );
	}
	if ( 'ok' !== $info['state'] ) {
		return ams2_page_message( 'This enrolment link is not valid.', home_url( '/enrolment-not-successful/' ) );
	}
	if ( ams2_in_old_flow( $key ) ) {
		return ams2_page_message( 'Your enrolment has already been submitted. Contact AMS Training if you need to change anything.' );
	}
	if ( ! ams2_is_test_course( $info['course_id'], $info['plan_id'] ) ) {
		return ams2_page_message( 'This enrolment link is not set up for this form. Contact AMS Training.' );
	}

	$config = array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'secretKey'  => $key,
		'nonce'      => wp_create_nonce( 'ams2_enrol' ),
		'maxBytes'   => defined( 'AMS_DOC_MAX_BYTES' ) ? AMS_DOC_MAX_BYTES : 10 * 1024 * 1024,
		'successUrl' => home_url( '/enrolment-successful/' ),
		'courseId'   => $info['course_id'],
		'planId'     => $info['plan_id'],
		'clientId'   => $info['client_id'],
		'prefill'    => array(
			'firstName' => $info['first_name'],
			'lastName'  => $info['last_name'],
			'email'     => $info['email'],
		),
	);

	// Stop a no-JavaScript submit from reaching the old admin-post/template_redirect handler.
	$form = str_replace(
		array( 'name="ams_action" value="process_enrolment"', 'Student Registration Check.' ),
		array( 'name="ams2_noop" value="1"', 'Student Registration' ),
		enrolment_form_shortcode70()
	);

	ob_start();
	?>
	<style>
		.ams2-enrol .ams-status{margin:1rem 0}
		.ams2-enrol .ams-box{padding:12px 14px;border:1px solid;border-radius:6px;margin-bottom:10px;font-size:14px;line-height:1.5}
		.ams2-enrol .ams-box--ok{background:#edf7ed;border-color:#b7dfb9;color:#1e4620}
		.ams2-enrol .ams-box--info{background:#f4f6f8;border-color:#dde2e7;color:#223a74}
		.ams2-enrol .ams-box--bad{background:#fdecea;border-color:#f5c2c0;color:#8a1c14}
		.ams2-enrol .upload-error{display:block;color:#d63638;font-size:.9em;margin-top:.4em}
		.ams2-enrol .upload-error-field{border:1px solid #d63638!important}
	</style>

	<div class="ams2-enrol" id="ams2-enrol">
		<div id="ams2-status" class="ams-status" aria-live="polite"></div>
		<noscript><div class="ams-box ams-box--bad">This form needs JavaScript. Turn it on in your browser, or contact AMS Training.</div></noscript>
		<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<script>window.AMS2 = <?php echo wp_json_encode( $config ); ?>;</script>
	<script>
	(function () {
		var C = window.AMS2;
		var root = document.getElementById('ams2-enrol');
		if (!C || !root) return;

		var form = root.querySelector('form.ef-form');
		var statusEl = document.getElementById('ams2-status');
		if (!form) return;

		var ALLOWED = ['pdf', 'jpg', 'jpeg', 'jfif', 'jpe', 'png', 'webp'];
		var INPUTS = { id_document: 'id_document', vet_transcript: 'vet_transcript' };
		var LIMIT = Math.round(C.maxBytes / 1048576) + 'MB';
		var TYPE_MSG = 'This file type can\'t be uploaded. Please choose a PDF, JPG, PNG or WEBP file.';
		var READ_MSG = 'We couldn\'t read this file. Save it to your Desktop or Downloads folder first, then choose it again.';
		var copies = {};
		var nonce = C.nonce;
		var working = false;

		window.addEventListener('beforeunload', function (e) {
			if (!working) return;
			e.preventDefault();
			e.returnValue = '';
		});

		function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
		function clean(s) { return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
		function mb(b) { return (b / 1048576).toFixed(1) + 'MB'; }
		function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
		function box(kind, html) { statusEl.innerHTML = '<div class="ams-box ams-box--' + kind + '">' + html + '</div>'; }

		function log(event, message, docType, detail) {
			try {
				var fd = new FormData();
				fd.append('action', 'ams_log_client');
				fd.append('event', 'ams2_' + event);
				fd.append('message', message || '');
				if (C.requestId) fd.append('request_id', C.requestId);
				if (docType) fd.append('doc_type', docType);
				fd.append('detail', (detail || '') + ' | online=' + navigator.onLine + ' | visible=' + document.visibilityState);
				if (!navigator.sendBeacon || !navigator.sendBeacon(C.ajaxUrl, fd)) {
					var x = new XMLHttpRequest(); x.open('POST', C.ajaxUrl); x.send(fd);
				}
			} catch (e) {}
		}

		function fieldError(input, msg) {
			var row = input.closest('.ef-upload-row') || input.parentNode;
			var el = row.querySelector('.upload-error');
			if (!msg) {
				if (el) el.remove();
				input.classList.remove('upload-error-field');
				return;
			}
			if (!el) {
				el = document.createElement('span');
				el.className = 'upload-error';
				el.setAttribute('role', 'alert');
				row.appendChild(el);
			}
			el.textContent = msg;
			input.classList.add('upload-error-field');
		}

		function readBuffer(f) {
			if (f.arrayBuffer) return f.arrayBuffer();
			return new Promise(function (resolve, reject) {
				var r = new FileReader();
				r.onload = function () { resolve(r.result); };
				r.onerror = function () { reject(r.error); };
				r.readAsArrayBuffer(f);
			});
		}

		// Checks the file's actual contents, so a renamed .docx or .zip can't pass as a PDF or image.
		function kindOf(buf) {
			var head = new Uint8Array(buf, 0, Math.min(1024, buf.byteLength));
			for (var i = 0; i + 3 < head.length; i++) {
				if (head[i] === 0x25 && head[i + 1] === 0x50 && head[i + 2] === 0x44 && head[i + 3] === 0x46) return 'pdf';
			}
			var b = head;
			if (b.length >= 3 && b[0] === 0xFF && b[1] === 0xD8 && b[2] === 0xFF) return 'jpg';
			if (b.length >= 4 && b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4E && b[3] === 0x47) return 'png';
			if (b.length >= 12 && b[0] === 0x52 && b[1] === 0x49 && b[2] === 0x46 && b[3] === 0x46 &&
				b[8] === 0x57 && b[9] === 0x45 && b[10] === 0x42 && b[11] === 0x50) return 'webp';
			return null;
		}

		function typeOf(input) {
			for (var t in INPUTS) { if (input.id === INPUTS[t]) return t; }
			return null;
		}

		/* ---------- Prefill ---------- */
		var p = C.prefill || {};
		[['first_name', clean(p.firstName)], ['last_name', clean(p.lastName)], ['email', p.email]].forEach(function (pair) {
			var el = form.querySelector('[name="' + pair[0] + '"]');
			if (el && pair[1]) { el.value = pair[1]; el.readOnly = true; }
		});
		[['course_id', C.courseId], ['plan_id', C.planId], ['client_id', C.clientId]].forEach(function (pair) {
			var el = form.querySelector('[name="' + pair[0] + '"]');
			if (el && pair[1]) el.value = pair[1];
		});

		root.querySelectorAll('.ef-upload-row span, .ef-upload-row label').forEach(function (el) {
			el.textContent = el.textContent.replace(/8\s?MB/gi, LIMIT);
		});

		/* ---------- Copy each file into memory as soon as it's chosen ---------- */
		form.querySelectorAll('input[type="file"]').forEach(function (input) {
			input.setAttribute('accept', '.pdf,.jpg,.jpeg,.jfif,.jpe,.png,.webp');
		});

		form.addEventListener('change', function (e) {
			var input = e.target;
			if (!input || !input.matches || !input.matches('input[type="file"]')) return;
			e.stopPropagation(); // Keeps the form's older inline file handlers out of the way.

			var type = typeOf(input);
			if (!type) return;

			delete copies[type];
			fieldError(input, null);

			var f = input.files && input.files[0];
			if (!f) return;

			var ext = f.name.indexOf('.') === -1 ? '' : f.name.split('.').pop().toLowerCase();
			var problem = null;
			if (ALLOWED.indexOf(ext) === -1) problem = TYPE_MSG;
			else if (f.size === 0) problem = 'This file is empty. Please choose the file again or save a new copy first.';
			else if (f.size > C.maxBytes) problem = 'This file is ' + mb(f.size) + '. The maximum size is ' + LIMIT + '. Please choose a smaller file.';

			if (problem) {
				input.value = '';
				fieldError(input, problem);
				log('file_rejected', problem, type, 'size=' + f.size + ' name=' + f.name);
				return;
			}

			copies[type] = readBuffer(f).then(function (buf) {
				if (!kindOf(buf)) {
					if (input.files[0] === f) { input.value = ''; fieldError(input, TYPE_MSG); }
					log('file_rejected', 'Contents are not a PDF or image', type, 'size=' + f.size + ' type=' + (f.type || 'unknown') + ' name=' + f.name);
					return null;
				}
				return new File([buf], f.name, { type: f.type || 'application/octet-stream', lastModified: f.lastModified });
			}).catch(function (err) {
				if (input.files[0] === f) { input.value = ''; fieldError(input, READ_MSG); }
				log('file_unreadable', String((err && err.name) || err), type, 'size=' + f.size + ' name=' + f.name);
				return null;
			});
		}, true);

		/* ---------- Requests ---------- */
		function post(fd, timeoutMs) {
			return new Promise(function (resolve) {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', C.ajaxUrl);
				xhr.timeout = timeoutMs;
				xhr.onload = function () {
					var json = null;
					try { json = JSON.parse(xhr.responseText); } catch (err) {}
					if (json && json.success) { resolve({ ok: true, data: json.data, status: xhr.status }); return; }
					var d = (json && json.data) || {};
					resolve({
						ok: false, network: false, code: d.code || '', status: xhr.status,
						message: d.message || 'The server returned an unexpected response (HTTP ' + xhr.status + ').'
					});
				};
				xhr.onerror = function () { resolve({ ok: false, network: true, status: 0, message: 'We could not reach the server. Check your internet connection.' }); };
				xhr.ontimeout = function () { resolve({ ok: false, network: true, status: 0, message: 'The server took too long to respond.' }); };
				xhr.send(fd);
			});
		}

		function refreshNonce() {
			var fd = new FormData();
			fd.append('action', 'ams2_nonce');
			return post(fd, 20000).then(function (r) {
				if (r.ok && r.data && r.data.nonce) { nonce = r.data.nonce; return true; }
				return false;
			});
		}

		function send(build, timeoutMs) {
			function attempt() {
				var fd = build();
				fd.set('ams2_nonce', nonce);
				return post(fd, timeoutMs);
			}
			return attempt().then(function (r) {
				if (!r.ok && r.code === 'nonce') {
					return refreshNonce().then(function (fresh) { return fresh ? attempt() : r; });
				}
				return r;
			});
		}

				var LABELS = { id_document: 'ID document', vet_transcript: 'VET transcript' };

		function retryable(r) {
			return r.network || [429, 500, 502, 503, 504].indexOf(r.status) !== -1;
		}

		function uploadOne(doc, attempt) {
			return send(function () {
				var fd = new FormData();
				fd.append('action', 'ams2_upload');
				fd.append('secretKey', C.secretKey);
				fd.append('id', C.requestId);
				fd.append('doc_type', doc.type);
				fd.append('document', doc.file, doc.file.name);
				return fd;
			}, 180000).then(function (r) {
				if (r.ok) return { type: doc.type, ok: true };
				if (retryable(r) && attempt < 4) {
					return wait(3000 * attempt).then(function () { return uploadOne(doc, attempt + 1); });
				}
				log('upload_failed', r.message, doc.type, 'http=' + r.status + ' size=' + doc.file.size + ' attempt=' + attempt);
				return { type: doc.type, ok: false };
			});
		}

		function uploadAll(docs) {
			var results = [];
			var chain = Promise.resolve();
			docs.forEach(function (d) {
				chain = chain.then(function () { return uploadOne(d, 1); }).then(function (res) { results.push(res); });
			});
			return chain.catch(function (err) {
				log('script_error', String((err && err.message) || err), '', 'during uploads');
			}).then(function () {
				return docs.filter(function (d) {
					return !results.some(function (res) { return res && res.ok && res.type === d.type; });
				});
			});
		}

		function finalise() {
			var fd = new FormData();
			fd.append('action', 'ams2_finalise');
			fd.append('secretKey', C.secretKey);
			fd.append('id', C.requestId);
			if (navigator.sendBeacon && navigator.sendBeacon(C.ajaxUrl, fd)) return Promise.resolve();
			return post(fd, 20000);
		}

		function finish() {
			working = false;
			window.location.href = C.successUrl;
		}

		function done() {
			finalise();
			box('ok', '<strong>&#10003; Enrolment successfully submitted.</strong> Taking you to the confirmation page…');
			finish();
		}

		function handleUploads(docs) {
			box('info', 'Uploading your documents… Please keep this page open.');

			var timedOut = false;
			var watchdog = setTimeout(function () {
				timedOut = true;
				log('watchdog_redirect', 'Uploads did not finish within 10 minutes', '', '');
				done();
			}, 10 * 60 * 1000);

			return uploadAll(docs).then(function (failed) {
				clearTimeout(watchdog);
				if (timedOut) return;
				if (!failed.length) { done(); return; }

				working = false;
				var names = failed.map(function (d) { return LABELS[d.type] || d.type; }).join(' and ');
				statusEl.innerHTML = '<div class="ams-box ams-box--bad"><strong>Your enrolment has been saved, but your ' + esc(names) + ' did not upload.</strong><br>'
					+ 'This is usually a brief connection problem. Press Try again. If it still does not work, press Continue and email the document to '
					+ '<a href="mailto:sales@amstraining.com.au">sales@amstraining.com.au</a>.</div>'
					+ '<button type="button" id="ams2-retry">Try again</button> '
					+ '<button type="button" id="ams2-continue">Continue</button>';

				document.getElementById('ams2-retry').onclick = function () {
					working = true;
					handleUploads(failed);
				};
				document.getElementById('ams2-continue').onclick = function () {
					log('continued_without_documents', names, '', '');
					done();
				};
			});
		}

		/* ---------- Submit ---------- */
		var submitWrap = form.querySelector('.ef-submit');
		var btn = submitWrap.querySelector('button');
		submitWrap.parentNode.insertBefore(statusEl, submitWrap);
		var busy = false;

		function resetButton() {
			busy = false;
			working = false;
			btn.disabled = false;
			btn.textContent = 'Register';
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (busy) return;

			var bad = form.querySelector('.upload-error-field');
			if (bad) {
				box('bad', 'Please choose a different file where shown before submitting.');
				bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
				return;
			}

			busy = true;
			working = true;
			btn.disabled = true;
			btn.textContent = 'Submitting enrolment…';
			box('info', 'Submitting your enrolment… This can take a minute. Please keep this page open.');

			var types = Object.keys(copies);

			Promise.all(types.map(function (t) { return copies[t]; })).then(function (files) {
				var docs = [];
				files.forEach(function (file, i) { if (file) docs.push({ type: types[i], file: file }); });

				var badNow = form.querySelector('.upload-error-field');
				if (badNow) {
					resetButton();
					box('bad', 'Please choose a different file where shown before submitting.');
					badNow.scrollIntoView({ behavior: 'smooth', block: 'center' });
					return;
				}

				return send(function () {
					var fd = new FormData(form);
					fd.delete('documents[]');
					fd.set('action', 'ams2_submit');
					fd.set('secretKey', C.secretKey);
					fd.set('doc_expected', docs.map(function (d) { return d.type; }).join(','));
					return fd;
				}, 60000).then(function (r) {
					if (!r.ok) {
						resetButton();
						if (r.network || !r.code) log('submit_failed', r.message, '', 'http=' + r.status);
						if (r.network) {
							box('bad', '<strong>We could not confirm your enrolment.</strong><br>' + esc(r.message) +
								' Press Register to try again. You will not be enrolled twice.');
						} else {
							box('bad', '<strong>Your enrolment has not been submitted.</strong><br>' + esc(r.message));
						}
						statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
						return;
					}

					C.requestId = r.data.id;
					form.querySelectorAll('.ef-section, .ef-submit').forEach(function (el) { el.style.display = 'none'; });
					statusEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

					if (!docs.length) { done(); return; }

					return handleUploads(docs);
				});
			});
		});
	})();
	</script>		
	<?php
	return ob_get_clean();
}

/* ---------- Status page: [ams2_status] (admin only) ---------- */

add_shortcode( 'ams2_status', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	global $wpdb;
	$out = '';

	if ( isset( $_POST['ams2_resume'] ) && check_admin_referer( 'ams2_resume' ) ) {
		$id  = absint( $_POST['ams2_resume'] );
		$row = ams2_row( $id );

		if ( $row && 'needs_review' === $row->status && $row->resume_status ) {
			ams2_set( $id, array( 'status' => $row->resume_status, 'attempts' => 0, 'error_message' => null ) );
			ams2_log( 'info', 'resumed', $id, 'Resumed at ' . $row->resume_status . ' by ' . wp_get_current_user()->user_login );
			ams2_schedule( $id );
			$out .= '<p><strong>AMS2-' . (int) $id . ' resumed at "' . esc_html( $row->resume_status ) . '". It runs on the next cron.</strong></p>';
		}
	}
   	if ( isset( $_POST['ams2_resend'] ) && check_admin_referer( 'ams2_resend' ) ) {
		$id  = absint( $_POST['ams2_resend'] );
		$row = ams2_row( $id );
		$to  = sanitize_email( wp_unslash( $_POST['ams2_resend_to'] ?? '' ) );

		if ( ! ams2_can_resend( $row ) ) {
			$out .= '<p style="color:#b32d2e"><strong>AMS2-' . $id . ' is not at a stage where the email can be resent.</strong></p>';
		} elseif ( ! is_email( $to ) ) {
			$out .= '<p style="color:#b32d2e"><strong>Enter a valid email address for AMS2-' . $id . '.</strong></p>';
		} else {
			$res = ams2_resend_confirmation( $row, $to );
			if ( true === $res ) {
				$update = array( 'email_sent_at' => current_time( 'mysql' ) );
				if ( 'needs_review' === $row->status ) {
					$update += array( 'status' => 'complete', 'resume_status' => '', 'attempts' => 0, 'error_message' => null );
				}
				ams2_set( $id, $update );
				ams2_log( 'info', 'confirmation_resent', $id, 'Resent by ' . wp_get_current_user()->user_login );
				$out .= '<p><strong>Confirmation email sent for AMS2-' . $id . '.</strong></p>';
			} else {
				ams2_log( 'error', 'confirmation_resend_failed', $id, $res );
				$out .= '<p style="color:#b32d2e"><strong>AMS2-' . $id . ' failed: ' . esc_html( $res ) . '</strong></p>';
			}
		}
	}
	$rows = $wpdb->get_results( 'SELECT * FROM ' . ams2_table() . ' ORDER BY id DESC LIMIT 50' );

	$out .= '<p style="font-size:13px;color:#555">Cron last ran: ' . esc_html( get_option( 'ams2_cron_last_run' ) ?: 'never' )
		. ' | Admin only: ' . ( AMS2_ADMIN_ONLY ? 'yes' : 'no' ) . '</p>';

	if ( ! $rows ) {
		return $out . '<p>No enrolments yet.</p>';
	}

	$out .= '<div style="max-width:100%;overflow-x:auto"><table style="border-collapse:collapse;font-size:13px;min-width:900px">'
		. '<thead><tr>';
	foreach ( array( 'Ref', 'Submitted', 'Student', 'Status', 'RTO Data', 'Documents', 'Error', '' ) as $h ) {
		$out .= '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc">' . esc_html( $h ) . '</th>';
	}
	$out .= '</tr></thead><tbody>';

	foreach ( $rows as $r ) {
		$sd   = json_decode( (string) $r->student_data, true ) ?: array();
		$docs = $wpdb->get_results( $wpdb->prepare(
			'SELECT doc_type, status FROM ' . ams2_docs_table() . " WHERE enrolment_row = %d AND status <> 'replaced'",
			$r->id
		) );
		$doc_text = array();
		foreach ( $docs as $d ) {
			$doc_text[] = $d->doc_type . ': ' . $d->status;
		}

		$action = '';
		if ( 'needs_review' === $r->status && $r->resume_status ) {
			$action = '<form method="post" onsubmit="return confirm(\'Resume AMS2-' . (int) $r->id . '? If it stopped while creating the enrolment, check RTO Data first, or the student will be enrolled twice.\');">'
				. wp_nonce_field( 'ams2_resume', '_wpnonce', true, false )
				. '<button type="submit" name="ams2_resume" value="' . (int) $r->id . '">Resume</button></form>';
		}
       		if ( ams2_can_resend( $r ) ) {
			$default_to = $r->real_email;
			if ( '' === (string) $default_to ) {
				$person     = ams2_key_person( $r->secret_key );
				$default_to = (string) ( $person->emailaddress ?? '' );
			}
			$action .= '<form method="post" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap" onsubmit="return confirm(\'Send the confirmation email for AMS2-' . (int) $r->id . ' to \' + this.ams2_resend_to.value + \'?\');">'
				. wp_nonce_field( 'ams2_resend', '_wpnonce', true, false )
				. '<input type="email" name="ams2_resend_to" value="' . esc_attr( $default_to ) . '" required style="width:210px;max-width:100%">'
				. '<button type="submit" name="ams2_resend" value="' . (int) $r->id . '">Resend email</button></form>';
		}
		$out .= '<tr>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee">AMS2-' . (int) $r->id . '</td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->created_at ) ) ) . '</td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . esc_html( trim( ( $sd['first_name'] ?? '' ) . ' ' . ( $sd['last_name'] ?? '' ) ) ) . '<br><span style="color:#777">' . esc_html( $r->real_email ) . '</span></td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee"><strong>' . esc_html( $r->status ) . '</strong>' . ( $r->attempts ? '<br>attempts ' . (int) $r->attempts : '' ) . '</td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . esc_html( $r->enrolment_id ? 'Enrolment ' . $r->enrolment_id . ' / learner ' . $r->learner_id : '-' ) . '</td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . esc_html( $doc_text ? implode( ', ', $doc_text ) : 'none' ) . '</td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee;max-width:320px;word-break:break-word">' . esc_html( (string) $r->error_message ) . '</td>'
			. '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . $action . '</td>'
			. '</tr>';
	}

	return $out . '</tbody></table></div>';
} );
add_shortcode( 'ams2_update_probe', function ( $atts ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$atts = shortcode_atts( array( 'id' => '', 'email' => 'developer@twomoonsconsulting.com.au' ), $atts );
	$out  = '';

	if ( isset( $_POST['ams2_probe'] ) && check_admin_referer( 'ams2_probe' ) ) {
		$as_int = 'int' === $_POST['ams2_probe'];
		$res    = ams2_api( array(
			'type'         => 'enrolment',
			'enrolment_id' => $as_int ? (int) $atts['id'] : (string) $atts['id'],
			'values'       => http_build_query( array(
				'email'         => $atts['email'],
				'other_email'   => $atts['email'],
				'contact_email' => '',
			) ),
		), 'update' );

		$out = '<p><strong>Sent enrolment_id as ' . ( $as_int ? 'number' : 'string' ) . ': HTTP ' . (int) $res['http'] . '</strong></p>'
			. '<pre style="max-width:100%;overflow-x:auto;white-space:pre-wrap">' . esc_html( $res['error'] ?: wp_strip_all_tags( $res['raw'] ) ) . '</pre>';
	}

	return '<form method="post" style="display:flex;gap:8px">'
		. wp_nonce_field( 'ams2_probe', '_wpnonce', true, false )
		. '<button type="submit" name="ams2_probe" value="int">Update (ID as number)</button>'
		. '<button type="submit" name="ams2_probe" value="string">Update (ID as string)</button>'
		. '</form>' . $out;
} );
function rtodata_v2_debug( $args, $endpoint, $header = array() ) {
		if ( ! defined( 'AMS_RTODATA_PUBLIC_KEY' ) || AMS_RTODATA_PUBLIC_KEY === '' ) {
			return array( 'curl_error' => 'AMS_RTODATA_PUBLIC_KEY not defined' );
		}

		$args['public_key'] = AMS_RTODATA_PUBLIC_KEY;
		$args['subdomain']  = 'amstraining';

		$header[] = 'Origin: https://' . parse_url( home_url(), PHP_URL_HOST );
		$header[] = 'Content-Type: application/json';
		$header[] = 'Accept: application/json';

		// Trailing slash: RTO Data 301s /enrol and /update to /enrol/ and /update/.
		$ch = curl_init( 'https://amstraining.rtodata.com.au/api/v2/' . rawurlencode( $endpoint ) . '/' );
		curl_setopt_array( $ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => wp_json_encode( $args ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $header,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_FOLLOWLOCATION => false, // Don't re-send the key to a redirect target.
		) );

		$raw   = curl_exec( $ch );
		$errno = curl_errno( $ch );

		$result = array(
			'http_code'  => curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
			'redirect'   => curl_getinfo( $ch, CURLINFO_REDIRECT_URL ),
			'curl_error' => $errno ? curl_error( $ch ) : '',
			'raw_body'   => $raw,
			'decoded'    => null,
			'json_error' => '',
		);
		curl_close( $ch );

		if ( false !== $raw ) {
			$result['decoded']    = json_decode( $raw, true );
			$result['json_error'] = json_last_error() ? json_last_error_msg() : '';
		}

		return $result;
	}
/**
 * [ams_test_update_values] Admin-only: RTO Data update test using their documented format.
 */
add_shortcode( 'ams_test_update_values', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$out = '';

	if ( isset( $_POST['ams_test_update_values'] ) && wp_verify_nonce( $_POST['_amsnonce'] ?? '', 'ams_test_update_values' ) ) {

		if ( ! function_exists( 'rtodata_v2_debug' ) ) {
			return '<p><strong>rtodata_v2_debug() is missing. Nothing was sent.</strong></p>';
		}

		$enrolment_id = 42473;

		$values = array(
			'first_name'=> 'email@amstraining.com.au'
		);

		$result = rtodata_v2_debug(
			array(
				'type'         => 'enrolment',
				'enrolment_id' => $enrolment_id,
				'values'       => http_build_query( $values ),
			),
			'update'
		);

		$ok   = ! empty( $result['decoded']['success'] );
		$out .= '<p><strong>' . ( $ok ? 'API reported success' : 'FAILED' ) . ' (enrolment ' . $enrolment_id . ')</strong></p>';
		$out .= '<h4>Sent</h4><pre style="white-space:pre-wrap">' . esc_html( print_r( $values, true ) ) . '</pre>';
		$out .= '<h4>RTO Data response</h4><pre style="white-space:pre-wrap">' . esc_html( var_export( $result, true ) ) . '</pre>';
	}

	$out .= '<form method="post">'
		. wp_nonce_field( 'ams_test_update_values', '_amsnonce', true, false )
		. '<button type="submit" name="ams_test_update_values" value="1">Test update (enrolment 42473)</button>'
		. '</form>';

	return $out;
} );
/**
 * [ams_test_enrol_client] Admin-only: test enrolment, optionally against an existing learner via client_id.
 * Requires rtodata_v2_debug() with the trailing slash fix.
 */
add_shortcode( 'ams_test_enrol_client', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 31470;
	$out       = '';

	if ( isset( $_POST['ams_test_enrol_client'] ) && wp_verify_nonce( $_POST['_amsnonce'] ?? '', 'ams_test_enrol_client' ) ) {

		if ( ! function_exists( 'rtodata_v2_debug' ) ) {
			return '<p><strong>rtodata_v2_debug() is missing. Nothing was sent.</strong></p>';
		}

		// last_name, date_of_birth, course_id and plan_id are required by enrol.
		// No email fields: nothing for RTO Data to send to.
		$values = array(
			'first_name'    => 'TEST Processing',
			'last_name'     => 'DEVELOPER Processing',
			'date_of_birth' => '1990-01-15',
			'course_id'     => 18, // RIIHAN309F Online Refresher
			'plan_id'       => 61,
			'email' => 'blank'
		);
		if ( $client_id ) {
			$values['client_id'] = $client_id;
		}

		$result = rtodata_v2_debug(
			array(
				'values' => http_build_query( $values ),
			),
			'enrol'
		);

		$d    = is_array( $result['decoded'] ?? null ) ? $result['decoded'] : array();
		$ok   = ! empty( $d['success'] );
		$eid  = (int) ( $d['enrolment_id'] ?? 0 );
		$lid  = (int) ( $d['enrolment_data']['learner_id'] ?? 0 );

		$out .= '<p><strong>' . ( $ok ? 'Enrolled' : 'FAILED' ) . '</strong>';
		if ( $ok ) {
			$out .= ' Enrolment ID ' . $eid . ', learner ID ' . $lid;
			if ( $client_id ) {
				$out .= $lid === $client_id ? ' (attached to client ' . $client_id . ')' : ' <strong>(NOT client ' . $client_id . ')</strong>';
			}
		}
		$out .= '</p>';
		$out .= '<h4>Sent</h4><pre style="white-space:pre-wrap">' . esc_html( print_r( $values, true ) ) . '</pre>';
		$out .= '<h4>RTO Data response</h4><pre style="white-space:pre-wrap">' . esc_html( var_export( $result, true ) ) . '</pre>';
	}

	$out .= '<form method="post" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">'
		. wp_nonce_field( 'ams_test_enrol_client', '_amsnonce', true, false )
		. '<label>Client ID (blank = not sent) <input type="number" name="client_id" value="' . esc_attr( $client_id ?: '' ) . '" style="width:120px"></label>'
		. '<button type="submit" name="ams_test_enrol_client" value="1">Test enrolment</button>'
		. '</form>';

	return $out;
} );
/**
 * [smtp2go_raw_search] Admin-only: search SMTP2GO activity by any recipient text,
 * including malformed placeholders like processing6hold.invalid.
 */
add_shortcode( 'smtp2go_raw_search', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$tz    = wp_timezone();
	$today = ( new DateTimeImmutable( 'today', $tz ) )->format( 'Y-m-d' );

	$f = array(
		'recipient' => isset( $_POST['recipient'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['recipient'] ) ) ) : 'processing6hold.invalid',
		'subject'   => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
		'start'     => isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : ( new DateTimeImmutable( 'today', $tz ) )->modify( '-7 days' )->format( 'Y-m-d' ),
		'end'       => isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : $today,
	);

	$out = '';

	if ( isset( $_POST['smtp2go_raw_search'] ) && wp_verify_nonce( $_POST['_s2gnonce'] ?? '', 'smtp2go_raw_search' ) ) {
		$out .= ( function () use ( $f, $tz ) {
			$key = defined( 'SMTP2GO_API_KEY' ) ? (string) SMTP2GO_API_KEY : (string) getenv( 'SMTP2GO_API_KEY' );
			if ( '' === $key ) {
				return '<p style="color:#b32d2e"><strong>SMTP2GO_API_KEY is not configured.</strong></p>';
			}
			if ( '' === $f['recipient'] || preg_match( '/\s/', $f['recipient'] ) || strlen( $f['recipient'] ) > 254 ) {
				return '<p style="color:#b32d2e"><strong>Enter recipient search text with no spaces.</strong></p>';
			}

			$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $f['start'], $tz );
			$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', $f['end'], $tz );
			if ( ! $start || ! $end || $start > $end ) {
				return '<p style="color:#b32d2e"><strong>Enter a valid date range (start on or before end).</strong></p>';
			}

			$utc  = new DateTimeZone( 'UTC' );
			$body = array(
				'start_date'       => $start->setTimezone( $utc )->format( 'Y-m-d\TH:i:s\Z' ),
				'end_date'         => $end->setTime( 23, 59, 59 )->setTimezone( $utc )->format( 'Y-m-d\TH:i:s\Z' ),
				'search_recipient' => $f['recipient'],
				'limit'            => 100,
			);
			if ( '' !== $f['subject'] ) {
				$body['search_subject'] = $f['subject'];
			}

			$events = array();
			$token  = null;
			for ( $page = 0; $page < 5; $page++ ) {
				if ( $token ) {
					$body['continue_token'] = $token;
				}

				$res = wp_remote_post( 'https://api.smtp2go.com/v3/activity/search', array(
					'timeout'     => 20,
					'headers'     => array(
						'Content-Type'      => 'application/json',
						'Accept'            => 'application/json',
						'X-Smtp2go-Api-Key' => $key,
					),
					'body'        => wp_json_encode( $body ),
					'data_format' => 'body',
				) );

				if ( is_wp_error( $res ) ) {
					$timeout = false !== stripos( $res->get_error_message(), 'timed out' ) || false !== stripos( $res->get_error_message(), 'cURL error 28' );
					return '<p style="color:#b32d2e"><strong>API request failed: ' . ( $timeout ? 'SMTP2GO did not respond in time.' : 'could not connect to SMTP2GO.' ) . '</strong></p>';
				}

				$code = (int) wp_remote_retrieve_response_code( $res );
				$json = json_decode( wp_remote_retrieve_body( $res ), true );
				$data = ( is_array( $json ) && isset( $json['data'] ) && is_array( $json['data'] ) ) ? $json['data'] : null;
				$err  = ( $data && isset( $data['error_code'] ) && is_scalar( $data['error_code'] ) ) ? (string) $data['error_code'] : '';

				if ( 401 === $code || 403 === $code || preg_match( '/API_KEY|PERMISSION|UNAUTHORI/i', $err ) ) {
					return '<p style="color:#b32d2e"><strong>API request failed: SMTP2GO rejected the API key or it lacks Activity permission.</strong></p>';
				}
				if ( 429 === $code ) {
					return '<p style="color:#b32d2e"><strong>API request failed: SMTP2GO rate limit reached. Wait a minute and try again.</strong></p>';
				}
				if ( $code < 200 || $code >= 300 || null === $data || '' !== $err ) {
					return '<p style="color:#b32d2e"><strong>API request failed: SMTP2GO returned an error (HTTP ' . $code . ( $err ? ', ' . esc_html( $err ) : '' ) . ').</strong></p>';
				}

				$events = array_merge( $events, ( isset( $data['events'] ) && is_array( $data['events'] ) ) ? $data['events'] : array() );
				$token  = ! empty( $data['continue_token'] ) ? (string) $data['continue_token'] : null;
				if ( ! $token ) {
					break;
				}
			}

			if ( ! $events ) {
				return '<p style="padding:10px 12px;border-left:4px solid #72aee6;background:#f0f6fc">No matching email activity found in SMTP2GO for the selected criteria.</p>';
			}

			$get = function ( $e, $keys ) {
				foreach ( $keys as $k ) {
					if ( isset( $e[ $k ] ) && is_scalar( $e[ $k ] ) && '' !== (string) $e[ $k ] ) {
						return (string) $e[ $k ];
					}
				}
				return '';
			};

			$html  = '<p>' . count( $events ) . ' event(s) found. Times shown in ' . esc_html( wp_timezone_string() ) . '.' . ( $token ? ' <strong>Only the first 500 shown.</strong>' : '' ) . '</p>';
			$html .= '<div style="overflow-x:auto"><table style="width:100%;min-width:720px;border-collapse:collapse;font-size:14px">';
			$html .= '<thead><tr style="background:#f6f7f7"><th style="text-align:left;padding:6px">Recipient</th><th style="text-align:left;padding:6px">Sender</th><th style="text-align:left;padding:6px">Subject</th><th style="text-align:left;padding:6px">Date and time</th><th style="text-align:left;padding:6px">Status</th><th style="text-align:left;padding:6px">Message ID</th></tr></thead><tbody>';

			foreach ( $events as $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				$raw = $get( $e, array( 'date' ) );
				$ts  = $raw ? strtotime( preg_match( '/(Z|[+-]\d{2}:?\d{2})$/', $raw ) ? $raw : $raw . ' UTC' ) : 0;

				$html .= '<tr style="border-top:1px solid #f0f0f1">'
					. '<td style="padding:6px">' . esc_html( $get( $e, array( 'recipient', 'email' ) ) ?: '-' ) . '</td>'
					. '<td style="padding:6px">' . esc_html( $get( $e, array( 'sender', 'from' ) ) ?: '-' ) . '</td>'
					. '<td style="padding:6px">' . esc_html( $get( $e, array( 'subject' ) ) ?: '-' ) . '</td>'
					. '<td style="padding:6px;white-space:nowrap">' . esc_html( $ts ? wp_date( 'd/m/Y g:i:s a', $ts ) : ( $raw ?: '-' ) ) . '</td>'
					. '<td style="padding:6px">' . esc_html( $get( $e, array( 'event', 'event_type' ) ) ?: 'unknown' ) . '</td>'
					. '<td style="padding:6px;font-family:monospace;font-size:12px;word-break:break-all">' . esc_html( $get( $e, array( 'email_id', 'message_id' ) ) ?: '-' ) . '</td>'
					. '</tr>';
			}

			return $html . '</tbody></table></div>';
		} )();
	}

	$form = '<form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;margin-bottom:16px">'
		. wp_nonce_field( 'smtp2go_raw_search', '_s2gnonce', true, false )
		. '<label>Recipient (any text)<br><input type="text" name="recipient" required maxlength="254" style="width:100%" value="' . esc_attr( $f['recipient'] ) . '"></label>'
		. '<label>Subject (optional)<br><input type="text" name="subject" maxlength="200" style="width:100%" value="' . esc_attr( $f['subject'] ) . '"></label>'
		. '<label>Start date<br><input type="date" name="start" required max="' . esc_attr( $today ) . '" style="width:100%" value="' . esc_attr( $f['start'] ) . '"></label>'
		. '<label>End date<br><input type="date" name="end" required max="' . esc_attr( $today ) . '" style="width:100%" value="' . esc_attr( $f['end'] ) . '"></label>'
		. '<button type="submit" name="smtp2go_raw_search" value="1">Search SMTP2GO</button>'
		. '</form>';

	return $form . $out;
} );
/**
 * [ams2_check_probe id="124"] Admin-only: asks RTO Data (v2 check) whether an AMS2 row's enrolment exists.
 * Read-only. Does not change the row.
 */
add_shortcode( 'ams2_check_probe', function ( $atts ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	$row  = ams2_row( absint( $atts['id'] ) );
	if ( ! $row ) {
		return '<p>No AMS2 row with ID ' . (int) $atts['id'] . '.</p>';
	}

	$sd     = json_decode( (string) $row->student_data, true ) ?: array();
	$emails = array(
		'placeholder' => 'processing' . (int) $row->id . 'hold.invalid',
		'real'        => $row->real_email,
	);
	$course = sanitize_key( $_POST['ams2_course_field'] ?? 'code' );

	$out = '<p><strong>AMS2-' . (int) $row->id . '</strong> status ' . esc_html( $row->status )
		. ', course code "' . esc_html( $row->course_code ) . '", course ID ' . (int) $row->course_id . '</p>';

	if ( isset( $_POST['ams2_check_probe'] ) && check_admin_referer( 'ams2_check_probe' ) ) {
		$which = sanitize_key( $_POST['ams2_check_probe'] );

		if ( isset( $emails[ $which ] ) ) {
			$args = array(
				'type'       => 'enrolment',
				'first_name' => strtoupper( trim( $sd['first_name'] ?? '' ) ),
				'last_name'  => strtoupper( trim( $sd['last_name'] ?? '' ) ),
				'email'      => $emails[ $which ],
			);
			if ( 'id' === $course || 'both' === $course ) {
				$args['course_id'] = (int) $row->course_id;
			}
			if ( 'code' === $course || 'both' === $course ) {
				$args['course_code'] = $row->course_code;
			}

			$res = ams2_api( $args, 'check' );

			$out .= '<h4>Sent (' . esc_html( $which ) . ' email, course ' . esc_html( $course ) . ')</h4>'
				. '<pre style="white-space:pre-wrap">' . esc_html( print_r( $args, true ) ) . '</pre>'
				. '<h4>RTO Data response: HTTP ' . (int) $res['http'] . ( $res['error'] ? ', ' . esc_html( $res['error'] ) : '' ) . '</h4>'
				. '<pre style="white-space:pre-wrap;word-break:break-all">' . esc_html( $res['raw'] ?: '(empty body)' ) . '</pre>';
		}
	}

	$opt = function ( $v, $label ) use ( $course ) {
		return '<option value="' . $v . '"' . selected( $course, $v, false ) . '>' . $label . '</option>';
	};

	return $out . '<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
		. wp_nonce_field( 'ams2_check_probe', '_wpnonce', true, false )
		. '<label>Send course as <select name="ams2_course_field">'
		. $opt( 'code', 'course_code' ) . $opt( 'id', 'course_id' ) . $opt( 'both', 'both' )
		. '</select></label>'
		. '<button type="submit" name="ams2_check_probe" value="placeholder">Check with placeholder email</button>'
		. '<button type="submit" name="ams2_check_probe" value="real">Check with real email</button>'
		. '</form>';
} );
add_filter( 'register_post_type_args', function ( $args, $post_type ) {
	if ( 'blacklist' !== $post_type ) {
		return $args;
	}
	$args['public']              = false;
	$args['publicly_queryable']  = false;
	$args['exclude_from_search'] = true;
	$args['show_in_nav_menus']   = false;
	$args['show_in_rest']        = false;
	$args['has_archive']         = false;
	$args['rewrite']             = false;
	$args['query_var']           = false;
	$args['show_ui']             = true;
	$args['show_in_menu']        = true;
	return $args;
}, 99, 2 );
/**
 * ============================================================
 * AMS personal data purge (hourly)
 * Clears student details a day after an enrolment has finished.
 * Status and test page (admin only): [ams_purge_debug]
 * ============================================================
 */

add_action( 'init', function () {
	if ( wp_next_scheduled( 'ams_purge_personal_data' ) ) {
		wp_clear_scheduled_hook( 'ams_purge_personal_data' );
	}
	if ( ! wp_next_scheduled( 'ams_purge_personal_data_hourly' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'ams_purge_personal_data_hourly' );
	}
} );

add_action( 'ams_purge_personal_data_hourly', 'ams_purge_personal_data_run' );

function ams_purge_personal_data_run() {
	global $wpdb;

	$counts = array();
	$errors = array();

	$queries = array(
		'old_flow' => "UPDATE {$wpdb->prefix}enrolment_requests
			SET student_data = '{}'
			WHERE status IN ('enrolment_created','documents_uploaded','payment_recorded','payment_failed')
			  AND created_at < NOW() - INTERVAL 1 DAY
			  AND student_data <> '{}'",

		'v2' => "UPDATE {$wpdb->prefix}ams2_enrolments
			SET student_data = '{}', real_email = ''
			WHERE status = 'complete'
			  AND updated_at < NOW() - INTERVAL 1 DAY
			  AND student_data <> '{}'",

		'v2_docs' => "UPDATE {$wpdb->prefix}ams2_documents
			SET original_name = ''
			WHERE status = 'sent'
			  AND sent_at < NOW() - INTERVAL 1 DAY
			  AND original_name <> ''",

		'old_docs' => "UPDATE {$wpdb->prefix}ams_enrolment_documents
			SET original_name = ''
			WHERE status = 'sent'
			  AND sent_at < NOW() - INTERVAL 1 DAY
			  AND original_name <> ''",
	);

	foreach ( $queries as $name => $sql ) {
		$result          = $wpdb->query( $sql );
		$counts[ $name ] = false === $result ? 0 : (int) $result;
		if ( $wpdb->last_error ) {
			$errors[] = $name . ': ' . $wpdb->last_error;
		}
	}

	update_option( 'ams_purge_last_run', array(
		'time'   => current_time( 'mysql' ),
		'counts' => $counts,
		'errors' => $errors,
	), false );

	if ( ( array_sum( $counts ) || $errors ) && function_exists( 'ams_log' ) ) {
		ams_log( $errors ? 'error' : 'info', 'personal_data_purged', implode( ' | ', $errors ), $counts );
	}
}

add_shortcode( 'ams_purge_debug', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$out = '';
	if ( isset( $_POST['ams_purge_now'] ) && check_admin_referer( 'ams_purge_now' ) ) {
		ams_purge_personal_data_run();
		$out .= '<p><strong>Ran now.</strong></p>';
	}

	$next = wp_next_scheduled( 'ams_purge_personal_data_hourly' );
	$last = get_option( 'ams_purge_last_run' );

	$text = 'Next run: ' . ( $next ? wp_date( 'd/m/Y H:i:s', $next ) : 'NOT SCHEDULED' ) . "\n";
	if ( $last ) {
		$text .= 'Last run: ' . $last['time'] . "\n";
		foreach ( (array) $last['counts'] as $k => $v ) {
			$text .= '  ' . $k . ' cleared: ' . $v . "\n";
		}
		foreach ( (array) $last['errors'] as $e ) {
			$text .= '  ERROR ' . $e . "\n";
		}
	} else {
		$text .= 'Last run: never';
	}

	return $out . '<pre>' . esc_html( $text ) . '</pre>'
		. '<form method="post">' . wp_nonce_field( 'ams_purge_now', '_wpnonce', true, false )
		. '<button type="submit" name="ams_purge_now" value="1">Run purge now</button></form>';
} );
/* ---------- AMS2: resend confirmation email from [ams2_status] ---------- */

function ams2_can_resend( $row ) {
	return $row && ! empty( $row->enrolment_id )
		&& ( 'complete' === $row->status || ( 'needs_review' === $row->status && 'updated' === $row->resume_status ) );
}

function ams2_key_person( $secret_key ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT firstName, lastName, emailaddress FROM {$wpdb->prefix}secret_keys WHERE secret_key = %s LIMIT 1",
		$secret_key
	) );
}

function ams2_resend_confirmation( $row, $to ) {
	$posts = get_posts( array(
		'post_type'      => 'ams_enrol_email',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array( 'key' => 'course_id', 'value' => (int) $row->course_id ),
			array( 'key' => 'plan_id', 'value' => (int) $row->plan_id ),
		),
	) );
	if ( ! $posts ) {
		return 'No published Enrolment Email for course ' . (int) $row->course_id . ' / plan ' . (int) $row->plan_id . '.';
	}

	$tpl   = $posts[0];
	$sd    = json_decode( (string) $row->student_data, true ) ?: array();
	$first = trim( (string) ( $sd['first_name'] ?? '' ) );
	$last  = trim( (string) ( $sd['last_name'] ?? '' ) );

	if ( '' === $first ) { // Purged row: use the checkout name the form was locked to.
		$person = ams2_key_person( $row->secret_key );
		$first  = trim( (string) ( $person->firstName ?? '' ) );
		$last   = trim( (string) ( $person->lastName ?? '' ) );
	}

	$title = trim( (string) get_post_meta( $tpl->ID, 'course_title', true ) ) ?: (string) $row->course_title;
	$body  = str_replace(
		array( '{first_name}', '{last_name}', '{course_title}' ),
		array(
			esc_html( mb_strtoupper( $first, 'UTF-8' ) ),
			esc_html( mb_strtoupper( $last, 'UTF-8' ) ),
			esc_html( $title ),
		),
		(string) get_post_meta( $tpl->ID, 'email_body', true )
	);

	$attachments = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$file_id = (int) get_post_meta( $tpl->ID, 'attachment_' . $i, true );
		$path    = $file_id ? get_attached_file( $file_id ) : '';
		if ( $path && file_exists( $path ) ) {
			$attachments[] = $path;
		}
	}

	$mail_error = '';
	$catch      = function ( $wp_error ) use ( &$mail_error ) {
		$mail_error = $wp_error->get_error_message();
	};
	add_action( 'wp_mail_failed', $catch );

	$sent = wp_mail(
		$to,
		trim( 'Enrolment confirmation ' . $title ),
		$body,
		array( 'Content-Type: text/html; charset=UTF-8', 'From: AMS Training <info@amstraining.com.au>' ),
		$attachments
	);

	remove_action( 'wp_mail_failed', $catch );

	return $sent ? true : ( $mail_error ?: 'wp_mail returned false' );
}
/**
 * [ams2_course_check] Admin-only: RTO Data "check" by course_id + first_name + last_name.
 * Read-only. Optional: enter an AMS2 ref to fill the fields from that row.
 */
add_shortcode( 'ams2_course_check', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$f = array(
		'ref'        => absint( $_POST['ref'] ?? 0 ),
		'course_id'  => absint( $_POST['course_id'] ?? 0 ),
		'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
		'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
	);
	$out = '';

	if ( isset( $_POST['ams2_cc'] ) && check_admin_referer( 'ams2_course_check' ) ) {

		// Fill from an AMS2 row when a ref is given.
		if ( $f['ref'] && 'load' === $_POST['ams2_cc'] ) {
			$row = function_exists( 'ams2_row' ) ? ams2_row( $f['ref'] ) : null;
			if ( ! $row ) {
				$out .= '<p style="color:#b32d2e"><strong>No AMS2 row ' . $f['ref'] . '.</strong></p>';
			} else {
				$sd    = json_decode( (string) $row->student_data, true ) ?: array();
				$first = trim( (string) ( $sd['first_name'] ?? '' ) );
				$last  = trim( (string) ( $sd['last_name'] ?? '' ) );
				if ( '' === $first && function_exists( 'ams2_key_person' ) ) {
					$p     = ams2_key_person( $row->secret_key );
					$first = trim( (string) ( $p->firstName ?? '' ) );
					$last  = trim( (string) ( $p->lastName ?? '' ) );
				}
				$f['course_id']  = (int) $row->course_id;
				$f['first_name'] = mb_strtoupper( $first, 'UTF-8' ); // Enrolments are created in uppercase.
				$f['last_name']  = mb_strtoupper( $last, 'UTF-8' );
				$out .= '<p>Loaded AMS2-' . $f['ref'] . ' (status ' . esc_html( $row->status ) . ', enrolment ID ' . esc_html( $row->enrolment_id ?: 'none' ) . ').</p>';
			}
		}

		if ( 'send' === $_POST['ams2_cc'] ) {
			if ( ! $f['course_id'] || '' === $f['first_name'] || '' === $f['last_name'] ) {
				$out .= '<p style="color:#b32d2e"><strong>Course ID, first name and last name are all required.</strong></p>';
			} elseif ( ! function_exists( 'ams2_api' ) ) {
				$out .= '<p style="color:#b32d2e"><strong>ams2_api() is missing. Nothing was sent.</strong></p>';
			} else {
				$args = array(
					'type'       => 'course',
					'course_id'  => (string) $f['course_id'],
					'first_name' => $f['first_name'],
					'last_name'  => $f['last_name'],
				);
				$start = microtime( true );
				$res   = ams2_api( $args, 'check' );
				$secs  = round( microtime( true ) - $start, 2 );

				$out .= '<h4>Sent to /api/v2/check</h4><pre style="white-space:pre-wrap">' . esc_html( print_r( $args, true ) ) . '</pre>'
					. '<h4>Response: HTTP ' . (int) $res['http'] . ' in ' . $secs . 's' . ( $res['error'] ? ', ' . esc_html( $res['error'] ) : '' ) . '</h4>'
					. '<pre style="white-space:pre-wrap;word-break:break-all;max-width:100%;overflow-x:auto">'
					. esc_html( is_array( $res['body'] ) ? print_r( $res['body'], true ) : ( $res['raw'] ?: '(empty body)' ) )
					. '</pre>';
			}
		}
	}

	$in = function ( $name, $label, $type = 'text', $w = '180px' ) use ( $f ) {
		return '<label>' . $label . '<br><input type="' . $type . '" name="' . $name . '" value="' . esc_attr( $f[ $name ] ?: '' ) . '" style="width:' . $w . ';max-width:100%"></label>';
	};

	return '<form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:16px">'
		. wp_nonce_field( 'ams2_course_check', '_wpnonce', true, false )
		. $in( 'ref', 'AMS2 ref (optional)', 'number', '110px' )
		. '<button type="submit" name="ams2_cc" value="load">Load from ref</button>'
		. $in( 'course_id', 'Course ID', 'number', '110px' )
		. $in( 'first_name', 'First name' )
		. $in( 'last_name', 'Last name' )
		. '<button type="submit" name="ams2_cc" value="send">Check RTO Data</button>'
		. '</form>' . $out;
} );
/**
 * ============================================================
 * AMS2 auto-check after an enrol timeout (ADDITIVE)
 *
 * enrol gets no clear answer -> 'verifying' -> course check after 5 minutes.
 * The check returns the student's active enrolment (status 0) if they have one,
 * otherwise their latest finished one (2 Complete, 3 Withdrawn, 4 Cancelled).
 *
 *   status 0, ID above floor          -> this row's enrolment: link it and carry on
 *   status 2/3/4, ID at/below floor   -> new one was never created: enrol again
 *   not found                         -> enrol again
 *   status 0, ID at/below floor       -> student already has an older open enrolment: needs_review
 *   anything else                     -> needs_review
 *   check fails                       -> retry every 5 minutes, then needs_review
 * Re-enrols are capped at AMS2_MAX_REENROLS.
 * ============================================================
 */

if ( ! defined( 'AMS2_VERIFY_DELAY' ) ) {
	define( 'AMS2_VERIFY_DELAY', 5 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'AMS2_VERIFY_MAX_CHECKS' ) ) {
	define( 'AMS2_VERIFY_MAX_CHECKS', 6 );
}
if ( ! defined( 'AMS2_MAX_REENROLS' ) ) {
	define( 'AMS2_MAX_REENROLS', 2 );
}

/* One-off: add the columns the check needs. */
add_action( 'init', function () {
	if ( '1' === get_option( 'ams2_verify_cols' ) ) {
		return;
	}
	global $wpdb;
	$t    = ams2_table();
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t}" );
	if ( ! in_array( 'verify_after', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE {$t} ADD verify_after datetime NULL, ADD verify_checks int(11) NOT NULL DEFAULT 0, ADD reenrol_count int(11) NOT NULL DEFAULT 0" );
	}
	update_option( 'ams2_verify_cols', '1', false );
}, 20 );

function ams2_start_verify( $id, $reason ) {
	ams2_set( $id, array(
		'status'        => 'verifying',
		'verify_after'  => wp_date( 'Y-m-d H:i:s', time() + AMS2_VERIFY_DELAY ),
		'verify_checks' => 0,
		'error_message' => $reason . ' Checking RTO Data before retrying.',
	) );
	ams2_log( 'warning', 'verify_started', $id, $reason );
}

// Highest enrolment ID already given to anything submitted before this row.
// This row's real enrolment must be above it.
function ams2_enrolment_floor( $row ) {
	global $wpdb;
	$a = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT MAX(CAST(enrolment_id AS UNSIGNED)) FROM ' . ams2_table() . " WHERE id < %d AND enrolment_id <> ''",
		$row->id
	) );
	$b = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT MAX(CAST(enrolment_id AS UNSIGNED)) FROM {$wpdb->prefix}enrolment_requests WHERE created_at < %s AND enrolment_id IS NOT NULL",
		$row->created_at
	) );
	return max( $a, $b );
}

function ams2_verify_reenrol( $row, $reason ) {
	if ( (int) $row->reenrol_count >= AMS2_MAX_REENROLS ) {
		ams2_fail( $row->id, 'received', $reason . ' Already re-enrolled ' . (int) $row->reenrol_count . ' times. Check RTO Data before resuming.', false );
		return;
	}
	ams2_set( $row->id, array(
		'status'        => 'received',
		'attempts'      => 0,
		'reenrol_count' => (int) $row->reenrol_count + 1,
		'error_message' => $reason . ' Enrolling again.',
	) );
	ams2_log( 'info', 'verify_reenrol', $row->id, $reason, array( 'reenrol' => (int) $row->reenrol_count + 1 ) );
	ams2_schedule( $row->id );
}

function ams2_step_verify( $row ) {
	if ( ! $row || ! ams2_claim( $row->id, 'verifying', 'checking' ) ) {
		return;
	}

	$sd   = json_decode( (string) $row->student_data, true ) ?: array();
	$args = array(
		'type'       => 'course',
		'course_id'  => (string) (int) $row->course_id,
		'first_name' => trim( (string) ( $sd['first_name'] ?? '' ) ),
		'last_name'  => trim( (string) ( $sd['last_name'] ?? '' ) ),
	);

	if ( '' === $args['first_name'] || '' === $args['last_name'] ) {
		ams2_fail( $row->id, 'received', 'Auto-check not possible: no name on the row. Check RTO Data before resuming.', false );
		return;
	}

	$res  = ams2_api( $args, 'check' );
	$body = $res['body'];

	// The check itself failed: try again later. Never treat this as "not found".
	if ( ! $res['sent'] || $res['http'] >= 500 || ! is_array( $body ) ) {
		$checks = (int) $row->verify_checks + 1;
		if ( $checks >= AMS2_VERIFY_MAX_CHECKS ) {
			ams2_fail( $row->id, 'received', 'Could not check RTO Data after ' . $checks . ' tries (' . ( $res['error'] ?: 'HTTP ' . $res['http'] ) . '). The enrolment may exist. Check RTO Data before resuming.', false );
			return;
		}
		ams2_set( $row->id, array(
			'status'        => 'verifying',
			'verify_checks' => $checks,
			'verify_after'  => wp_date( 'Y-m-d H:i:s', time() + AMS2_VERIFY_DELAY ),
		) );
		ams2_log( 'warning', 'verify_check_failed', $row->id, 'Check ' . $checks . ' of ' . AMS2_VERIFY_MAX_CHECKS );
		return;
	}

	$data = ( ! empty( $body['success'] ) && ! empty( $body['data'] ) && is_array( $body['data'] ) ) ? $body['data'] : array();

	// Not found: the enrolment was never created.
	if ( ! $data || empty( $data['enrolment_id'] ) ) {
		ams2_verify_reenrol( $row, 'Not found in RTO Data after timeout.' );
		return;
	}

	$eid    = (int) $data['enrolment_id'];
	$status = isset( $data['status_id'] ) ? (int) $data['status_id'] : -1;
	$floor  = ams2_enrolment_floor( $row );
	$found  = $eid . ' (status ' . $status . ', created ' . ( $data['created'] ?? '?' ) . ')';

	// Active and newer than anything linked before: this row's enrolment.
	if ( 0 === $status && $eid > $floor ) {
		ams2_set( $row->id, array(
			'status'        => 'enrolled',
			'resume_status' => '',
			'attempts'      => 0,
			'error_message' => null,
			'enrolment_id'  => (string) $eid,
			'learner_id'    => (string) (int) ( $data['learner_id'] ?? 0 ),
		) );
		ams2_log( 'info', 'verify_linked', $row->id, '', array(
			'enrolment_id' => $eid,
			'learner_id'   => (int) ( $data['learner_id'] ?? 0 ),
			'floor'        => $floor,
		) );
		ams2_schedule( $row->id );
		return;
	}

	// Only an older finished enrolment: no active one exists, so the new one was never created.
	if ( in_array( $status, array( 2, 3, 4 ), true ) && $eid <= $floor ) {
		ams2_verify_reenrol( $row, 'Only an older finished enrolment in RTO Data: ' . $found . '.' );
		return;
	}

	if ( 0 === $status ) {
		$why = 'The student already has an older open enrolment on this course: ' . $found . '. Decide whether to use it or enrol again.';
	} else {
		$why = 'The RTO Data check returned an enrolment that can\'t be matched to this one: ' . $found . ' (floor ' . $floor . ').';
	}
	ams2_fail( $row->id, 'received', 'Timed out. ' . $why . ' Check RTO Data before resuming.', false );
}

/* Runs on the existing ams2 cron, after its own work. */
add_action( 'ams2_cron', function () {
	global $wpdb;
	$t = ams2_table();

	// A check that started but never finished is safe to repeat (it only reads).
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$t} SET status = 'verifying' WHERE status = 'checking' AND updated_at < %s",
		wp_date( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS )
	) );

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM {$t} WHERE status = 'verifying' AND verify_after <= %s ORDER BY id ASC LIMIT 5",
		current_time( 'mysql' )
	) );
	foreach ( $ids as $id ) {
		ams2_step_verify( ams2_row( (int) $id ) );
	}
}, 20 );

// TEMP: simulate 503s on v2 uploads for admins. Remove after testing.
add_action( 'wp_ajax_ams2_upload', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$n = (int) get_transient( 'ams2_fake_503' );
	if ( $n < 6 ) {
		set_transient( 'ams2_fake_503', $n + 1, 10 * MINUTE_IN_SECONDS );
		status_header( 503 );
		exit;
	}
}, 1 );

