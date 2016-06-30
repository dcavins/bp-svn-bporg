<?php
/**
 * BuddyPress Groups BP_Groups_Status class
 *
 * @package BuddyPress
 * @subpackage GroupsClasses
 * @since 2.7.0
 */

/**
 * Class used to extend the group status API.
 *
 * @since 2.7.0
 */
class BP_Groups_Status {
	/**
	 * Status name.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var string
	 */
	public $name;

	/**
	 * List of capabilities the status contains.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var array
	 */
	public $capabilities;

	/**
	 * Constructor - Set up object properties.
	 *
	 * The list of capabilities, must have the key as the name of the capability
	 * and the value a boolean of whether it is granted to the role.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param array $capabilities List of capabilities.
	 */
	public function __construct( $status, $capabilities ) {
		$this->name = $status;
		$this->capabilities = $capabilities;
	}

	/**
	 * Assign group status a capability.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @param bool $grant Whether role has capability privilege.
	 */
	public function add_cap( $cap, $grant = true ) {
		$this->capabilities[$cap] = $grant;
		bp_groups_statuses()->add_cap( $this->name, $cap, $grant );
	}

	/**
	 * Remove capability from group status.
	 *
	 * This is a container for {@link WP_Roles::remove_cap()} to remove the
	 * capability from the role. That is to say, that {@link
	 * WP_Roles::remove_cap()} implements the functionality, but it also makes
	 * sense to use this class, because you don't need to enter the role name.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $cap ) {
		unset( $this->capabilities[$cap] );
		bp_groups_statuses()->remove_cap( $this->name, $cap );
	}

	/**
	 * Determines whether the group status has the given capability.
	 *
	 * The capabilities is passed through the {@see 'role_has_cap'} filter.
	 * The first parameter for the hook is the list of capabilities the class
	 * has assigned. The second parameter is the capability name to look for.
	 * The third and final parameter for the hook is the role name.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @return bool True if the role has the given capability. False otherwise.
	 */
	public function has_cap( $cap ) {
		/**
		 * Filter which capabilities a role has.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $capabilities Array of role capabilities.
		 * @param string $cap          Capability name.
		 * @param string $name         Role name.
		 */
		$capabilities = apply_filters( 'group_has_cap', $this->capabilities, $cap, $this->name );

		if ( ! empty( $capabilities[$cap] ) ) {
			return $capabilities[$cap];
		} else {
			return false;
		}
	}

}
