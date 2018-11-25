<?php
// Do not allow direct access over web.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 0 );
}

/**
 * Close the activity
 *
 * @param int $activity_id numeric activity id.
 *
 * @return bool
 */
function bplua_close_activity( $activity_id ) {
	$instance = bp_activity_lock_get_helper();

	return $instance->close( $activity_id );
}

/**
 * Open activity
 *
 * @param int $activity_id numeric activity id.
 *
 * @return bool
 */
function bplua_open_activity( $activity_id ) {

	$instance = bp_activity_lock_get_helper();

	return $instance->open( $activity_id );
}

/**
 * Check if activity is closed.
 *
 * @param int $activity_id numeric activity id.
 *
 * @return bool
 */
function bplua_is_activity_closed( $activity_id ) {
	$instance = bp_activity_lock_get_helper();
	return $instance->is_closed( $activity_id );
}

