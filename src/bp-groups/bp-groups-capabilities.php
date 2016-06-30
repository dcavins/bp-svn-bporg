<?php
/**
 * Map meta capabilities to primitive capabilities.
 *
 * This does not actually compare whether the user ID has the actual capability,
 * just what the capability or capabilities are. Meta capability list value can
 * be 'delete_user', 'edit_user', 'remove_user', 'promote_user', 'delete_post',
 * 'delete_page', 'edit_post', 'edit_page', 'read_post', or 'read_page'.
 *
 * @since 2.0.0
 *
 * @global array $post_type_meta_caps Used to get post type meta capabilities.
 *
 * @param string $cap       Capability name.
 * @param int    $user_id   User ID.
 * @param int    $object_id Optional. ID of the specific object to check against if `$cap` is a "meta" cap.
 *                          "Meta" capabilities, e.g. 'edit_post', 'edit_user', etc., are capabilities used
 *                          by map_meta_cap() to map to other "primitive" capabilities, e.g. 'edit_posts',
 *                          'edit_others_posts', etc. The parameter is accessed via func_get_args().
 * @return array Actual capabilities for meta capability.
 */
function bp_groups_map_meta_cap( $cap, $user_id ) {
	$args = array_slice( func_get_args(), 2 );
	$caps = array();

	switch ( $cap ) {
	case 'remove_user':
		$caps[] = 'remove_users';
		break;
	case 'promote_user':
	case 'add_users':
		$caps[] = 'promote_users';
		break;
	case 'edit_user':
	case 'edit_users':
		// Allow user to edit itself
		if ( 'edit_user' == $cap && isset( $args[0] ) && $user_id == $args[0] )
			break;

		// In multisite the user must have manage_network_users caps. If editing a super admin, the user must be a super admin.
		if ( is_multisite() && ( ( ! is_super_admin( $user_id ) && 'edit_user' === $cap && is_super_admin( $args[0] ) ) || ! user_can( $user_id, 'manage_network_users' ) ) ) {
			$caps[] = 'do_not_allow';
		} else {
			$caps[] = 'edit_users'; // edit_user maps to edit_users.
		}
		break;
	case 'delete_post':
	case 'delete_page':
		$post = get_post( $args[0] );
		if ( ! $post ) {
			$caps[] = 'do_not_allow';
			break;
		}

		if ( 'revision' == $post->post_type ) {
			$post = get_post( $post->post_parent );
			if ( ! $post ) {
				$caps[] = 'do_not_allow';
				break;
			}
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type ) {
			/* translators: 1: post type, 2: capability name */
			_doing_it_wrong( __FUNCTION__, sprintf( __( 'The post type %1$s is not registered, so it may not be reliable to check the capability "%2$s" against a post of that type.' ), $post->post_type, $cap ), '4.4.0' );
			$caps[] = 'edit_others_posts';
			break;
		}

		if ( ! $post_type->map_meta_cap ) {
			$caps[] = $post_type->cap->$cap;
			// Prior to 3.1 we would re-call map_meta_cap here.
			if ( 'delete_post' == $cap )
				$cap = $post_type->cap->$cap;
			break;
		}

		// If the post author is set and the user is the author...
		if ( $post->post_author && $user_id == $post->post_author ) {
			// If the post is published or scheduled...
			if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
				$caps[] = $post_type->cap->delete_published_posts;
			} elseif ( 'trash' == $post->post_status ) {
				$status = get_post_meta( $post->ID, '_wp_trash_meta_status', true );
				if ( in_array( $status, array( 'publish', 'future' ), true ) ) {
					$caps[] = $post_type->cap->delete_published_posts;
				} else {
					$caps[] = $post_type->cap->delete_posts;
				}
			} else {
				// If the post is draft...
				$caps[] = $post_type->cap->delete_posts;
			}
		} else {
			// The user is trying to edit someone else's post.
			$caps[] = $post_type->cap->delete_others_posts;
			// The post is published or scheduled, extra cap required.
			if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
				$caps[] = $post_type->cap->delete_published_posts;
			} elseif ( 'private' == $post->post_status ) {
				$caps[] = $post_type->cap->delete_private_posts;
			}
		}
		break;
		// edit_post breaks down to edit_posts, edit_published_posts, or
		// edit_others_posts
	case 'edit_post':
	case 'edit_page':
		$post = get_post( $args[0] );
		if ( ! $post ) {
			$caps[] = 'do_not_allow';
			break;
		}

		if ( 'revision' == $post->post_type ) {
			$post = get_post( $post->post_parent );
			if ( ! $post ) {
				$caps[] = 'do_not_allow';
				break;
			}
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type ) {
			/* translators: 1: post type, 2: capability name */
			_doing_it_wrong( __FUNCTION__, sprintf( __( 'The post type %1$s is not registered, so it may not be reliable to check the capability "%2$s" against a post of that type.' ), $post->post_type, $cap ), '4.4.0' );
			$caps[] = 'edit_others_posts';
			break;
		}

		if ( ! $post_type->map_meta_cap ) {
			$caps[] = $post_type->cap->$cap;
			// Prior to 3.1 we would re-call map_meta_cap here.
			if ( 'edit_post' == $cap )
				$cap = $post_type->cap->$cap;
			break;
		}

		// If the post author is set and the user is the author...
		if ( $post->post_author && $user_id == $post->post_author ) {
			// If the post is published or scheduled...
			if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
				$caps[] = $post_type->cap->edit_published_posts;
			} elseif ( 'trash' == $post->post_status ) {
				$status = get_post_meta( $post->ID, '_wp_trash_meta_status', true );
				if ( in_array( $status, array( 'publish', 'future' ), true ) ) {
					$caps[] = $post_type->cap->edit_published_posts;
				} else {
					$caps[] = $post_type->cap->edit_posts;
				}
			} else {
				// If the post is draft...
				$caps[] = $post_type->cap->edit_posts;
			}
		} else {
			// The user is trying to edit someone else's post.
			$caps[] = $post_type->cap->edit_others_posts;
			// The post is published or scheduled, extra cap required.
			if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
				$caps[] = $post_type->cap->edit_published_posts;
			} elseif ( 'private' == $post->post_status ) {
				$caps[] = $post_type->cap->edit_private_posts;
			}
		}
		break;
	case 'read_post':
	case 'read_page':
		$post = get_post( $args[0] );
		if ( ! $post ) {
			$caps[] = 'do_not_allow';
			break;
		}

		if ( 'revision' == $post->post_type ) {
			$post = get_post( $post->post_parent );
			if ( ! $post ) {
				$caps[] = 'do_not_allow';
				break;
			}
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type ) {
			/* translators: 1: post type, 2: capability name */
			_doing_it_wrong( __FUNCTION__, sprintf( __( 'The post type %1$s is not registered, so it may not be reliable to check the capability "%2$s" against a post of that type.' ), $post->post_type, $cap ), '4.4.0' );
			$caps[] = 'edit_others_posts';
			break;
		}

		if ( ! $post_type->map_meta_cap ) {
			$caps[] = $post_type->cap->$cap;
			// Prior to 3.1 we would re-call map_meta_cap here.
			if ( 'read_post' == $cap )
				$cap = $post_type->cap->$cap;
			break;
		}

		$status_obj = get_post_status_object( $post->post_status );
		if ( $status_obj->public ) {
			$caps[] = $post_type->cap->read;
			break;
		}

		if ( $post->post_author && $user_id == $post->post_author ) {
			$caps[] = $post_type->cap->read;
		} elseif ( $status_obj->private ) {
			$caps[] = $post_type->cap->read_private_posts;
		} else {
			$caps = map_meta_cap( 'edit_post', $user_id, $post->ID );
		}
		break;
	case 'publish_post':
		$post = get_post( $args[0] );
		if ( ! $post ) {
			$caps[] = 'do_not_allow';
			break;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type ) {
			/* translators: 1: post type, 2: capability name */
			_doing_it_wrong( __FUNCTION__, sprintf( __( 'The post type %1$s is not registered, so it may not be reliable to check the capability "%2$s" against a post of that type.' ), $post->post_type, $cap ), '4.4.0' );
			$caps[] = 'edit_others_posts';
			break;
		}

		$caps[] = $post_type->cap->publish_posts;
		break;
	case 'edit_post_meta':
	case 'delete_post_meta':
	case 'add_post_meta':
		$post = get_post( $args[0] );
		if ( ! $post ) {
			$caps[] = 'do_not_allow';
			break;
		}

		$caps = map_meta_cap( 'edit_post', $user_id, $post->ID );

		$meta_key = isset( $args[ 1 ] ) ? $args[ 1 ] : false;

		if ( $meta_key && has_filter( "auth_post_meta_{$meta_key}" ) ) {
			/**
			 * Filter whether the user is allowed to add post meta to a post.
			 *
			 * The dynamic portion of the hook name, `$meta_key`, refers to the
			 * meta key passed to {@see map_meta_cap()}.
			 *
			 * @since 3.3.0
			 *
			 * @param bool   $allowed  Whether the user can add the post meta. Default false.
			 * @param string $meta_key The meta key.
			 * @param int    $post_id  Post ID.
			 * @param int    $user_id  User ID.
			 * @param string $cap      Capability name.
			 * @param array  $caps     User capabilities.
			 */
			$allowed = apply_filters( "auth_post_meta_{$meta_key}", false, $meta_key, $post->ID, $user_id, $cap, $caps );
			if ( ! $allowed )
				$caps[] = $cap;
		} elseif ( $meta_key && is_protected_meta( $meta_key, 'post' ) ) {
			$caps[] = $cap;
		}
		break;
	case 'edit_comment':
		$comment = get_comment( $args[0] );
		if ( ! $comment ) {
			$caps[] = 'do_not_allow';
			break;
		}

		$post = get_post( $comment->comment_post_ID );

		/*
		 * If the post doesn't exist, we have an orphaned comment.
		 * Fall back to the edit_posts capability, instead.
		 */
		if ( $post ) {
			$caps = map_meta_cap( 'edit_post', $user_id, $post->ID );
		} else {
			$caps = map_meta_cap( 'edit_posts', $user_id );
		}
		break;
	case 'unfiltered_upload':
		if ( defined('ALLOW_UNFILTERED_UPLOADS') && ALLOW_UNFILTERED_UPLOADS && ( !is_multisite() || is_super_admin( $user_id ) )  )
			$caps[] = $cap;
		else
			$caps[] = 'do_not_allow';
		break;
	case 'unfiltered_html' :
		// Disallow unfiltered_html for all users, even admins and super admins.
		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML )
			$caps[] = 'do_not_allow';
		elseif ( is_multisite() && ! is_super_admin( $user_id ) )
			$caps[] = 'do_not_allow';
		else
			$caps[] = $cap;
		break;
	case 'edit_files':
	case 'edit_plugins':
	case 'edit_themes':
		// Disallow the file editors.
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			$caps[] = 'do_not_allow';
		elseif ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
			$caps[] = 'do_not_allow';
		elseif ( is_multisite() && ! is_super_admin( $user_id ) )
			$caps[] = 'do_not_allow';
		else
			$caps[] = $cap;
		break;
	case 'update_plugins':
	case 'delete_plugins':
	case 'install_plugins':
	case 'upload_plugins':
	case 'update_themes':
	case 'delete_themes':
	case 'install_themes':
	case 'upload_themes':
	case 'update_core':
		// Disallow anything that creates, deletes, or updates core, plugin, or theme files.
		// Files in uploads are excepted.
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			$caps[] = 'do_not_allow';
		} elseif ( is_multisite() && ! is_super_admin( $user_id ) ) {
			$caps[] = 'do_not_allow';
		} elseif ( 'upload_themes' === $cap ) {
			$caps[] = 'install_themes';
		} elseif ( 'upload_plugins' === $cap ) {
			$caps[] = 'install_plugins';
		} else {
			$caps[] = $cap;
		}
		break;
	case 'activate_plugins':
		$caps[] = $cap;
		if ( is_multisite() ) {
			// update_, install_, and delete_ are handled above with is_super_admin().
			$menu_perms = get_site_option( 'menu_items', array() );
			if ( empty( $menu_perms['plugins'] ) )
				$caps[] = 'manage_network_plugins';
		}
		break;
	case 'delete_user':
	case 'delete_users':
		// If multisite only super admins can delete users.
		if ( is_multisite() && ! is_super_admin( $user_id ) )
			$caps[] = 'do_not_allow';
		else
			$caps[] = 'delete_users'; // delete_user maps to delete_users.
		break;
	case 'create_users':
		if ( !is_multisite() )
			$caps[] = $cap;
		elseif ( is_super_admin( $user_id ) || get_site_option( 'add_new_users' ) )
			$caps[] = $cap;
		else
			$caps[] = 'do_not_allow';
		break;
	case 'manage_links' :
		if ( get_option( 'link_manager_enabled' ) )
			$caps[] = $cap;
		else
			$caps[] = 'do_not_allow';
		break;
	case 'customize' :
		$caps[] = 'edit_theme_options';
		break;
	case 'delete_site':
		$caps[] = 'manage_options';
		break;
	default:
		// Handle meta capabilities for custom post types.
		global $post_type_meta_caps;
		if ( isset( $post_type_meta_caps[ $cap ] ) ) {
			$args = array_merge( array( $post_type_meta_caps[ $cap ], $user_id ), $args );
			return call_user_func_array( 'map_meta_cap', $args );
		}

		// If no meta caps match, return the original cap.
		$caps[] = $cap;
	}

	/**
	 * Filter a user's capabilities depending on specific context and/or privilege.
	 *
	 * @since 2.8.0
	 *
	 * @param array  $caps    Returns the user's actual capabilities.
	 * @param string $cap     Capability name.
	 * @param int    $user_id The user ID.
	 * @param array  $args    Adds the context to the cap. Typically the object ID.
	 */
	return apply_filters( 'map_meta_cap', $caps, $cap, $user_id, $args );
}

/**
 * Whether the current user has a specific capability.
 *
 * While checking against particular roles in place of a capability is supported
 * in part, this practice is discouraged as it may produce unreliable results.
 *
 * Note: Will always return true if the current user is a super admin, unless specifically denied.
 *
 * @since 2.0.0
 *
 * @see WP_User::has_cap()
 * @see map_meta_cap()
 *
 * @param string $capability Capability name.
 * @param int    $object_id  Optional. ID of the specific object to check against if `$capability` is a "meta" cap.
 *                           "Meta" capabilities, e.g. 'edit_post', 'edit_user', etc., are capabilities used
 *                           by map_meta_cap() to map to other "primitive" capabilities, e.g. 'edit_posts',
 *                           'edit_others_posts', etc. Accessed via func_get_args() and passed to WP_User::has_cap(),
 *                           then map_meta_cap().
 * @return bool Whether the current user has the given capability. If `$capability` is a meta cap and `$object_id` is
 *              passed, whether the current user has the given meta capability for the given object.
 */
// function bp_groups_current_group_has_cap( $capability ) {
// 	$current_group = groups_get_current_group();

// 	if ( empty( $current_group ) ) {
// 		return false;
// 	}

// 	return call_user_func_array( array( $current_group, 'has_cap' ), $capability );
// }

/**
 * Whether a particular user has capability or role.
 *
 * @since 2.7.0
 *
 * @param int|object $group      Group ID or object.
 * @param string     $capability Capability or status name.
 *
 * @return bool
 */
// function bp_groups_group_has_cap( $group, $capability ) {
// 	if ( ! is_object( $group ) ) {
// 		$group = groups_get_group( array( 'group_id' => (int) $group ) );
// 	}

// 	if ( ! $group ) {
// 		return false;
// 	}

// 	return call_user_func_array( array( $group, 'has_cap' ), $capability );
// }

/**
 * Retrieves the global WP_Roles instance and instantiates it if necessary.
 *
 * @since 2.7.0
 *
 * @global WP_Roles $wp_roles WP_Roles global instance.
 *
 * @return WP_Roles WP_Roles global instance if not already instantiated.
 */
// function bp_groups_statuses() {
// 	$bp = buddypress();

// 	if ( ! isset( $bp->groups->statuses ) ) {
// 		$bp->groups->statuses = new BP_Groups_Statuses();
// 	}
// 	return $bp->groups->statuses;
// }

/**
 * Retrieve role object.
 *
 * @since 2.7.0
 *
 * @param string $role Role name.
 * @return WP_Role|null WP_Role object if found, null if the role does not exist.
 */
// function bp_groups_get_group_status( $status ) {
// 	return bp_groups_statuses()->get_status( $status );
// }

/**
 * Add group status, if it does not exist.
 *
 * @since 2.0.0
 *
 * @param string $status Role name.
 * @param string $display_name Display name for role.
 * @param array $capabilities List of capabilities, e.g. array( 'edit_posts' => true, 'delete_posts' => false );
 * @return WP_Role|null WP_Role object if role is added, null if already exists.
 */
// function bp_groups_add_group_status( $status, $args = array() ) {
// 	if ( empty( $status ) ) {
// 		return;
// 	}
// 	return bp_groups_statuses()->add_status( $status, $args );
// }

/**
 * Remove group status, if it exists.
 *
 * @since 2.7.0
 *
 * @param string $role Role name.
 */
// function bp_groups_remove_group_status( $status ) {
// 	bp_groups_statuses()->remove_status( $status );
// }