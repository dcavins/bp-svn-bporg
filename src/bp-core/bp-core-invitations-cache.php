<?php
/**
 * BuddyPress Invitation Caching Functions.
 *
 * Caching functions handle the clearing of cached objects and pages on specific
 * actions throughout BuddyPress.
 *
 * @package BuddyPress
 * @subpackage InvitationsCache
 * @since 5.0.0
 */

/**
 * Resets all incremented bp_invitations caches.
 *
 * @since 5.0.0
 */
function bp_invitations_reset_cache_incrementor() {
	bp_core_reset_incrementor( 'bp_invitations' );
}
add_action( 'bp_invitation_after_save', 'bp_invitations_reset_cache_incrementor' );
add_action( 'bp_invitation_after_delete', 'bp_invitations_reset_cache_incrementor' );
