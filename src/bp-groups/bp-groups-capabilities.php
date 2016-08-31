<?php
/**
 * BuddyPress Groups Capabilities.
 *
 * @package BuddyPress
 * @subpackage GroupsCapabilities
 * @since 2.7.0
 */

/** Group Statuses and Capabilities *******************************************/

/**
 * Set up base group statuses.
 *
 * @since 2.7.0
 */
function bp_groups_register_base_group_statuses() {

	$public_group_caps = array(
		'join_method'    => 'anyone_can_join',
		'show_group'     => 'anyone',
		'access_group'	 => 'anyone',
		'post_in_forum'  => 'anyone',
	);
	/**
	 * Filters the basic capabilities of the "public" group status.
	 *
	 * @since 2.7.0
	 *
	 * @param array $public_group_caps Array of capabilities.
	 */
	$public_group_caps = apply_filters( 'bp_groups_public_group_status_caps', $public_group_caps );

	bp_groups_register_group_status( 'public', array(
		'display_name'    => _x( 'Public', 'Group status name', 'buddypress' ),
		'capabilities'    => $public_group_caps,
		'fallback_status' => 'none',
		'priority'        => 10,
	) );

	$private_group_caps = array(
		'join_method'   => 'accepts_membership_requests',
		'show_group'    => 'anyone',
		'access_group'  => 'member',
		'post_in_forum' => 'member',
	);
	/**
	 * Filters the basic capabilities of the "public" group status.
	 *
	 * @since 2.7.0
	 *
	 * @param array $private_group_caps Array of capabilities.
	 */
	$private_group_caps = apply_filters( 'bp_groups_private_group_status_caps', $private_group_caps );

	bp_groups_register_group_status( 'private', array(
		'display_name'    => _x( 'Private', 'Group status name', 'buddypress' ),
		'capabilities'    => $private_group_caps,
		'fallback_status' => 'none',
		'priority'        => 50,
	) );

	$hidden_group_caps = array(
		'join_method'   => 'invitation_only',
		'show_group'    => array( 'member', 'invited' ), // Invitees must be able to know about hidden groups.
		'access_group'	=> 'member',
		'post_in_forum' => 'member',
	);
	/**
	 * Filters the basic capabilities of the "public" group status.
	 *
	 * @since 2.7.0
	 *
	 * @param array $private_group_caps Array of capabilities.
	 */
	$hidden_group_caps = apply_filters( 'bp_groups_hidden_group_status_caps', $hidden_group_caps );

	bp_groups_register_group_status( 'hidden', array(
		'display_name'    => _x( 'Hidden', 'Group status name', 'buddypress' ),
		'capabilities'    => $hidden_group_caps,
		'fallback_status' => 'none',
		'priority'        => 90,
	) );
}
add_action( 'bp_groups_register_group_statuses', 'bp_groups_register_base_group_statuses', 8 );

/**
 * Register a group status.
 *
 * @since 2.7.0
 *
 * @param string $group_status Unique string identifier for the group status.
 * @param array  $args {
 *     Array of arguments describing the group type.
 *
 *         @type string $name            Displayed name.
 *         @type array  $capabilities    Array of capabilities. See
 *                                       `bp_groups_register_base_group_statuses()`
 *                                       for commons capability sets.
 *         @type string $fallback_status If a capability isn't set, which typical
 *                                       status is most similar to the new status?
 *                                       Specify 'public', 'private', or 'hidden'.
 *         @type int    $priority        Order the capability among the default
 *                                       statuses: 'public' has a priority of 10,
 *                                       'private' has a priority of 50, and
 *                                       'hidden' has a priority of 90.
 *
 * }
 * @return object|WP_Error Group type object on success, WP_Error object on failure.
 */
function bp_groups_register_group_status( $group_status, $args = array() ) {
	$bp = buddypress();

	if ( isset( $bp->groups->statuses[ $group_status ] ) ) {
		return new WP_Error( 'bp_group_status_exists', __( 'Group status already exists.', 'buddypress' ), $group_status );
	}

	$r = bp_parse_args( $args, array(
		'name'            => $group_status,
		'display_name'    => ucfirst( $group_status ),
		'capabilities'    => array(),
		'fallback_status' => 'public',
	), 'register_group_status' );

	$group_status = sanitize_key( $group_status );

	/**
	 * Filters the list of illegal group status names.
	 *
	 * - 'any' is a special pseudo-type.
	 *
	 * @since 2.7.0
	 *
	 * @param array $illegal_names Array of illegal names.
	 */
	$illegal_names = apply_filters( 'bp_group_status_illegal_names', array( 'any' ) );
	if ( in_array( $group_status, $illegal_names, true ) ) {
		return new WP_Error( 'bp_group_status_illegal_name', __( 'You may not register a group status with this name.', 'buddypress' ), $group_status );
	}

	// Use the fallback status to fill out the status.
	if ( 'none' != $r['fallback_status'] ) {
		$fallback_caps = bp_groups_get_group_status_capabilities( $r['fallback_status'] );

		if ( $fallback_caps ) {
			$r['capabilities'] = bp_parse_args( $r['capabilities'], $fallback_caps, 'register_group_status_parse_caps' );
		}
	}

	$bp->groups->statuses[ $group_status ] = $status = (object) $r;

	/**
	 * Fires after a group status is registered.
	 *
	 * @since 2.7.0
	 *
	 * @param string $group_status Group status identifier.
	 * @param object $status       Group status object.
	 */
	do_action( 'bp_groups_register_group_status', $group_status, $status );

	return $status;
}

/**
 * Deregister a group status.
 *
 * @since 2.7.0
 *
 * @param string $group_status Unique string identifier for the group status.
 *
 * @return bool|WP_Error if the group status isn't registered.
 */
function bp_groups_deregister_group_status( $group_status ) {
	$bp = buddypress();
	$retval = false;

	if ( ! isset( $bp->groups->statuses[ $group_status ] ) ) {
		return new WP_Error( 'bp_group_status_does_not_exist', __( 'Group status does not exist.', 'buddypress' ), $group_status );
	} else {
		$old_status_object = $bp->groups->statuses[ $group_status ];
		unset( $bp->groups->statuses[ $group_status ] );
		$retval = true;
	}

	/**
	 * Fires after a group status is deregistered.
	 *
	 * @since 2.7.0
	 *
	 * @param string $group_status Group status identifier.
	 * @param object $status       Removed group status object.
	 */
	do_action( 'bp_groups_deregister_group_status', $group_status, $old_status_object );

	return $retval;
}

/**
 * Add a group capability to an existing status.
 *
 * @since 2.7.0
 *
 * @param string $status The name of the status to edit.
 * @param string $cap    Capability to add.
 * @param string $value  New value of the capability.
 *
 * @return bool True if set, false otherwise.
 */
function bp_groups_add_group_status_capability( $status, $cap, $value = true ) {
	$bp = buddypress();
	if ( empty( $status ) || empty( $cap ) || ! isset( $bp->groups->statuses[$status] ) ) {
		return false;
	}
	$cap_name = sanitize_key( $cap );
	$bp->groups->statuses[$status]->capabilities[$cap_name] = $value;
	return true;
}

/**
 * Edit a group capability for a specific status.
 *
 * Edit an existing group capability by changing the value.
 *
 * @since 2.7.0
 *
 * @param string $status The name of the status to edit.
 * @param string $cap    Capability to edit.
 * @param string $value  New value of the capability.
 *
 * @return bool True if set, false otherwise.
 */
function bp_groups_edit_group_status_capability( $status, $cap, $value = true ) {
	$bp = buddypress();
	if ( empty( $status )
		|| empty( $cap )
		|| ! isset( $bp->groups->statuses[$status] )
		|| ! isset( $bp->groups->statuses[$status]->capabilities[$cap] ) ) {
		return false;
	}
	$bp->groups->statuses[$status]->capabilities[$cap] = $value;
	return true;
}

/**
 * Get a list of all registered group status objects.
 *
 * @since 2.7.0
 *
 * @see bp_groups_register_group_status() for accepted arguments.
 *
 * @param array|string $args     Optional. An array of key => value arguments to match against
 *                               the group type objects. Default empty array.
 * @param string       $output   Optional. The type of output to return. Accepts 'names'
 *                               or 'objects'. Default 'names'.
 * @param string       $operator Optional. The logical operation to perform. 'or' means only one
 *                               element from the array needs to match; 'and' means all elements
 *                               must match. Accepts 'or' or 'and'. Default 'and'.
 *
 * @return array       $types    A list of groups status names or objects.
 */
function bp_groups_get_group_statuses( $args = array(), $output = 'names', $operator = 'and' ) {
	$statuses = buddypress()->groups->statuses;

	$statuses = wp_filter_object_list( $statuses, $args, $operator );

	// Sort by status "priority".
	$statuses = bp_sort_by_key( $statuses, 'priority', 'num', true );

	/**
	 * Filters the array of group status objects.
	 *
	 * This filter is run before the $output filter has been applied, so that
	 * filtering functions have access to the entire group status objects.
	 *
	 * @since 2.6.0
	 *
	 * @param array  $statuses  Group status objects, keyed by name.
	 * @param array  $args      Array of key=>value arguments for filtering.
	 * @param string $operator  'or' to match any of $args, 'and' to require all.
	 */
	$statuses = apply_filters( 'bp_groups_get_group_statuses', $statuses, $args, $operator );

	if ( 'names' === $output ) {
		$statuses = wp_list_pluck( $statuses, 'name' );
	}

	return $statuses;
}

/**
 * Retrieve a group status object by name.
 *
 * @since 2.7.0
 *
 * @param string $group_status The name of the group status.
 *
 * @return object A group status object.
 */
function bp_groups_get_group_status_object( $group_status ) {
	$statuses = bp_groups_get_group_statuses( array(), 'objects' );

	if ( empty( $statuses[ $group_status ] ) ) {
		return null;
	}

	return $statuses[ $group_status ];
}

/**
 * Retrieve a group status object's capabilities by the status name.
 *
 * @since 2.7.0
 *
 * @param string $group_status The name of the group status.
 *
 * @return array The capabilities array of the group status object.
 */
function bp_groups_get_group_status_capabilities( $group_status ) {

	$status = bp_groups_get_group_status_object( $group_status );

	if ( empty( $status->capabilities ) ) {
		return null;
	}

	return $status->capabilities;
}

/**
 * Add a group capability to an existing status.
 *
 * Edit an existing group capability by changing the value.
 *
 * @since 2.7.0
 *
 * @param string $status The name of the status to edit.
 * @param string $cap    Capability to edit.
 * @param string $value  New value of the capability.
 *
 * @return array $capabilities Capabilities array for specified group.
 */
function bp_groups_get_group_capabilities( $group ) {
	// Have the group's capabilities been populated?
	if ( is_object( $group ) && ! isset( $group->capabilities ) ) {
		$group = groups_get_group( array( 'group_id' => $group->id, 'populate_extras' => true ) );
	} elseif ( is_int( $group ) ) {
		$group = groups_get_group( array( 'group_id' => (int) $group, 'populate_extras' => true ) );
	}

	if ( ! isset( $group->capabilities ) ) {
		return false;
	}

	if ( ! empty( $group->capabilities ) ) {
		return $group->capabilities;
	} else {
		return false;
	}
}

/**
 * Check whether a group status has a value for a capability.
 *
 * If the capability has a non-falsey value, it is returned, so this funciton
 * can be used to check and fetch capability values. To check the capabilities
 * of a specific group, use `bp_groups_group_has_cap()` below.
 *
 * @since 2.7.0
 *
 * @param string $status The name of the status to check.
 * @param string $cap    Capability to check.
 *
 * @return mixed|bool Returns the value stored for the capability if set, false otherwise.
 */
function bp_groups_group_status_has_cap( $status, $cap ) {
	$bp = buddypress();

	if ( ! isset( $bp->groups->statuses[$status] ) || ! isset( $bp->groups->statuses[$status]->capabilities ) ) {
		return false;
	}

	/**
	 * Filter which capabilities are associated with a group status.
	 *
	 * @since 2.7.0
	 *
	 * @param array  $capabilities Array of capabilities for this status.
	 * @param string $status       Status name.
	 * @param string $cap          Capability name.
	 */
	$capabilities = apply_filters( 'bp_groups_group_status_has_cap', $bp->groups->statuses[$status]->capabilities, $status, $cap );

	if ( ! empty( $capabilities[$cap] ) ) {
		return $capabilities[$cap];
	} else {
		return false;
	}
}

/**
 * Check whether a group has a value for a capability.
 *
 * If the capability has a non-falsey value, it is returned, so this funciton
 * can be used to check and fetch capability values. To check the capabilities
 * of a status generally, use `bp_groups_group_status_has_cap()` above.
 * To filter a particular group's capabilities, use the
 * `bp_groups_group_object_set_caps` filter hook.
 *
 * @since 2.7.0
 *
 * @param object|int $group Group object or id of the group to check.
 * @param string     $cap   Capability to check.
 *
 * @return mixed|bool Returns the value stored for the capability if set, false otherwise.
 */
function bp_groups_group_has_cap( $group, $cap ) {
	// Have the group's capabilities been populated?
	if ( is_object( $group ) && ! isset( $group->capabilities ) ) {
		$group = groups_get_group( array( 'group_id' => $group->id, 'populate_extras' => true ) );
	} elseif ( is_int( $group ) ) {
		$group = groups_get_group( array( 'group_id' => (int) $group, 'populate_extras' => true ) );
	}

	if ( ! isset( $group->capabilities ) ) {
		return false;
	}

	if ( ! empty( $group->capabilities[$cap] ) ) {
		return $group->capabilities[$cap];
	} else {
		return false;
	}
}

/**
 * User-friendly descriptions for each group capability.
 *
 * Used on the group settings and create screens to describe the capabilities
 * of each group status.
 *
 * @since 2.7.0
 *
 * @param string $cap   Capability to check.
 * @param mixed  $value The value of the capability we'd like to describe.
 *
 * @return mixed|bool Returns the value stored for the capability if set, false otherwise.
 */
function bp_groups_group_capabilities_description( $cap, $value ) {
	$retval = '';

	switch ( $cap ) {
		case 'join_method':
			if ( 'anyone_can_join' == $value ) {
				$retval = __( 'Any site member can join this group.', 'buddypress' );
			} elseif ( 'accepts_membership_requests' == $value ) {
				$retval = __( 'Only users who request membership and are accepted can join the group.', 'buddypress' );
			} elseif ( 'invitation_only' == $value ) {
				$retval = __( 'Only users who are invited can join the group.', 'buddypress' );
			}
			break;
		case 'show_group' :
			// @TODO: This could be an array of options.
			if ( 'anyone' == $value ) {
				$retval = __( 'This group will be listed in the groups directory and in search results.', 'buddypress' );
			} else {
				$retval = __( 'This group will not be listed in the groups directory or search results.', 'buddypress' );
			}
			break;
		case 'access_group' :
			// @TODO: This could be an array of options.
			if ( 'anyone' == $value ) {
				$retval = __( 'Group content and activity will be visible to any visitor to the site.', 'buddypress' );
			} elseif ( 'loggedin' == $value ) {
				$retval = __( 'Group content and activity will be visible to any site member.', 'buddypress' );
			} else {
				$retval = __( 'Group content and activity will only be visible to members of the group.', 'buddypress' );
			}
			break;
		default:
			/**
			 * Provide the group capability description for custom capabilities.
			 *
			 * @since 2.7.0
			 *
			 * @param string $retval The description of the capability and value combination.
			 * @param string $cap    Capability name.
			 * @param string $name   Value for the capability.
			 */
			$retval = apply_filters( 'bp_groups_group_custom_capabilities_description', $retval, $cap, $value );
			break;
	}

	return $retval;
}

/**
 * Check whether a user meets an access condition for a group.
 *
 * Used to calculate whether the group is visible and accessible to the user.
 *
 * @since 2.7.0
 *
 * @param string $access_condition 'anyone', 'loggedin', 'member',
 *                                 'mod', 'admin', or 'noone'.
 *                                 Defaults to the current group.
 * @param int    $group_id         Optional. ID of the group to check.
 * @param int    $user_id          Optional. ID of the user to check.
 *                                 Defaults to the current user.
 *
 * @return bool
 */
function bp_groups_user_meets_access_condition( $access_condition, $group_id = 0, $user_id = 0 ) {
	if ( ! $group_id ) {
		$group_id = bp_get_current_group_id();
	}
	if ( ! $group_id ) {
		return false;
	}
	if ( ! $user_id ) {
		$user_id = bp_loggedin_user_id();
	}

	switch ( $access_condition ) {
		case 'admin' :
			$meets_condition = groups_is_user_admin( $user_id, $group_id );
			break;

		case 'mod' :
			$meets_condition = groups_is_user_mod( $user_id, $group_id );
			break;

		case 'member' :
			$meets_condition = groups_is_user_member( $user_id, $group_id );
			break;

		case 'invited' :
			$meets_condition = groups_check_user_has_invite( $user_id, $group_id );
			break;

		case 'loggedin' :
			$meets_condition = is_user_logged_in();
			break;

		case 'noone' :
			$meets_condition = false;
			break;

		case 'anyone' :
		default :
			$meets_condition = true;
			break;
	}

	return (bool) $meets_condition;
}

/**
 * Reset the 'last_changed' cache incrementor when groups are updated.
 *
 * @since 2.7.0
 */
function bp_groups_cache_invalidate_last_changed_incrementor() {
	wp_cache_delete( 'last_changed', 'bp_groups' );
}
// @TODO: This is updated on every group creation, deletion, setting update, and membership change.
// Is this a bad caching strategy?
add_action( 'groups_created_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_settings_updated', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_delete_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_join_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_leave_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_invite_user', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_uninvite_user', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_accept_invite', 'bp_groups_cache_invalidate_last_changed_incrementor' );
add_action( 'groups_reject_invite', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// More, too. Promotions, etc.

