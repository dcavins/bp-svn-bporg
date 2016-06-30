<?php
/**
 * BuddyPress Groups BP_Groups_Statuses class
 *
 * @package BuddyPress
 * @subpackage GroupsClasses
 * @since 2.7.0
 */

/**
 * Class used to implement a group statuses API.
 *
 * The structure of group statuses is stored as follows.
 *
 *     array (
 *    		'statusname' => array (
 *    			'name' => 'statusname',
 *    			'capabilities' => array()
 *    		)
 *     )
 *
 * @since 2.7.0
 */
class BP_Groups_Statuses {
	/**
	 * List of statuses and capabilities.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var array
	 */
	public $statuses;

	/**
	 * List of the status objects.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var array
	 */
	public $status_objects = array();

	/**
	 * List of status names.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var array
	 */
	public $status_names = array();

	/**
	 * Option name for storing role list.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var string
	 */
	public $status_key;

	/**
	 * Whether to use the database for retrieval and storage.
	 *
	 * @since 2.7.0
	 * @access public
	 * @var bool
	 */
	public $use_db = true;

	/**
	 * Constructor
	 *
	 * @since 2.7.0
	 */
	public function __construct() {
		$this->_init();
	}

	/**
	 * Make private/protected methods readable for backwards compatibility.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param callable $name      Method to call.
	 * @param array    $arguments Arguments to pass when calling.
	 * @return mixed|false Return value of the callback, false otherwise.
	 */
	public function __call( $name, $arguments ) {
		if ( '_init' === $name ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}

	/**
	 * Set up the object properties.
	 *
	 * The role key is set to the current prefix for the $wpdb object with
	 * 'user_roles' appended. If the $wp_user_roles global is set, then it will
	 * be used and the role option will not be updated or used.
	 *
	 * @since 2.1.0
	 * @access protected
	 *
	 * @global wpdb  $wpdb          WordPress database abstraction object.
	 * @global array $wp_user_roles Used to set the 'roles' property value.
	 */
	protected function _init() {
		global $wpdb;
		$bp = buddypress();

		// TODO: Use caching here instead of a global.
		if ( ! empty( $bp->groups->statuses ) ) {
			$this->statuses = $bp->groups->statuses;
		} else {
			$this->statuses = get_option( $this->status_key );
		}

		if ( empty( $this->statuses ) ) {
			return;
		}

		$this->status_objects = array();
		$this->status_names =  array();
		foreach ( array_keys( $this->statuses ) as $status ) {
			$this->status_objects[$status] = new BP_Group_Status( $status, $this->statuses[$status]['capabilities'] );
			$this->status_names[$status] = $this->statuses[$status]['name'];
		}
	}

	/**
	 * Add role name with capabilities to list.
	 *
	 * Updates the list of roles, if the role doesn't already exist.
	 *
	 * The capabilities are defined in the following format `array( 'read' => true );`
	 * To explicitly deny a role a capability you set the value for that capability to false.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param array  $args {
	 *     Array of arguments describing the group type.
	 *
	 *         @type string $name            Displayed name.
	 *         @type array  $capabilities    Array of capabilities.
	 *         @type string $fallback_status If a capability isn't set, which typical
	 *                                       status is most similar to the new status?
	 *     }
	 * }
	 * @return object|WP_Error Group type object on success, WP_Error object on failure.
	 */
	public function add_status( $status, $args = array() ) {
		if ( empty( $status ) ) {
			return;
		}

		if ( isset( $bp->groups->statuses[ $status ] ) ) {
			return new WP_Error( 'bp_group_status_exists', __( 'Group status already exists.', 'buddypress' ), $status );
		}

		$r = bp_parse_args( $args, array(
			'name'            => ucfirst( $status ),
			'capabilities'    => array(),
			'fallback_status' => 'public'
		), 'register_group_type' );

		$status = sanitize_key( $status );

		$this->statuses[ $status ] = $r;
		$bp->groups->statuses = $this->statuses;

		$this->status_objects[$status] = new BP_Group_Status( $status, $capabilities );
		$this->status_names[$status]   = $r['name'];
		return $this->status_objects[$status];
	}

	/**
	 * Remove role by name.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $role Role name.
	 */
	public function remove_status( $status ) {
		if ( ! isset( $this->status_objects[$status] ) ) {
			return;
		}

		unset( $this->status_objects[$status] );
		unset( $this->status_names[$status] );
		unset( $this->statuses[$status] );

		$bp->groups->statuses = $this->statuses;
	}

	/**
	 * Add capability to role.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param string $cap Capability name.
	 * @param bool $grant Optional, default is true. Whether role is capable of performing capability.
	 */
	public function add_cap( $status, $cap, $grant = true ) {
		if ( ! isset( $this->statuses[$status] ) ) {
			return;
		}

		$this->statuses[$status]['capabilities'][$cap] = $grant;
		$bp->groups->statuses = $this->statuses;
	}

	/**
	 * Remove capability from role.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $role, $cap ) {
		if ( ! isset( $this->statuses[$status] ) ) {
			return;
		}

		unset( $this->statuses[$status]['capabilities'][$cap] );

		$bp->groups->statuses = $this->statuses;
	}

	/**
	 * Retrieve role object by name.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $role Role name.
	 * @return WP_Role|null WP_Role object if found, null if the role does not exist.
	 */
	public function get_status( $status ) {
		if ( isset( $this->status_objects[$status] ) ) {
			return $this->status_objects[$status];
		} else {
			return null;
		}
	}

	/**
	 * Retrieve list of role names.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @return array List of role names.
	 */
	public function get_names() {
		return $this->status_names;
	}

	/**
	 * Whether role name is currently in the list of available roles.
	 *
	 * @since 2.7.0
	 * @access public
	 *
	 * @param string $role Role name to look up.
	 * @return bool
	 */
	public function is_status( $status ) {
		return isset( $this->status_names[$status] );
	}
}
