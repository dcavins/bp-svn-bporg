<?php
/**
 * BuddyPress - Groups Admin - Group Settings
 *
 * @package BuddyPress
 * @subpackage bp-legacy
 */

?>

<h2 class="bp-screen-reader-text"><?php _e( 'Manage Group Settings', 'buddypress' ); ?></h2>

<?php

/**
 * Fires before the group settings admin display.
 *
 * @since 1.1.0
 */
do_action( 'bp_before_group_settings_admin' ); ?>

<?php if ( bp_is_active( 'forums' ) ) : ?>

	<?php if ( bp_forums_is_installed_correctly() ) : ?>

		<div class="checkbox">
			<label for="group-show-forum"><input type="checkbox" name="group-show-forum" id="group-show-forum" value="1"<?php bp_group_show_forum_setting(); ?> /> <?php _e( 'Enable discussion forum', 'buddypress' ); ?></label>
		</div>

		<hr />

	<?php endif; ?>

<?php endif; ?>

<fieldset class="group-create-privacy">

	<legend><?php _e( 'Privacy Options', 'buddypress' ); ?></legend>

	<div class="radio">
		<?php
		$allowed_statuses = bp_groups_get_group_statuses( array(), 'objects' );
		$new_group_status = bp_get_new_group_status();
		if ( ! $new_group_status ) {
			$new_group_status = current( $allowed_statuses )->name;
		}
		foreach( $allowed_statuses as $status ) :
			?>
			<label for="group-status-<?php echo $status->name ?>"><input type="radio" name="group-status" id="group-status-<?php echo $status->name ?>" value="<?php echo $status->name ?>"<?php checked( $status->name, $new_group_status ); ?> aria-describedby="<?php echo $status->name ?>-group-description" /> <?php echo $status->display_name; ?></label>

			<ul id="<?php echo $status->name ?>-group-description">
				<?php
				foreach ( $status->capabilities as $cap => $value ) :
					$cap_desc = bp_groups_group_capabilities_description( $cap, $value );
					if ( $cap_desc ) : ?>
						<li><?php echo $cap_desc; ?></li>
					<?php endif;
				endforeach; ?>
			</ul>
		<?php endforeach; ?>
	</div>

</fieldset>

<fieldset class="group-create-invitations">

	<legend><?php _e( 'Group Invitations', 'buddypress' ); ?></legend>

	<p><?php _e( 'Which members of this group are allowed to invite others?', 'buddypress' ); ?></p>

	<div class="radio">

		<label for="group-invite-status-members"><input type="radio" name="group-invite-status" id="group-invite-status-members" value="members"<?php bp_group_show_invite_status_setting( 'members' ); ?> /> <?php _e( 'All group members', 'buddypress' ); ?></label>

		<label for="group-invite-status-mods"><input type="radio" name="group-invite-status" id="group-invite-status-mods" value="mods"<?php bp_group_show_invite_status_setting( 'mods' ); ?> /> <?php _e( 'Group admins and mods only', 'buddypress' ); ?></label>

		<label for="group-invite-status-admins"><input type="radio" name="group-invite-status" id="group-invite-status-admins" value="admins"<?php bp_group_show_invite_status_setting( 'admins' ); ?> /> <?php _e( 'Group admins only', 'buddypress' ); ?></label>

	</div>

</fieldset>

<?php

/**
 * Fires after the group settings admin display.
 *
 * @since 1.1.0
 */
do_action( 'bp_after_group_settings_admin' ); ?>

<p><input type="submit" value="<?php esc_attr_e( 'Save Changes', 'buddypress' ); ?>" id="save" name="save" /></p>
<?php wp_nonce_field( 'groups_edit_group_settings' ); ?>
