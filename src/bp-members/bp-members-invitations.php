<?php
/**
 * BuddyPress Member Activity
 *
 * @package BuddyPress
 * @subpackage MembersActivity
 * @since 2.2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

function bp_invitations_setup_nav() {
	error_log( "running setup_invitations_nav"  );

	/* Add 'Send Invites' to the main user profile navigation */
	bp_core_new_nav_item( array(
		'name' => __( 'Invitations', 'buddypress' ),
		'slug' => bp_get_members_invitations_slug(),
		'position' => 80,
		'screen_function' => 'members_screen_send_invites',
		'default_subnav_slug' => 'invite-new-members',
		'show_for_displayed_user' => true
	) );

	$parent_link = trailingslashit( bp_loggedin_user_domain() . bp_get_members_invitations_slug() );

	/* Create two sub nav items for this component */
	bp_core_new_subnav_item( array(
		'name' => __( 'Invite New Members', 'buddypress' ),
		'slug' => 'invite-new-members',
		'parent_slug' => bp_get_members_invitations_slug(),
		'parent_url' => $parent_link,
		'screen_function' => 'members_screen_send_invites',
		'position' => 10,
		'user_has_access' => true
	) );

	bp_core_new_subnav_item( array(
		'name' => __( 'Sent Invites', 'invite-anyone' ),
		'slug' => 'sent-invites',
		'parent_slug' => bp_get_members_invitations_slug(),
		'parent_url' => $parent_link,
		'screen_function' => 'members_screen_list_sent_invites',
		'position' => 20,
		'user_has_access' => true
	) );
}
add_action( 'bp_setup_nav', array( 'bp_invitations_setup_nav' ) );
