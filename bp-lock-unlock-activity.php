<?php
/**
 * Plugin Name: BuddyPress Lock Unlock Activity
 * Version: 1.0.6
 * Plugin URI: https://buddydev.com/plugins/bp-lock-unlock-activity/
 * Author: BuddyDev
 * Author URI: https://buddydev.com
 * Description: Allow Users to lock unlock activity for commenting.
 *
 * Original Contributor: Anu Sharma, Brajesh Singh.
 */

// No direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main helper class
 */
class BP_Lock_Unlock_Activity_Helper {

	/**
	 * Class instance
	 *
	 * @var BP_Lock_Unlock_Activity_Helper
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	private function __construct() {
		// load the functions.php which can be used by others.
		add_action( 'bp_loaded', array( $this, 'load' ) );
		// show open close button on activity entries.
		add_action( 'bp_activity_entry_meta', array( $this, 'show_btn' ) );

		// handle the open/close action.
		add_action( 'bp_actions', array( $this, 'handle_open_close' ), 1000 );

		// filter the activity commenting capability.
		add_filter( 'bp_activity_can_comment', array( $this, 'check_comment_status' ) );

		// filter activity comment reply capability.
		add_filter( 'bp_activity_can_comment_reply', array( $this, 'check_comment_reply_status' ), 10, 2 );

		add_action( 'bp_init', array( $this, 'load_translation' ), 2 );
		add_action( 'wp_ajax_bpal_lock_unlock_activity', array( $this, 'ajax_lock_unlock_activity' ) );

		add_action( 'bp_enqueue_scripts', array( $this, 'load_assets' ) );
	}

	/**
	 * Get the singleton instance
	 *
	 * @return BP_Lock_Unlock_Activity_Helper
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load the translation file
	 */
	public function load_translation() {
		load_plugin_textdomain( 'bp-lock-unlock-activity', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Load core
	 */
	public function load() {
		$files = array(
			'bp-lock-unlock-activity-functions.php',
		);

		$path = plugin_dir_path( __FILE__ );

		foreach ( $files as $file ) {
			require_once $path . $file;
		}
	}

	/**
	 * Handles the opening/closing of activity
	 * Based on the current action, it either closes activity for comment or opens it
	 */
	public function handle_open_close() {

		if ( ! bp_is_user() && bp_is_activity_component() && bp_current_action() && ! bp_is_single_activity() ) {

			if ( ! is_user_logged_in() ) {
				return;
			}

			$message     = '';
			$action      = bp_current_action();
			$activity_id = bp_action_variable( 0 );

			if ( ! $action || ! $activity_id ) {
				return;
			}

			// if we are here, we know the action and the activity id.
			// make sure only super admins or the owner of activity can close it.
			if ( self::user_can_update_activity( $activity_id ) ) {
				// Are we closing the activity for comment.
				if ( 'close' === $action ) {
					self::close( $activity_id );
					$message = __( 'Activity Locked for commenting.', 'bp-lock-unlock-activity' );
				} elseif ( 'open' === $action ) {
					// Are we opening again the activity for commenting.
					self::open( $activity_id );
					$message = __( 'Activity Unlocked for commenting.', 'bp-lock-unlock-activity' );
				}
			} else {
				// let user know that he is a crook may be.
				$message = __( "You Don't have permission to do this.", 'bp-lock-unlock-activity' );
			}

			if ( $message ) {
				bp_core_add_message( $message );
			}

			// redirect back to the last page.
			bp_core_redirect( wp_get_referer() );
		}
	}

	/**
	 * Ajax processing of the open/close request.
	 */
	public function ajax_lock_unlock_activity() {

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bp_activity_open_close_link' ) ) {
			wp_send_json_error( __( 'Invalid action!', 'bp-lock-unlock-activity' ) );
		}

		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;

		if ( ! $activity_id ) {
			return;// someone is playing.
		}

		$activity = new BP_Activity_Activity( $activity_id );

		if ( ! $activity->id ) {
			return; // seriously, you plan to fool the system, we don't allow that?
		}

		// if we are here, check if the user can update status?
		if ( ! self::user_can_update_activity( $activity_id ) ) {
			wp_send_json_error( __( 'You are not allowed to lock/unlock activity.', 'bp-lock-unlock-activity' ) );
		}

		// if we are here, the request is genuine, let's try to fulfill it.
		if ( self::is_open( $activity_id ) ) {
			self::close( $activity_id );
		} else {
			self::open( $activity_id );
		}

		ob_start();
		// load activity.
		if ( bp_has_activities( array( 'include' => $activity_id ) ) ) {
			while ( bp_activities() ) {
				bp_the_activity();
				bp_get_template_part( 'activity/entry' );
			}
		}

		$content = ob_get_clean();
		wp_send_json_success( $content );
	}

	/**
	 * Load assets.
	 */
	public function load_assets() {
		if ( ! is_user_logged_in() ) {
			return; // only logged in user can toggle state.
		}
		wp_register_script( 'bp-lock-unlock-activity', plugin_dir_url( __FILE__ ) . 'assets/bp-lock-unlock-activity.js', array( 'jquery' ) );
		wp_enqueue_script( 'bp-lock-unlock-activity' );
	}

	/**
	 * Check if the current user can update activity e.g. lock/unlock it for commenting
	 *
	 * @param int $activity_id numeric activity id.
	 *
	 * @return boolean
	 */
	public static function user_can_update_activity( $activity_id ) {
		// super admins, site admins can do it.
		if ( is_super_admin() || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$can_update = false;

		// now check if the activity belongs to logged in user.
		$activity = new BP_Activity_Activity( $activity_id );
		if ( get_current_user_id() == $activity->user_id ) {
			$can_update = true;
		}

		return apply_filters( 'bp_lock_unlock_user_can_update_activity', $can_update );
	}

	/**
	 * Check if the activity is open for commenting or not
	 *
	 * If the activity is not open for commenting it sets the commenting capability to false
	 *
	 * @global BP_Activity_Template $activities_template global activity template object.
	 *
	 * @param boolean $can_comment can the user edit.
	 *
	 * @return boolean
	 */
	public function check_comment_status( $can_comment ) {

		global $activities_template;

		$activity = $activities_template->activity;

		if ( self::is_closed( $activity->id ) ) {
			$can_comment = false;
		}

		return $can_comment;
	}

	/**
	 * Check if the activity comment can be replied or not
	 *
	 * Based on the settings of the parent activity, It sets the reply capability to false if the activity is not open for comment
	 *
	 * @global BP_Activity_Template $activities_template global activity template object.
	 *
	 * @param boolean              $can_comment can edit.
	 * @param BP_Activity_Activity $comment activity comment.
	 *
	 * @return boolean
	 */
	public function check_comment_reply_status( $can_comment, $comment ) {
		global $activities_template;

		$activity = $activities_template->activity;

		if ( self::is_closed( $activity->id ) ) {
			$can_comment = false;
		}

		return $can_comment;
	}

	/**
	 *  Generate the Button and put it in the activity entry meta
	 */
	public function show_btn() {
		global $activities_template;

		if ( self::user_can_update_activity( $activities_template->activity->id ) ) {
			echo $this->get_close_open_link();
		}
	}

	/**
	 * Get the link to open/close an activity
	 *
	 * @global BP_Activity_Template $activities_template global activity template object.
	 *
	 * @return string
	 */
	public function get_close_open_link() {
		global $activities_template;

		$activity = $activities_template->activity;

		$url = bp_get_root_domain() . '/' . bp_get_activity_root_slug() . '/';

		$class = 'open-close-activity';

		if ( self::is_closed( $activity->id ) ) {
			$label           = __( 'Open', 'bp-lock-unlock-activity' );
			$link_title_attr = __( 'Reopen Activity for commenting', 'bp-lock-unlock-activity' );
			$url             = $url . 'open/' . $activity->id;
			$class           .= ' bplua-open-activity';
		} else {
			$label           = __( 'Close', 'bp-lock-unlock-activity' );
			$link_title_attr = __( 'Lock Activity, do not allow commenting', 'bp-lock-unlock-activity' );
			$url             = $url . 'close/' . $activity->id;
			$class           .= ' bplua-close-activity';
		}

		$url .= '/';

		// . '/delete/' . $activities_template->activity->id;
		$url = wp_nonce_url( $url, 'bp_activity_open_close_link' );

		$link = "<a data-activity-id='{$activity->id}' href='{$url}' class='button item-button bp-secondary-action {$class}' rel='nofollow' title='{$link_title_attr}'>{$label}</a>";

		return $link;
	}

	/**
	 * Check if the activity is closed for comment
	 *
	 * @param int $activity_id numeric activity id.
	 *
	 * @return bool
	 */
	public static function is_closed( $activity_id ) {
		return (boolean) bp_activity_get_meta( $activity_id, 'is_closed' );
	}

	/**
	 * Check if the activity is open for comment
	 *
	 * @param int $activity_id numeric activity id.
	 *
	 * @return bool
	 */
	public static function is_open( $activity_id ) {
		return ! self::is_closed( $activity_id );
	}

	/**
	 * Close the activity
	 *
	 * @param int $activity_id numeric activity id.
	 *
	 * @return bool
	 */
	public static function close( $activity_id ) {
		return bp_activity_update_meta( $activity_id, 'is_closed', 1 );
	}

	/**
	 * Open activity for commenting
	 *
	 * @param int $activity_id numeric activity id.
	 *
	 * @return bool
	 */
	public static function open( $activity_id ) {
		return bp_activity_delete_meta( $activity_id, 'is_closed', 1 );
	}
}

/**
 * Helper function
 *
 * @return BP_Lock_Unlock_Activity_Helper
 */
function bp_activity_lock_get_helper() {
	return BP_Lock_Unlock_Activity_Helper::get_instance();
}

// instantiate the helper.
bp_activity_lock_get_helper();
