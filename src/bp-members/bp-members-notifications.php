<?php
/**
 * BuddyPress Members Notification Functions.
 *
 * These functions handle the recording, deleting and formatting of notifications
 * for the user and for this specific component.
 *
 * @package BuddyPress
 * @subpackage Members
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Emails ********************************************************************/

/**
 * Notify site admins about new membership request.
 *
 * @since 1.0.0
 *
 * @param int $requesting_user_id ID of the user requesting site membership.
 * @param int $admin_id           ID of the site admin.
 * @param int $invitation_id      ID of the network invitation object.
 */
function bp_members_new_membership_request( $requesting_user_id = 0, $admin_id = 0, $membership_id = 0 ) {

	// Trigger a BuddyPress Notification.
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_add_notification( array(
			'user_id'           => $admin_id,
			'item_id'           => $group_id,
			'secondary_item_id' => $requesting_user_id,
			'component_name'    => buddypress()->members->id,
			'component_action'  => 'new_membership_request',
		) );
	}

	// Bail if member opted out of receiving this email.
	if ( 'no' === bp_get_user_meta( $admin_id, 'notification_network_membership_request', true ) ) {
		return;
	}

	$unsubscribe_args = array(
		'user_id'           => $admin_id,
		'notification_type' => 'network-membership-request',
	);

	$request_message = '';
	$requests = groups_get_requests( $args = array(
		'user_id'    => $requesting_user_id,
		'item_id'    => $group_id,
	) );
	if ( $requests ) {
		$request_message = current( $requests )->content;
	}

	$group = groups_get_group( $group_id );
	$args  = array(
		'tokens' => array(
			'admin.id'             => $admin_id,
			'group'                => $group,
			'group.name'           => $group->name,
			'group.id'             => $group_id,
			'group-requests.url'   => esc_url( bp_get_group_permalink( $group ) . 'admin/membership-requests' ),
			'profile.url'          => esc_url( bp_core_get_user_domain( $requesting_user_id ) ),
			'requesting-user.id'   => $requesting_user_id,
			'requesting-user.name' => bp_core_get_user_displayname( $requesting_user_id ),
			'request.message'      => $request_message,
			'unsubscribe'          => esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) ),
		),
	);
	bp_send_email( 'network-membership-request', (int) $admin_id, $args );
}

/**
 * Notify member about their group membership request.
 *
 * @since 1.0.0
 *
 * @param int  $requesting_user_id ID of the user requesting group membership.
 * @param int  $group_id           ID of the group.
 * @param bool $accepted           Optional. Whether the membership request was accepted.
 *                                 Default: true.
 */
function bp_members_membership_request_completed( $requesting_user_id = 0, $group_id = 0, $accepted = true ) {

	// Trigger a BuddyPress Notification.
	if ( bp_is_active( 'notifications' ) ) {

		// What type of acknowledgement.
		$type = ! empty( $accepted ) ? 'membership_request_accepted' : 'membership_request_rejected';

		bp_notifications_add_notification( array(
			'user_id'           => $requesting_user_id,
			'item_id'           => $group_id,
			'component_name'    => buddypress()->groups->id,
			'component_action'  => $type,
		) );
	}

	// Bail if member opted out of receiving this email.
	if ( 'no' === bp_get_user_meta( $requesting_user_id, 'notification_membership_request_completed', true ) ) {
		return;
	}

	$group = groups_get_group( $group_id );
	$args  = array(
		'tokens' => array(
			'group'              => $group,
			'group.id'           => $group_id,
			'group.name'         => $group->name,
			'group.url'          => esc_url( bp_get_group_permalink( $group ) ),
			'requesting-user.id' => $requesting_user_id,
		),
	);

	if ( ! empty( $accepted ) ) {

		$unsubscribe_args = array(
			'user_id'           => $requesting_user_id,
			'notification_type' => 'groups-membership-request-accepted',
		);

		$args['tokens']['unsubscribe'] = esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) );

		bp_send_email( 'groups-membership-request-accepted', (int) $requesting_user_id, $args );

	} else {

		$unsubscribe_args = array(
			'user_id'           => $requesting_user_id,
			'notification_type' => 'groups-membership-request-rejected',
		);

		$args['tokens']['unsubscribe'] = esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) );

		bp_send_email( 'groups-membership-request-rejected', (int) $requesting_user_id, $args );
	}
}
// add_action( 'groups_membership_accepted', 'groups_notification_membership_request_completed', 10, 3 );
// add_action( 'groups_membership_rejected', 'groups_notification_membership_request_completed', 10, 3 );

/**
 * Notify a member they have been invited to a network.
 *
 * @since 1.0.0
 *
 * @param BP_Groups_Group      $group           Group object.
 * @param BP_Groups_Member|int $member          Member object or invited_user_id.
 * @param int                  $inviter_user_id ID of the user who sent the invite.
 */
function bp_members_network_invites( &$group, &$member, $inviter_user_id ) {

	// @todo $inviter_ud may be used for caching, test without it
	$inviter_ud      = bp_core_get_core_userdata( $inviter_user_id );

	if ( $member instanceof BP_Groups_Member ) {
		$invited_user_id = $member->user_id;
	} else if ( is_int( $member ) ) {
		$invited_user_id = $member;
	}

	// Trigger a BuddyPress Notification.
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_add_notification( array(
			'user_id'          => $invited_user_id,
			'item_id'          => $group->id,
			'component_name'   => buddypress()->groups->id,
			'component_action' => 'group_invite',
		) );
	}

	// Bail if member opted out of receiving this email.
	if ( 'no' === bp_get_user_meta( $invited_user_id, 'notification_groups_invite', true ) ) {
		return;
	}

	$invited_link = bp_core_get_user_domain( $invited_user_id ) . bp_get_groups_slug();

	$unsubscribe_args = array(
		'user_id'           => $invited_user_id,
		'notification_type' => 'groups-invitation',
	);

	$invite_message = '';
	$invitations = groups_get_invites( $args = array(
		'user_id'    => $invited_user_id,
		'item_id'    => $group->id,
		'inviter_id' => $inviter_user_id,
	) );
	if ( $invitations ) {
		$invite_message = current( $invitations )->content;
	}

	$args         = array(
		'tokens' => array(
			'group'          => $group,
			'group.url'      => bp_get_group_permalink( $group ),
			'group.name'     => $group->name,
			'inviter.name'   => bp_core_get_userlink( $inviter_user_id, true, false, true ),
			'inviter.url'    => bp_core_get_user_domain( $inviter_user_id ),
			'inviter.id'     => $inviter_user_id,
			'invites.url'    => esc_url( $invited_link . '/invites/' ),
			'invite.message' => $invite_message,
			'unsubscribe'    => esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) ),
		),
	);

	bp_send_email( 'groups-invitation', (int) $invited_user_id, $args );
}

/** Notifications *************************************************************/

/**
 * Format notifications for the Groups component.
 *
 * @since 1.0.0
 *
 * @param string $action            The kind of notification being rendered.
 * @param int    $item_id           The primary item ID.
 * @param int    $secondary_item_id The secondary item ID.
 * @param int    $total_items       The total number of messaging-related notifications
 *                                  waiting for the user.
 * @param string $format            'string' for BuddyBar-compatible notifications; 'array'
 *                                  for WP Toolbar. Default: 'string'.
 * @return string
 */
function bp_members_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {

	switch ( $action ) {
		case 'new_membership_request':
			$group_id = $item_id;
			$requesting_user_id = $secondary_item_id;

			$group = groups_get_group( $group_id );
			$group_link = bp_get_group_permalink( $group );
			$amount = 'single';

			// Set up the string and the filter
			// because different values are passed to the filters,
			// we'll return values inline.
			if ( (int) $total_items > 1 ) {
				$text = sprintf( __( '%1$d new membership requests for the group "%2$s"', 'buddypress' ), (int) $total_items, $group->name );
				$amount = 'multiple';
				$notification_link = $group_link . 'admin/membership-requests/?n=1';

				if ( 'string' == $format ) {

					/**
					 * Filters groups multiple new membership request notification for string format.
					 *
					 * This is a dynamic filter that is dependent on item count and action.
					 * Complete filter - bp_groups_multiple_new_membership_requests_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for request.
					 * @param string $group_link        The permalink for the group.
					 * @param int    $total_items       Total number of membership requests.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . 's_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $total_items, $group->name, $text, $notification_link );
				} else {

					/**
					 * Filters groups multiple new membership request notification for any non-string format.
					 *
					 * This is a dynamic filter that is dependent on item count and action.
					 * Complete filter - bp_groups_multiple_new_membership_requests_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param string $group_link        The permalink for the group.
					 * @param int    $total_items       Total number of membership requests.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . 's_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $total_items, $group->name, $text, $notification_link );
				}
			} else {
				$user_fullname = bp_core_get_user_displayname( $requesting_user_id );
				$text = sprintf( __( '%s requests group membership', 'buddypress' ), $user_fullname );
				$notification_link = $group_link . 'admin/membership-requests/?n=1';

				if ( 'string' == $format ) {

					/**
					 * Filters groups single new membership request notification for string format.
					 *
					 * This is a dynamic filter that is dependent on item count and action.
					 * Complete filter - bp_groups_single_new_membership_request_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for request.
					 * @param string $group_link        The permalink for the group.
					 * @param string $user_fullname     Full name of requesting user.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $user_fullname, $group->name, $text, $notification_link );
				} else {

					/**
					 * Filters groups single new membership request notification for any non-string format.
					 *
					 * This is a dynamic filter that is dependent on item count and action.
					 * Complete filter - bp_groups_single_new_membership_request_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param string $group_link        The permalink for the group.
					 * @param string $user_fullname     Full name of requesting user.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $user_fullname, $group->name, $text, $notification_link );
				}
			}

			break;

		case 'membership_request_accepted':
			$group_id = $item_id;

			$group = groups_get_group( $group_id );
			$group_link = bp_get_group_permalink( $group );
			$amount = 'single';

			if ( (int) $total_items > 1 ) {
				$text = sprintf( __( '%d accepted group membership requests', 'buddypress' ), (int) $total_items, $group->name );
				$amount = 'multiple';
				$notification_link = trailingslashit( bp_loggedin_user_domain() . bp_get_groups_slug() ) . '?n=1';

				if ( 'string' == $format ) {

					/**
					 * Filters multiple accepted group membership requests notification for string format.
					 * Complete filter - bp_groups_multiple_membership_request_accepted_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $total_items       Total number of accepted requests.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $total_items, $group->name, $text, $notification_link );
				} else {

					/**
					 * Filters multiple accepted group membership requests notification for non-string format.
					 * Complete filter - bp_groups_multiple_membership_request_accepted_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification
					 * @param int    $total_items       Total number of accepted requests.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $total_items, $group->name, $text, $notification_link );
				}
			} else {
				$text = sprintf( __( 'Membership for group "%s" accepted', 'buddypress' ), $group->name );
				$filter = 'bp_groups_single_membership_request_accepted_notification';
				$notification_link = $group_link . '?n=1';

				if ( 'string' == $format ) {

					/**
					 * Filters single accepted group membership request notification for string format.
					 * Complete filter - bp_groups_single_membership_request_accepted_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param string $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $group->name, $text, $notification_link );
				} else {

					/**
					 * Filters single accepted group membership request notification for non-string format.
					 * Complete filter - bp_groups_single_membership_request_accepted_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param string $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( $filter, array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $group->name, $text, $notification_link );
				}
			}

			break;

		case 'membership_request_rejected':
			$group_id = $item_id;

			$group = groups_get_group( $group_id );
			$group_link = bp_get_group_permalink( $group );
			$amount = 'single';

			if ( (int) $total_items > 1 ) {
				$text = sprintf( __( '%d rejected group membership requests', 'buddypress' ), (int) $total_items, $group->name );
				$amount = 'multiple';
				$notification_link = trailingslashit( bp_loggedin_user_domain() . bp_get_groups_slug() ) . '?n=1';

				if ( 'string' == $format ) {

					/**
					 * Filters multiple rejected group membership requests notification for string format.
					 * Complete filter - bp_groups_multiple_membership_request_rejected_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $total_items, $group->name );
				} else {

					/**
					 * Filters multiple rejected group membership requests notification for non-string format.
					 * Complete filter - bp_groups_multiple_membership_request_rejected_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $total_items, $group->name, $text, $notification_link );
				}
			} else {
				$text = sprintf( __( 'Membership for group "%s" rejected', 'buddypress' ), $group->name );
				$notification_link = $group_link . '?n=1';

				if ( 'string' == $format ) {

					/**
					 * Filters single rejected group membership requests notification for string format.
					 * Complete filter - bp_groups_single_membership_request_rejected_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $group->name, $text, $notification_link );
				} else {

					/**
					 * Filters single rejected group membership requests notification for non-string format.
					 * Complete filter - bp_groups_single_membership_request_rejected_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $group->name, $text, $notification_link );
				}
			}

			break;

		case 'member_promoted_to_admin':
			$group_id = $item_id;

			$group = groups_get_group( $group_id );
			$group_link = bp_get_group_permalink( $group );
			$amount = 'single';

			if ( (int) $total_items > 1 ) {
				$text = sprintf( __( 'You were promoted to an admin in %d groups', 'buddypress' ), (int) $total_items );
				$amount = 'multiple';
				$notification_link = trailingslashit( bp_loggedin_user_domain() . bp_get_groups_slug() ) . '?n=1';

				if ( 'string' == $format ) {
					/**
					 * Filters multiple promoted to group admin notification for string format.
					 * Complete filter - bp_groups_multiple_member_promoted_to_admin_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $total_items, $text, $notification_link );
				} else {
					/**
					 * Filters multiple promoted to group admin notification for non-string format.
					 * Complete filter - bp_groups_multiple_member_promoted_to_admin_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $total_items, $text, $notification_link );
				}
			} else {
				$text = sprintf( __( 'You were promoted to an admin in the group "%s"', 'buddypress' ), $group->name );
				$notification_link = $group_link . '?n=1';

				if ( 'string' == $format ) {
					/**
					 * Filters single promoted to group admin notification for non-string format.
					 * Complete filter - bp_groups_single_member_promoted_to_admin_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $group->name, $text, $notification_link );
				} else {
					/**
					 * Filters single promoted to group admin notification for non-string format.
					 * Complete filter - bp_groups_single_member_promoted_to_admin_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $group->name, $text, $notification_link );
				}
			}

			break;

		case 'member_promoted_to_mod':
			$group_id = $item_id;

			$group = groups_get_group( $group_id );
			$group_link = bp_get_group_permalink( $group );
			$amount = 'single';

			if ( (int) $total_items > 1 ) {
				$text = sprintf( __( 'You were promoted to a mod in %d groups', 'buddypress' ), (int) $total_items );
				$amount = 'multiple';
				$notification_link = trailingslashit( bp_loggedin_user_domain() . bp_get_groups_slug() ) . '?n=1';

				if ( 'string' == $format ) {
					/**
					 * Filters multiple promoted to group mod notification for string format.
					 * Complete filter - bp_groups_multiple_member_promoted_to_mod_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $total_items, $text, $notification_link );
				} else {
					/**
					 * Filters multiple promoted to group mod notification for non-string format.
					 * Complete filter - bp_groups_multiple_member_promoted_to_mod_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $total_items, $text, $notification_link );
				}
			} else {
				$text = sprintf( __( 'You were promoted to a mod in the group "%s"', 'buddypress' ), $group->name );
				$notification_link = $group_link . '?n=1';

				if ( 'string' == $format ) {
					/**
					 * Filters single promoted to group mod notification for string format.
					 * Complete filter - bp_groups_single_member_promoted_to_mod_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $group->name, $text, $notification_link );
				} else {
					/**
					 * Filters single promoted to group admin notification for non-string format.
					 * Complete filter - bp_groups_single_member_promoted_to_mod_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $group->name, $text, $notification_link );
				}
			}

			break;

		case 'group_invite':
			$group_id = $item_id;
			$group = groups_get_group( $group_id );
			$group_link = bp_get_group_permalink( $group );
			$amount = 'single';

			$notification_link = bp_loggedin_user_domain() . bp_get_groups_slug() . '/invites/?n=1';

			if ( (int) $total_items > 1 ) {
				$text = sprintf( __( 'You have %d new group invitations', 'buddypress' ), (int) $total_items );
				$amount = 'multiple';

				if ( 'string' == $format ) {
					/**
					 * Filters multiple group invitation notification for string format.
					 * Complete filter - bp_groups_multiple_group_invite_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $total_items, $text, $notification_link );
				} else {
					/**
					 * Filters multiple group invitation notification for non-string format.
					 * Complete filter - bp_groups_multiple_group_invite_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $total_items       Total number of rejected requests.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $total_items, $text, $notification_link );
				}
			} else {
				$text = sprintf( __( 'You have an invitation to the group: %s', 'buddypress' ), $group->name );
				$filter = 'bp_groups_single_group_invite_notification';

				if ( 'string' == $format ) {
					/**
					 * Filters single group invitation notification for string format.
					 * Complete filter - bp_groups_single_group_invite_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param string $string            HTML anchor tag for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', '<a href="' . $notification_link . '">' . $text . '</a>', $group_link, $group->name, $text, $notification_link );
				} else {
					/**
					 * Filters single group invitation notification for non-string format.
					 * Complete filter - bp_groups_single_group_invite_notification.
					 *
					 * @since 1.0.0
					 *
					 * @param array  $array             Array holding permalink and content for notification.
					 * @param int    $group_link        The permalink for the group.
					 * @param string $group->name       Name of the group.
					 * @param string $text              Notification content.
					 * @param string $notification_link The permalink for notification.
					 */
					return apply_filters( 'bp_groups_' . $amount . '_' . $action . '_notification', array(
						'link' => $notification_link,
						'text' => $text
					), $group_link, $group->name, $text, $notification_link );
				}
			}

			break;

		default:

			/**
			 * Filters plugin-added group-related custom component_actions.
			 *
			 * @since 2.4.0
			 *
			 * @param string $notification      Null value.
			 * @param int    $item_id           The primary item ID.
			 * @param int    $secondary_item_id The secondary item ID.
			 * @param int    $total_items       The total number of messaging-related notifications
			 *                                  waiting for the user.
			 * @param string $format            'string' for BuddyBar-compatible notifications;
			 *                                  'array' for WP Toolbar.
			 */
			$custom_action_notification = apply_filters( 'bp_groups_' . $action . '_notification', null, $item_id, $secondary_item_id, $total_items, $format );

			if ( ! is_null( $custom_action_notification ) ) {
				return $custom_action_notification;
			}

			break;
	}

	/**
	 * Fires right before returning the formatted group notifications.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action            The type of notification being rendered.
	 * @param int    $item_id           The primary item ID.
	 * @param int    $secondary_item_id The secondary item ID.
	 * @param int    $total_items       Total amount of items to format.
	 */
	do_action( 'groups_format_notifications', $action, $item_id, $secondary_item_id, $total_items );

	return false;
}

/**
 * Mark notifications read when a member's group membership request is granted.
 *
 * @since 2.8.0
 *
 * @param int $user_id  ID of the user.
 * @param int $group_id ID of the group.
 */
function bp_members_accept_request_mark_notifications( $user_id, $group_id ) {
	if ( bp_is_active( 'notifications' ) ) {
		// First null parameter marks read for all admins.
		bp_notifications_mark_notifications_by_item_id( null, $group_id, buddypress()->groups->id, 'new_membership_request', $user_id );
	}
}
// add_action( 'groups_membership_accepted', 'bp_groups_accept_request_mark_notifications', 10, 2 );
// add_action( 'groups_membership_rejected', 'bp_groups_accept_request_mark_notifications', 10, 2 );

/**
 * Render the group settings fields on the Notification Settings page.
 *
 * @since 6.0.0
 */
function bp_members_screen_notification_settings() {

	if ( ! bp_user_can( bp_displayed_user_id(), 'manage_network_membership_requests' ) ) {
		return;
	}

	if ( ! $network_membership_request = bp_get_user_meta( bp_displayed_user_id(), 'notification_network_membership_request', true ) ) {
		$network_membership_request = 'yes';
	}

	?>

	<table class="notification-settings" id="bp-members-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php _ex( 'Members', 'Member component settings on notification settings page', 'buddypress' ) ?></th>
				<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
				<th class="no"><?php _e( 'No', 'buddypress' )?></th>
			</tr>
		</thead>

		<tbody>
			<tr id="bp-members-notification-settings-invitation">
				<td></td>
				<td><?php _ex( 'A user requests membership on this network', 'Member component settings on notification settings page','buddypress' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_network_membership_request]" id="notification_network_membership_request-yes" value="yes" <?php checked( $network_membership_request, 'yes', true ) ?>/><label for="notification_network_membership_request-yes" class="bp-screen-reader-text"><?php
					/* translators: accessibility text */
					_e( 'Yes, send email', 'buddypress' );
				?></label></td>
				<td class="no"><input type="radio" name="notifications[notification_network_membership_request]" id="notification_network_membership_request-no" value="no" <?php checked( $network_membership_request, 'no', true ) ?>/><label for="notification_network_membership_request-no" class="bp-screen-reader-text"><?php
					/* translators: accessibility text */
					_e( 'No, do not send email', 'buddypress' );
				?></label></td>
			</tr>

			<?php

			/**
			 * Fires at the end of the available group settings fields on Notification Settings page.
			 *
			 * @since 6.0.0
			 */
			do_action( 'bp_members_screen_notification_settings' ); ?>

		</tbody>
	</table>

<?php
}
add_action( 'bp_notification_settings', 'bp_members_screen_notification_settings' );
