<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class BP_Core_Setup_Wizard {

	/**
	 * @var int The current step of the updater
	 */
	var $current_step;

	/**
	 *
	 * @var array The total steps to be completed
	 */
	var $steps;

	/** Methods ***************************************************************/

	function __construct() {

		// Set/reset the wizard cookie
		setcookie( 'bp-wizard-step', 0, time() + 60 * 60 * 24, COOKIEPATH );
		$_COOKIE['bp-wizard-step'] = 0;

		// Call the save method that will save data and modify $current_step
		if ( isset( $_POST['save'] ) )
			$this->save( $_POST['save'] );

		// Build the steps needed for update or new installations
		$this->steps = $this->add_steps();
	}

	function current_step() {
		if ( isset( $_POST['step'] ) ) {
			$current_step = (int) $_POST['step'] + 1;
		} else {
			if ( !empty( $_COOKIE['bp-wizard-step'] ) ) {
				$current_step = $_COOKIE['bp-wizard-step'];
			} else {
				$current_step = 0;
			}
		}

		return $current_step;
	}

	function add_steps() {

		// Setup wizard steps
		$steps = array();

		// This is a first time installation
		if ( bp_get_maintenance_mode() == 'install' ) {
			$steps = array(
				__( 'Components', 'buddypress' ),
				__( 'Pages',      'buddypress' ),
				__( 'Permalinks', 'buddypress' ),
				__( 'Finish',     'buddypress' )
			);

		// This is an update to an existing install
		} else {

			// New for BP 1.5
			if ( bp_get_db_version_raw() < 1801 || !bp_core_get_directory_page_ids() ) {
				$steps[] = __( 'Components', 'buddypress' );
				$steps[] = __( 'Pages',      'buddypress' );
			}

			// New for BP 1.6
			if ( bp_get_db_version_raw() < 5222 && !defined( 'BP_USE_WP_ADMIN_BAR' ) )
				$steps[] = __( 'Toolbar', 'buddypress' );

			if ( bp_get_db_version_raw() < (int) bp_get_db_version() )
				$steps[] = __( 'Database Update', 'buddypress' );

			$steps[] = __( 'Finish', 'buddypress' );
		}

		return $steps;
	}

	function save( $step_name ) {

		// Bail if user is not capable of being here
		if ( ! bp_current_user_can( 'activate_plugins' ) )
			wp_die( 'Uh... No.' );

		// Save any posted values
		switch ( $step_name ) {
			case 'db_update':
				$result = $this->step_db_update_save();
				break;

			case 'components':
				$result = $this->step_components_save();
				break;

			case 'pages':
				$result = $this->step_pages_save();
				break;

			case 'permalinks':
				$result = $this->step_permalinks_save();
				break;

			case 'admin_bar':
				$result = $this->step_admin_bar_save();
				break;

			case 'finish':
			default:
				$result = $this->step_finish_save();
				break;
		}

		if ( 'finish' != $step_name )
			setcookie( 'bp-wizard-step', (int) $this->current_step(), time() + 60 * 60 * 24, COOKIEPATH );
	}

	function html() {

		// Bail if user is not capable of being here
		if ( ! bp_current_user_can( 'activate_plugins' ) )
			wp_die( 'You do not have sufficient permissions to access this page.' );
		?>

		<div class="wrap" id="bp-wizard">

			<?php screen_icon( 'buddypress' ); ?>

			<h2>
				<?php
				if ( 'update' == bp_get_maintenance_mode() )
					_e( 'BuddyPress Update', 'buddypress' );
				else
					_e( 'BuddyPress Setup', 'buddypress' );
				?>
			</h2>

			<?php
				do_action( 'bp_admin_notices' );

				$step_count  = count( $this->steps ) - 1;
				$wiz_or_set  = $this->current_step() >= $step_count ? 'bp-components' : 'bp-wizard';
				$form_action = bp_core_do_network_admin() ? network_admin_url( add_query_arg( array( 'page' => $wiz_or_set ), 'admin.php' ) ) : admin_url( add_query_arg( array( 'page' => $wiz_or_set ), 'index.php' ) );
			?>

			<form action="<?php echo $form_action; ?>" method="post" id="bp-wizard-form">
				<div id="bp-wizard-nav">
					<ol>

						<?php foreach( (array) $this->steps as $i => $name ) : ?>

							<li<?php if ( $this->current_step() == $i ) : ?> class="current"<?php endif; ?>>
								<?php if ( $this->current_step() > $i ) : ?>

									<span class="complete">&nbsp;</span>

								<?php else :

									echo $i + 1 . '. ';

								endif;

								echo esc_attr( $name ) ?>

							</li>

						<?php endforeach; ?>

					</ol>

					<?php if ( __( 'Finish', 'buddypress' ) == $this->steps[$this->current_step()] ) : ?>

						<div class="prev-next submit clear">
							<input type="submit" value="<?php _e( 'Finish &amp; Activate', 'buddypress' ); ?>" name="submit" />
						</div>

					<?php else : ?>

						<div class="prev-next submit clear">
							<input type="submit" value="<?php _e( 'Save &amp; Next', 'buddypress' ); ?>" name="submit" />
						</div>

					<?php endif; ?>

				</div>

				<div id="bp-wizard-content">

					<?php switch ( $this->steps[$this->current_step()] ) {
						case __( 'Database Update', 'buddypress') :
							$this->step_db_update();
							break;

						case __( 'Components', 'buddypress') :
							$this->step_components();
							break;

						case __( 'Pages', 'buddypress') :
							$this->step_pages();
							break;

						case __( 'Permalinks', 'buddypress') :
							$this->step_permalinks();
							break;

						case __( 'Toolbar', 'buddypress' ) :
							$this->step_admin_bar();
							break;

						case __( 'Finish', 'buddypress') :
							$this->step_finish();
							break;
					} ?>

				</div>
			</form>
		</div>

	<?php
	}

	/** Screen methods ********************************************************/

	function step_db_update() {
	?>

		<p><?php _e( 'To complete the update, a few changes need to be made to your database. These changes are not destructive and will not remove or change any existing settings.', 'buddypress' ); ?></p>

		<div class="submit clear">
			<input type="hidden" name="save" value="db_update" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $this->current_step() ); ?>" />

			<?php wp_nonce_field( 'bpwizard_db_update' ) ?>

		</div>

	<?php
	}

	function step_components() {

		if ( !function_exists( 'bp_core_admin_components_options' ) )
			require ( BP_PLUGIN_DIR . 'bp-core/admin/bp-core-components.php' );

		bp_core_admin_components_options(); ?>

		<div class="submit clear">
			<input type="hidden" name="save" value="components" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $this->current_step() ); ?>" />

			<?php wp_nonce_field( 'bpwizard_components' ); ?>

		</div>

	<?php
	}

	function step_pages() {
		global $wpdb;

		// Get BuddyPress once in this scope
		$bp = buddypress();

		// Make sure that page info is pulled from bp_get_root_blog_id() (except when in
		// multisite mode)
		if ( !empty( $wpdb->blogid ) && ( $wpdb->blogid != bp_get_root_blog_id() ) && ( !defined( 'BP_ENABLE_MULTIBLOG' ) ) )
			switch_to_blog( bp_get_root_blog_id() );

		$existing_pages = bp_core_get_directory_page_ids();

		// Provide empty indexes to avoid PHP errors with wp_dropdown_pages()
		$indexes = array( 'members', 'activity', 'groups', 'forums', 'blogs', 'register', 'activate' );
		foreach ( $indexes as $index ) {
			if ( !isset( $existing_pages[$index] ) ) {
				$existing_pages[$index] = '';
			}
		}

		if ( !empty( $existing_pages['blogs'] ) )
			$existing_blog_page = '&selected=' . $existing_pages['blogs'];
		else
			$existing_blog_page = '';

		// Get active components
		$active_components = apply_filters( 'bp_active_components', bp_get_option( 'bp-active-components' ) );

		// Check for defined slugs
		$members_slug    = !empty( $bp->members->slug    ) ? $bp->members->slug    : __( 'members',  'buddypress' );

		// Groups
		$groups_slug     = !empty( $bp->groups->slug     ) ? $bp->groups->slug     : __( 'groups',   'buddypress' );

		// Activity
		$activity_slug   = !empty( $bp->activity->slug   ) ? $bp->activity->slug   : __( 'activity', 'buddypress' );

		// Forums
		$forums_slug     = !empty( $bp->forums->slug     ) ? $bp->forums->slug     : __( 'forums',   'buddypress' );

		// Blogs
		$blogs_slug      = !empty( $bp->blogs->slug      ) ? $bp->blogs->slug      : __( 'blogs',    'buddypress' );

		// Register
		$register_slug   = !empty( $bp->register->slug   ) ? $bp->register->slug   : __( 'register', 'buddypress' );

		// Activation
		$activation_slug = !empty( $bp->activation->slug ) ? $bp->activation->slug : __( 'activate', 'buddypress' );

		?>

		<script type="text/javascript">
			jQuery( document ).ready( function() {
				jQuery( 'select' ).change( function() {
					jQuery( this ).siblings( 'input[type=radio]' ).click();
				});
			});
		</script>

		<?php if ( bp_get_maintenance_mode() ) : ?>

			<p><?php _e( 'BuddyPress uses WordPress pages to display directories. This allows you to easily change their titles and relocate them.', 'buddypress' ); ?></p>
			<p><?php _e( 'Choose an existing page, have one auto-created, or create them manually and come back here once you are finished.', 'buddypress' ); ?></p>

		<?php endif; ?>

		<table class="form-table">

			<tr valign="top">
				<th scope="row">
					<h5><?php _e( 'Members', 'buddypress' ); ?></h5>
					<p><?php _e( 'Displays member profiles, and a directory of all site members.', 'buddypress' ); ?></p>
				</th>
				<td>
					<p><label><input type="radio" name="bp_pages[members]" <?php checked( empty( $existing_pages['members'] ) ); ?>  value="<?php echo $members_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ) ?> <?php echo home_url( $members_slug ); ?>/</label></p>

					<?php if ( $members_page_dropdown = wp_dropdown_pages( "name=bp-members-page&echo=0&selected={$existing_pages['members']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>

						<p><label><input type="radio" name="bp_pages[members]" <?php checked( !empty( $existing_pages['members'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['members'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $members_page_dropdown ?></label></p>

					<?php endif ?>
				</td>
			</tr>

			<?php if ( isset( $active_components['groups'] ) ) : ?>

				<tr valign="top">
					<th scope="row">
						<h5><?php _e( 'Groups', 'buddypress' ); ?></h5>
						<p><?php _e( 'Displays individual groups as well as a directory of groups.', 'buddypress' ); ?></p>
					</th>
					<td>
						<p><label><input type="radio" name="bp_pages[groups]" <?php checked( empty( $existing_pages['groups'] ) ); ?>  value="<?php echo $groups_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ); ?> <?php echo home_url( $groups_slug ); ?>/</label></p>

						<?php if ( $groups_page_dropdown = wp_dropdown_pages( "name=bp-groups-page&echo=0&selected={$existing_pages['groups']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>
							<p><label><input type="radio" name="bp_pages[groups]" <?php checked( !empty( $existing_pages['groups'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['groups'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $groups_page_dropdown ?></label></p>
						<?php endif ?>
					</td>
				</tr>

			<?php endif; ?>

			<?php /* The Blogs component only needs a directory page when Multisite is enabled */ ?>
			<?php if ( is_multisite() && isset( $active_components['blogs'] ) ) : ?>

				<tr valign="top">
					<th scope="row">
						<h5><?php _e( 'Blogs', 'buddypress' ); ?></h5>
						<p><?php _e( 'Displays a directory of the blogs in your network.', 'buddypress' ); ?></p>
					</th>
					<td>
						<p><label><input type="radio" name="bp_pages[blogs]" <?php checked( empty( $existing_pages['blogs'] ) ); ?>  value="<?php echo $blogs_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ); ?> <?php echo home_url( $blogs_slug ); ?>/</label></p>

						<?php if ( $blogs_page_dropdown = wp_dropdown_pages( "name=bp-blogs-page&echo=0&selected={$existing_pages['blogs']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>
							<p><label><input type="radio" name="bp_pages[blogs]" <?php checked( !empty( $existing_pages['blogs'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['blogs'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $blogs_page_dropdown ?></label></p>
						<?php endif ?>
					</td>
				</tr>

			<?php endif; ?>

			<?php if ( isset( $active_components['activity'] ) ) : ?>

				<tr valign="top">
					<th scope="row">
						<h5><?php _e( 'Activity', 'buddypress' ); ?></h5>
						<p><?php _e( "Displays the activity for the entire site, a member's friends, groups and @mentions.", 'buddypress' ); ?></p>
					</th>
					<td>
						<p><label><input type="radio" name="bp_pages[activity]" <?php checked( empty( $existing_pages['activity'] ) ); ?>  value="<?php echo $activity_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ); ?> <?php echo home_url( $activity_slug ); ?>/</label></p>

						<?php if ( $activity_page_dropdown = wp_dropdown_pages( "name=bp-activity-page&echo=0&selected={$existing_pages['activity']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>
							<p><label><input type="radio" name="bp_pages[activity]" <?php checked( !empty( $existing_pages['activity'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['activity'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $activity_page_dropdown ?></label></p>
						<?php endif ?>
					</td>
				</tr>

			<?php endif; ?>

			<?php if ( isset( $active_components['forums'] ) ) : ?>

				<tr valign="top">
					<th scope="row">
						<h5><?php _e( 'Forums', 'buddypress' ); ?></h5>
						<p><?php _e( 'Displays a directory of public forum topics.', 'buddypress' ); ?></p>
					</th>
					<td>
						<p><label><input type="radio" name="bp_pages[forums]" <?php checked( empty( $existing_pages['forums'] ) ); ?>  value="<?php echo $forums_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ); ?> <?php echo home_url( $forums_slug ); ?>/</label></p>

						<?php if ( $forums_page_dropdown = wp_dropdown_pages( "name=bp-forums-page&echo=0&selected={$existing_pages['forums']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>
							<p><label><input type="radio" name="bp_pages[forums]" <?php checked( !empty( $existing_pages['forums'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['forums'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $forums_page_dropdown ?></label></p>
						<?php endif ?>
					</td>
				</tr>

			<?php endif; ?>

			<tr valign="top">
				<th scope="row">
					<h5><?php _e( 'Register', 'buddypress' ); ?></h5>
					<p><?php _e( 'Displays a site registration page where users can create new accounts.', 'buddypress' ); ?></p>
				</th>
				<td>
					<p><label><input type="radio" name="bp_pages[register]" <?php checked( empty( $existing_pages['register'] ) ); ?>  value="<?php echo $register_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ) ?> <?php echo home_url( $register_slug ) ?>/</label></p>

					<?php if ( $register_page_dropdown = wp_dropdown_pages( "name=bp-register-page&echo=0&selected={$existing_pages['register']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>
						<p><label><input type="radio" name="bp_pages[register]" <?php checked( !empty( $existing_pages['register'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['register'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $register_page_dropdown ?></label></p>
					<?php endif ?>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					<h5><?php _e( 'Activate', 'buddypress' ); ?></h5>
					<p><?php _e( 'The page users will visit to activate their account once they have registered.', 'buddypress' ); ?></p>
				</th>
				<td>
					<p><label><input type="radio" name="bp_pages[activate]" <?php checked( empty( $existing_pages['activate'] ) ); ?>  value="<?php echo $activation_slug; ?>" /> <?php _e( 'Automatically create a page at:', 'buddypress' ); ?> <?php echo home_url( $activation_slug ); ?>/</label></p>

					<?php if ( $activate_page_dropdown = wp_dropdown_pages( "name=bp-activate-page&echo=0&selected={$existing_pages['activate']}&show_option_none=" . __( '- Select -', 'buddypress' ) ) ) : ?>
						<p><label><input type="radio" name="bp_pages[activate]" <?php checked( !empty( $existing_pages['activate'] ) ); ?> value="<?php echo get_post_field( 'post_name', $existing_pages['activate'] ); ?>" /> <?php _e( 'Use an existing page:', 'buddypress' ); ?> <?php echo $activate_page_dropdown ?></label></p>
					<?php endif ?>
				</td>
			</tr>
		</table>

		<div class="submit clear">
			<input type="hidden" name="save" value="pages" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $this->current_step() ); ?>" />

			<?php wp_nonce_field( 'bpwizard_pages' ); ?>

		</div>

		<?php

		restore_current_blog();
	}

	function step_permalinks() {

		$prefix              = '';
		$permalink_structure = bp_get_option( 'permalink_structure' );
		$structures          = array( '', $prefix . '/%year%/%monthnum%/%day%/%postname%/', $prefix . '/%year%/%monthnum%/%postname%/', $prefix . '/archives/%post_id%' );

		if ( !got_mod_rewrite() && !iis7_supports_permalinks() )
			$prefix = '/index.php'; ?>

		<p><?php

			// If we're using permalinks already, adjust text accordingly
			if ( !empty( $permalink_structure ) ) {
				_e( 'Your permalink settings are compatible with BuddyPress.', 'buddypress' );
			} else {
				_e( 'Pretty permalinks must be active on your site.', 'buddypress' );
			}

		?></p>
		<p><?php printf( __( 'For more advanced options please visit the <a href="%s">permalink settings page</a> now and come back here later.', 'buddypress' ), admin_url( 'options-permalink.php' ) ); ?>

		<table class="form-table">
			
			<?php if ( !empty( $permalink_structure ) && ! in_array( $permalink_structure, $structures ) ) : ?>
				<tr>
					<th><label><input name="permalink_structure" type="radio" checked="checked" value="<?php echo esc_attr( $permalink_structure ); ?>" class="tog" <?php checked( true ); ?> />&nbsp;<?php _e( 'Current' ); ?></label></th>
					<td><code><?php echo get_home_url() . $prefix . $permalink_structure; ?></code></td>
				</tr>
			<?php endif; ?>

			<tr>
				<th><label><input name="permalink_structure" type="radio"<?php if ( empty( $permalink_structure ) || false != strpos( $permalink_structure, $structures[1] ) ) : ?> checked="checked" <?php endif; ?>value="<?php echo esc_attr( $structures[1] ); ?>" class="tog" <?php checked( $structures[1], $permalink_structure ); ?> />&nbsp;<?php _e( 'Day and name' ); ?></label></th>
				<td><code><?php echo get_home_url() . $prefix . '/' . date('Y') . '/' . date('m') . '/' . date('d') . '/sample-post/'; ?></code></td>
			</tr>
			<tr>
				<th><label><input name="permalink_structure" type="radio"<?php if ( empty( $permalink_structure ) || false != strpos( $permalink_structure, $structures[2] ) ) : ?> checked="checked" <?php endif; ?> value="<?php echo esc_attr( $structures[2] ); ?>" class="tog" <?php checked( $structures[2], $permalink_structure ); ?> />&nbsp;<?php _e( 'Month and name' ); ?></label></th>
				<td><code><?php echo get_home_url() . $prefix . '/' . date('Y') . '/' . date('m') . '/sample-post/'; ?></code></td>
			</tr>
			<tr>
				<th><label><input name="permalink_structure" type="radio"<?php if ( empty( $permalink_structure ) || false != strpos( $permalink_structure, $structures[3] ) ) : ?> checked="checked" <?php endif; ?> value="<?php echo esc_attr( $structures[3] ); ?>" class="tog" <?php checked( $structures[3], $permalink_structure ); ?> />&nbsp;<?php _e( 'Numeric' ); ?></label></th>
				<td><code><?php echo get_home_url() . $prefix ?>/archives/123</code></td>
			</tr>
		</table>

		<div class="submit clear">
			<input type="hidden" name="save" value="permalinks" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $this->current_step() ); ?>" />

			<?php if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && empty( $_POST['skip-htaccess'] ) ) : ?>

				<input type="hidden" name="skip-htaccess" value="skip-htaccess" />

			<?php endif; ?>

			<?php wp_nonce_field( 'bpwizard_permalinks' ); ?>

		</div>

	<?php
	}

	/**
	 * When upgrading to BP 1.6, prompt the admin to switch to WordPress' Toolbar.
	 *
	 * @since BuddyPress (1.6)
	 */
	function step_admin_bar() {
	?>

		<p><?php _e( "BuddyPress now uses the WordPress Toolbar; we've turbo-charged it by adding social items to help your users explore your site and manage their content.", 'buddypress' ); ?></p>

		<p><?php _e( "We've noticed that your site uses the old bar from earlier versions of BuddyPress.", 'buddypress' ); ?></p>

		<p>
			<label>
				<input type="checkbox" name="keep_buddybar" value="1" />
				<?php _e( "If you'd prefer to not switch to the WordPress Toolbar just yet, check this box. Don't worry, you can change your mind later.", 'buddypress' ); ?>
			</label>
		</p>

		<div class="submit clear">
			<input type="hidden" name="save" value="admin_bar" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $this->current_step() ); ?>" />

			<?php wp_nonce_field( 'bpwizard_admin_bar' ) ?>

		</div>

		<?php
	}

	function step_finish() {
	?>
		<p>
			<?php
			if ( 'update' == bp_get_maintenance_mode() )
				_e( "The BuddyPress update is complete, and your site is ready to go!", 'buddypress' );
			else
				_e( "The BuddyPress setup is complete, and your site is ready to go!", 'buddypress' );
			?>
		</p>

		<div class="submit clear">
			<input type="hidden" name="save" value="finish" />
			<input type="hidden" name="step" value="<?php echo esc_attr( $this->current_step() ); ?>" />

			<?php wp_nonce_field( 'bpwizard_finish' ); ?>

		</div>

	<?php
	}

	/** Save Step Methods *****************************************************/

	function step_db_update_save() {

		if ( ! isset( $_POST['submit'] ) )
			return false;

		check_admin_referer( 'bpwizard_db_update' );

		// Run the schema install to update tables
		bp_core_install();

		// Update to 1.5
		if ( bp_get_db_version_raw() < 1801 )
			$this->update_1_5();

		// Update to 1.6
		if ( bp_get_db_version_raw() < bp_get_db_version() )
			$this->update_1_6();

		return true;
	}

	function step_components_save() {

		if ( ! isset( $_POST['submit'] ) || ! isset( $_POST['bp_components'] ) )
			return false;

		check_admin_referer( 'bpwizard_components' );

		$active_components = array();

		// Settings form submitted, now save the settings.
		foreach ( (array) $_POST['bp_components'] as $key => $value ) {
			$active_components[$key] = 1;
		}

		bp_update_option( 'bp-active-components', $active_components );

		wp_cache_flush();
		bp_core_install();

		return true;
	}

	function step_pages_save() {

		if ( ! isset( $_POST['submit'] ) || ! isset( $_POST['bp_pages'] ) )
			return false;

		check_admin_referer( 'bpwizard_pages' );

		// Make sure that the pages are created on the bp_get_root_blog_id()
		//no matter which Dashboard the setup is being run on.
		if ( !defined( 'BP_ENABLE_MULTIBLOG' ) )
			switch_to_blog( bp_get_root_blog_id() );

		$blog_pages = $this->setup_pages( (array) $_POST['bp_pages'] );
		bp_update_option( 'bp-pages', $blog_pages );

		if ( !defined( 'BP_ENABLE_MULTIBLOG' ) )
			restore_current_blog();

		return true;
	}

	function step_permalinks_save() {
		global $wp_rewrite, $current_site, $current_blog;

		// Prevent debug notices
		$iis7_permalinks = $usingpi = $writable = false;

		if ( ! isset( $_POST['submit'] ) )
			return false;

		check_admin_referer( 'bpwizard_permalinks' );

		$home_path       = get_home_path();
		$iis7_permalinks = iis7_supports_permalinks();

		if ( isset( $_POST['permalink_structure'] ) ) {
			$permalink_structure = $_POST['permalink_structure'];

			if ( !empty( $permalink_structure ) )
				$permalink_structure = preg_replace( '#/+#', '/', '/' . $_POST['permalink_structure'] );

			if ( ( defined( 'VHOST' ) && constant( 'VHOST' ) == 'no' ) && $permalink_structure != '' && $current_site->domain . $current_site->path == $current_blog->domain . $current_blog->path )
				$permalink_structure = '/blog' . $permalink_structure;

			$wp_rewrite->set_permalink_structure( $permalink_structure );
		}

		if ( !empty( $iis7_permalinks ) ) {
			if ( ( !file_exists( $home_path . 'web.config' ) && win_is_writable( $home_path ) ) || win_is_writable( $home_path . 'web.config' ) ) {
				$writable = true;
			}
		} else {
			if ( ( !file_exists( $home_path . '.htaccess' ) && is_writable( $home_path ) ) || is_writable( $home_path . '.htaccess' ) ) {
				$writable = true;
			}
		}

		if ( $wp_rewrite->using_index_permalinks() )
			$usingpi = true;

		$wp_rewrite->flush_rules();

		if ( !empty( $iis7_permalinks ) || ( empty( $usingpi ) && empty( $writable ) ) ) {

			function _bp_core_wizard_step_permalinks_message() {
				global $wp_rewrite; ?>

				<div id="message" class="updated fade"><p>

					<?php
						_e( 'Oops, there was a problem creating a configuration file. ', 'buddypress' );

						if ( !empty( $iis7_permalinks ) ) {

							if ( !empty( $permalink_structure ) && empty( $usingpi ) && empty( $writable ) ) {

								_e( 'If your <code>web.config</code> file were <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>, we could do this automatically, but it isn&#8217;t so this is the url rewrite rule you should have in your <code>web.config</code> file. Click in the field and press <kbd>CTRL + a</kbd> to select all. Then insert this rule inside of the <code>/&lt;configuration&gt;/&lt;system.webServer&gt;/&lt;rewrite&gt;/&lt;rules&gt;</code> element in <code>web.config</code> file.' ); ?>

								<br /><br />

								<textarea rows="9" class="large-text readonly" style="background: #fff;" name="rules" id="rules" readonly="readonly"><?php echo esc_html( $wp_rewrite->iis7_url_rewrite_rules() ); ?></textarea>

							<?php

							} else if ( !empty( $permalink_structure ) && empty( $usingpi ) && !empty( $writable ) ); {
								_e( 'Permalink structure updated. Remove write access on web.config file now!' );
							}

						} else {

							_e( 'If your <code>.htaccess</code> file were <a href="http://codex.wordpress.org/Changing_File_Permissions">writable</a>, we could do this automatically, but it isn&#8217;t so these are the mod_rewrite rules you should have in your <code>.htaccess</code> file. Click in the field and press <kbd>CTRL + a</kbd> to select all.' ); ?>

							<br /><br />

							<textarea rows="6" class="large-text readonly" style="background: #fff;" name="rules" id="rules" readonly="readonly"><?php echo esc_html( $wp_rewrite->mod_rewrite_rules() ); ?></textarea>

						<?php } ?>

					<br /><br />

					<?php
						if ( empty( $iis7_permalinks ) )
							_e( 'Paste all these rules into a new <code>.htaccess</code> file in the root of your WordPress installation and save the file. Once you\'re done, please hit the "Save and Next" button to continue.', 'buddypress' );
					?>

				</p></div>

			<?php
			}

			if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['skip-htaccess'] ) ) {
				return true;
			} else {
				add_action( 'bp_admin_notices', '_bp_core_wizard_step_permalinks_message' );
				return false;
			}
		}

		return true;
	}

	/**
	 * When upgrading to BP 1.6, the admin is prompted to switch to WordPress' Toolbar.
	 * If they choose not to, record that preference in the options table.
	 *
	 * @since BuddyPress (1.6)
	 */
	function step_admin_bar_save() {
		if ( ! isset( $_POST['submit'] ) )
			return false;

		check_admin_referer( 'bpwizard_admin_bar' );

		if ( !empty( $_POST['keep_buddybar'] ) )
			bp_update_option( '_bp_force_buddybar', 1 );

		return true;
	}

	function step_finish_save() {

		if ( ! isset( $_POST['submit'] ) )
			return false;

		check_admin_referer( 'bpwizard_finish' );

		// Update the DB version in the database
		bp_version_bump();

		// Delete the setup cookie
		@setcookie( 'bp-wizard-step', '', time() - 3600, COOKIEPATH );

		// Redirect to the BuddyPress dashboard
		$redirect = bp_core_do_network_admin() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
		$redirect = add_query_arg( array( 'page' => 'bp-components' ), $redirect  );

		wp_safe_redirect( $redirect );

		// That's all!
		exit();
	}

	function setup_pages( $pages ) {

		$bp_pages = array();

		// Delete any existing pages
		foreach ( (array) bp_core_get_directory_page_ids() as $page_id ) {
			wp_delete_post( $page_id, true );
		}
		bp_delete_option( 'bp-pages' );

		foreach ( $pages as $key => $value ) {

			// Check for the selected page
			if ( 'page' == $value ) {
				if ( !empty( $_POST['bp-' . $key . '-page'] ) ) {
					$bp_pages[$key] = (int) $_POST['bp-' . $key . '-page'];
				} else {
					$bp_pages[$key] = wp_insert_post( array( 'comment_status' => 'closed', 'ping_status' => 'closed', 'post_title' => ucwords( $key ), 'post_status' => 'publish', 'post_type' => 'page' ) );
				}

			// Create a new page
			} else {
				$bp_pages[$key] = wp_insert_post( array( 'comment_status' => 'closed', 'ping_status' => 'closed', 'post_title' => ucwords( $value ), 'post_status' => 'publish', 'post_type' => 'page' ) );
			}
		}

		return $bp_pages;
	}

	// Database update methods based on version numbers
	function update_1_5() {
		delete_site_option( 'bp-activity-db-version' );
		delete_site_option( 'bp-blogs-db-version'    );
		delete_site_option( 'bp-friends-db-version'  );
		delete_site_option( 'bp-groups-db-version'   );
		delete_site_option( 'bp-messages-db-version' );
		delete_site_option( 'bp-xprofile-db-version' );
	}

	// Database update methods based on version numbers
	function update_1_6() {

		// Delete possible site options
		delete_site_option( 'bp-db-version'       );
		delete_site_option( '_bp_db_version'      );
		delete_site_option( 'bp-core-db-version'  );
		delete_site_option( '_bp-core-db-version' );

		// Delete possible blog options
		delete_blog_option( bp_get_root_blog_id(), 'bp-db-version'       );
		delete_blog_option( bp_get_root_blog_id(), 'bp-core-db-version'  );
		delete_site_option( bp_get_root_blog_id(), '_bp-core-db-version' );
		delete_site_option( bp_get_root_blog_id(), '_bp_db_version'      );
	}

	/**
	 * Reset the cookie so the install script starts over
	 */
	function reset_cookie() {
		@setcookie( 'bp-wizard-step', '', time() - 3600, COOKIEPATH );
	}
}

/**
 * Get the wizard
 *
 * @return boolean
 */
function bp_get_wizard() {
	$bp = buddypress();

	if ( !empty( $bp->admin->wizard ) )
		return $bp->admin->wizard;

	return false;
}
