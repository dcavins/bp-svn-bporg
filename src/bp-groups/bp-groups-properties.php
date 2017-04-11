<?php
/**
 * BuddyPress Groups Statuses.
 *
 * @package BuddyPress
 * @subpackage GroupsStatuses
 * @since 2.9.0
 */

/** Group Statuses and Properties *******************************************/

/**
 * Set up base group statuses.
 *
 * @since 2.9.0
 */
function bp_groups_register_base_group_statuses() {

	$public_group_props = array(
		'join_method'             => 'anyone_can_join',
		'show_group'              => 'anyone',
		'access_group'	          => 'anyone',
		'post_in_activity_stream' => 'member',
		'post_in_forum'           => 'member',
	);
	/**
	 * Filters the basic properties of the "public" group status.
	 *
	 * @since 2.9.0
	 *
	 * @param array $public_group_props Array of properties.
	 */
	$public_group_props = apply_filters( 'bp_groups_public_group_status_properties', $public_group_props );

	bp_groups_register_group_status( 'public', array(
		'display_name'    => _x( 'Public', 'Group status name', 'buddypress' ),
		'properties'      => $public_group_props,
		'fallback_status' => 'none',
		'priority'        => 10,
	) );

	$private_group_props = array(
		'join_method'             => 'accepts_membership_requests',
		'show_group'              => 'anyone',
		'access_group'            => 'member',
		'post_in_activity_stream' => 'member',
		'post_in_forum'           => 'member',
	);
	/**
	 * Filters the basic properties of the "public" group status.
	 *
	 * @since 2.9.0
	 *
	 * @param array $private_group_props Array of properties.
	 */
	$private_group_props = apply_filters( 'bp_groups_private_group_status_properties', $private_group_props );

	bp_groups_register_group_status( 'private', array(
		'display_name'    => _x( 'Private', 'Group status name', 'buddypress' ),
		'properties'      => $private_group_props,
		'fallback_status' => 'none',
		'priority'        => 50,
	) );

	$hidden_group_props = array(
		'join_method'             => 'invitation_only',
		'show_group'              => array( 'member', 'invited' ), // Invitees must be able to know about hidden groups.
		'access_group'	          => 'member',
		'post_in_activity_stream' => 'member',
		'post_in_forum'           => 'member',
	);
	/**
	 * Filters the basic properties of the "public" group status.
	 *
	 * @since 2.9.0
	 *
	 * @param array $private_group_props Array of properties.
	 */
	$hidden_group_props = apply_filters( 'bp_groups_hidden_group_status_properties', $hidden_group_props );

	bp_groups_register_group_status( 'hidden', array(
		'display_name'    => _x( 'Hidden', 'Group status name', 'buddypress' ),
		'properties'      => $hidden_group_props,
		'fallback_status' => 'none',
		'priority'        => 90,
	) );
}
add_action( 'bp_groups_register_group_statuses', 'bp_groups_register_base_group_statuses', 8 );

/**
 * Register a group status.
 *
 * @since 2.9.0
 *
 * @param string $group_status Unique string identifier for the group status.
 * @param array  $args {
 *     Array of arguments describing the group type.
 *
 *         @type string $name            Displayed name.
 *         @type array  $properties    Array of properties. See
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
		'properties'      => array(),
		'fallback_status' => 'public',
	), 'register_group_status' );

	$group_status = sanitize_key( $group_status );

	/**
	 * Filters the list of illegal group status names.
	 *
	 * - 'any' is a special pseudo-type.
	 *
	 * @since 2.9.0
	 *
	 * @param array $illegal_names Array of illegal names.
	 */
	$illegal_names = apply_filters( 'bp_group_status_illegal_names', array( 'any' ) );
	if ( in_array( $group_status, $illegal_names, true ) ) {
		return new WP_Error( 'bp_group_status_illegal_name', __( 'You may not register a group status with this name.', 'buddypress' ), $group_status );
	}

	// Use the fallback status to fill out the status.
	if ( 'none' != $r['fallback_status'] ) {
		$fallback_props = bp_groups_get_group_status_properties( $r['fallback_status'] );

		if ( $fallback_props ) {
			$r['properties'] = bp_parse_args( $r['properties'], $fallback_props, 'register_group_status_parse_properties' );
		}
	}

	$bp->groups->statuses[ $group_status ] = $status = (object) $r;

	/**
	 * Fires after a group status is registered.
	 *
	 * @since 2.9.0
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
 * @since 2.9.0
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
	 * @since 2.9.0
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
 * @since 2.9.0
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
	$bp->groups->statuses[$status]->properties[$cap_name] = $value;
	return true;
}

/**
 * Edit a group capability for a specific status.
 *
 * Edit an existing group capability by changing the value.
 *
 * @since 2.9.0
 *
 * @param string $status The name of the status to edit.
 * @param string $cap    Capability to edit.
 * @param string $value  New value of the capability.
 *
 * @return bool True if set, false otherwise.
 */
function bp_groups_edit_group_status_capability( $status, $cap, $value = true ) {
	$bp = buddypress();
	if ( ! isset( $bp->groups->statuses[$status]->properties[$cap] ) ) {
		return false;
	}
	$bp->groups->statuses[$status]->properties[$cap] = $value;
	return true;
}

/**
 * Get a list of all registered group status objects.
 *
 * @since 2.9.0
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
 * @since 2.9.0
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
 * Retrieve a group status object's properties by the status name.
 *
 * @since 2.9.0
 *
 * @param string $group_status The name of the group status.
 *
 * @return array The properties array of the group status object.
 */
function bp_groups_get_group_status_properties( $group_status ) {

	$status = bp_groups_get_group_status_object( $group_status );

	if ( empty( $status->properties ) ) {
		return array();
	}

	return $status->properties;
}

/**
 * Add a property to an existing status.
 *
 * Edit an existing status property by changing the value.
 *
 * @since 2.9.0
 *
 * @param string $status The name of the status to edit.
 * @param string $prop    Property to edit.
 * @param string $value  New value of the property.
 *
 * @return array $properties Properties array for specified group.
 */
function bp_groups_get_group_properties( $group ) {
	// Have the group's properties been populated?
	if ( is_object( $group ) && ! isset( $group->properties ) ) {
		$group = groups_get_group( array( 'group_id' => $group->id, 'populate_extras' => true ) );
	} elseif ( is_int( $group ) ) {
		$group = groups_get_group( array( 'group_id' => (int) $group, 'populate_extras' => true ) );
	}

	if ( ! isset( $group->properties ) ) {
		return false;
	}

	if ( ! empty( $group->properties ) ) {
		return $group->properties;
	} else {
		return false;
	}
}

/**
 * Check whether a group status has a value for a capability.
 *
 * If the capability has a non-falsey value, it is returned, so this funciton
 * can be used to check and fetch capability values. To check the properties
 * of a specific group, use `bp_groups_group_has_property()` below.
 *
 * @since 2.9.0
 *
 * @param string $status The name of the status to check.
 * @param string $prop   Capability to check.
 *
 * @return mixed|bool Returns the value stored for the capability if set, false otherwise.
 */
function bp_groups_group_status_has_property( $status, $prop ) {
	$bp = buddypress();

	if ( ! isset( $bp->groups->statuses[$status]->properties ) ) {
		return false;
	}

	/**
	 * Filter which properties are associated with a group status.
	 *
	 * @since 2.9.0
	 *
	 * @param array  $properties Array of properties for this status.
	 * @param string $status     Status name.
	 * @param string $prop       Capability name.
	 */
	$properties = apply_filters( 'bp_groups_group_status_has_property', $bp->groups->statuses[$status]->properties, $status, $prop );

	if ( ! empty( $properties[$prop] ) ) {
		return $properties[$prop];
	} else {
		return false;
	}
}

/**
 * Check whether a group has a value for a property.
 *
 * If the property has a non-falsey value, it is returned, so this function
 * can be used to check and fetch property values. To check the properties
 * of a status generally, use `bp_groups_group_status_has_property()` above.
 * To filter a particular group's properties, use the
 * `bp_groups_group_object_set_properties` filter hook.
 *
 * @since 2.9.0
 *
 * @param object|int $group Group object or id of the group to check.
 * @param string     $prop  Property to check.
 *
 * @return mixed|bool Returns the value stored for the property if set, false otherwise.
 */
function bp_groups_group_has_property( $group, $prop ) {
	// Have the group's properties been populated?
	if ( is_object( $group ) && ! isset( $group->properties ) ) {
		$group = groups_get_group( array( 'group_id' => $group->id, 'populate_extras' => true ) );
	} elseif ( is_int( $group ) ) {
		$group = groups_get_group( array( 'group_id' => (int) $group, 'populate_extras' => true ) );
	}

	if ( ! isset( $group->properties ) ) {
		return false;
	}

	if ( ! empty( $group->properties[$prop] ) ) {
		return $group->properties[$prop];
	} else {
		return false;
	}
}

/**
 * User-friendly descriptions for each group property.
 *
 * Used on the group settings and create screens to describe the properties
 * of each group status.
 *
 * @since 2.9.0
 *
 * @param string $prop  Property to check.
 * @param mixed  $value The value of the property we'd like to describe.
 *
 * @return mixed|bool Returns the value stored for the property if set, false otherwise.
 */
function bp_groups_group_properties_description( $prop, $value ) {
	$retval = '';

	switch ( $prop ) {
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
			 * Provide the group property description for custom properties.
			 *
			 * @since 2.9.0
			 *
			 * @param string $retval The description of the property and value combination.
			 * @param string $prop   Property name.
			 * @param string $name   Value for the property.
			 */
			$retval = apply_filters( 'bp_groups_group_custom_properties_description', $retval, $prop, $value );
			break;
	}

	return $retval;
}

/**
 * Check whether a user meets an access condition for a group.
 *
 * Used to calculate whether the group is visible and accessible to the user.
 *
 * @since 2.9.0
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
function bp_groups_user_meets_access_condition( $access_condition, $group_id = 0, $user_id = false ) {
	if ( ! $group_id ) {
		$group_id = bp_get_current_group_id();
	}
	if ( ! $group_id ) {
		return false;
	}
	if ( false === $user_id ) {
		$user_id = bp_loggedin_user_id();
	}

	if ( bp_user_can( $user_id, 'bp_moderate' ) ) {
		return true;
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
 * @since 2.9.0
 */
function bp_groups_cache_invalidate_last_changed_incrementor() {
	wp_cache_delete( 'last_changed', 'bp_groups' );
}
// @TODO: This is updated on every group creation, deletion, setting update, and membership change.
// Is this a bad caching strategy?
// add_action( 'groups_created_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_settings_updated', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_delete_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_join_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_leave_group', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_invite_user', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_uninvite_user', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_accept_invite', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// add_action( 'groups_reject_invite', 'bp_groups_cache_invalidate_last_changed_incrementor' );
// More, too. Promotions, etc.

/**
 * Filter the bp_user_can value to determine what the user can do
 * with regards to a specific group.
 *
 * @since 2.9.0
 */
function bp_groups_user_can_filter( $retval, $user_id, $capability, $site_id, $args ) {
	switch ( $capability ) {
		case 'groups_join_group':
			// Return early if the user isn't logged in or the group ID is unknown.
			if ( ! $user_id || ! isset( $args['group_id'] ) ) {
				break;
			}

			// The group must allow joining, and the user should not currently be a member.
			$group = groups_get_group( (int) $args['group_id'] );
			if ( 'anyone_can_join' == bp_groups_group_has_property( $group, 'join_method' ) && ! groups_is_user_member( $user_id, $group->id ) ) {
				$retval = true;
			}

			break;

		case 'groups_request_membership':
			// Return early if the user isn't logged in or the group ID is unknown.
			if ( ! $user_id || ! isset( $args['group_id'] ) ) {
				break;
			}

			// The group must accept membership requests, and the user should not currently be a member.
			$group = groups_get_group( (int) $args['group_id'] );
			if ( 'accepts_membership_requests' == bp_groups_group_has_property( $group, 'join_method' ) && ! groups_is_user_member( $user_id, $group->id ) ) {
				$retval = true;
			}

			break;

		case 'groups_access_group':
			// Return early if the group ID is unknown.
			if ( ! isset( $args['group_id'] ) ) {
				break;
			}

			$group = groups_get_group( (int) $args['group_id'] );

			// If the check is for the logged-in user, use the BP_Groups_Group property.
			if ( $user_id == bp_loggedin_user_id() ) {
				if ( $group->user_has_access ) {
					$retval = true;
				}

			/*
			 * If the check is for a specified user who is not the logged-in user
			 * run the check manually.
			 */
			} else {

				// Parse multiple visibility conditions into an array.
				$access_conditions = bp_groups_group_has_property( $group, 'access_group' );
				if ( ! is_array( $access_conditions ) ) {
					$access_conditions = preg_split( '/[\s,]+/', $access_conditions );
				}

				// If the specified user meets at least one condition, allow access.
				foreach ( $access_conditions as $access_condition ) {
					if ( bp_groups_user_meets_access_condition( $access_condition, (int) $args['group_id'], $user_id ) ) {
						$retval = true;
						break;
					}
				}

			}

			break;

		case 'groups_see_group':
			// Return early if the group ID is unknown.
			if ( ! isset( $args['group_id'] ) ) {
				break;
			}

			$group = groups_get_group( (int) $args['group_id'] );

			// If the check is for the logged-in user, use the BP_Groups_Group property.
			if ( $user_id == bp_loggedin_user_id() ) {
				if ( $group->is_visible ) {
					$retval = true;
				}

			/*
			 * If the check is for a specified user who is not the logged-in user
			 * run the check manually.
			 */
			} else {

				// Parse multiple visibility conditions into an array.
				$access_conditions = bp_groups_group_has_property( $group, 'show_group' );
				if ( ! is_array( $access_conditions ) ) {
					$access_conditions = preg_split( '/[\s,]+/', $access_conditions );
				}

				// If the specified user meets at least one condition, allow access.
				foreach ( $access_conditions as $access_condition ) {
					if ( bp_groups_user_meets_access_condition( $access_condition, (int) $args['group_id'], $user_id ) ) {
						$retval = true;
						break;
					}
				}

			}

			break;
	}

	return $retval;

}
add_filter( 'bp_user_can', 'bp_groups_user_can_filter', 10, 5 );
