<?php
/**
 * BuddyPress - Users Notifications
 *
 * @package BuddyPress
 * @subpackage bp-legacy
 * @version 3.0.0
 */

?>

<div class="item-list-tabs no-ajax" id="subnav" aria-label="<?php esc_attr_e( 'Member secondary navigation', 'buddypress' ); ?>" role="navigation">
	<ul>
		<?php bp_get_options_nav(); ?>
	</ul>
</div>

<?php
if ( 'sent-invites' === bp_current_action() ) {
		echo "send tinvites";
} else {
		echo "default";

}

