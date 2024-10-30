<?php
/*
Plugin Name: BP Sticky Groups
Plugin URI: https://wordpress.org/plugins/bp-sticky-groups
Description: A plugin to stick BuddyPress groups to top of the groups directory
Version: 1.0.3
Requires at least: 3.6
Tested up to: 3.8
Author: dot07
Author URI: https://dot07.com
License: GPLv2
Network: true
Text Domain: bp-sticky-groups
Domain Path: /languages/
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BP_Sticky_Groups' ) ) :
/**
 * Main BP Sticky Groups Class
 *
 * @since 1.0
 */
class BP_Sticky_Groups {

	private static $instance;

	/**
	 * Main BP Sticky Groups Instance
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses BP_Sticky_Groups::setup_globals() to set the global needed
	 * @uses BP_Sticky_Groups::setup_actions() to set up the hooks
	 * @return object the instance
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new BP_Sticky_Groups;
			self::$instance->setup_globals();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	private function __construct() { /* Do nothing here */ }

	/**
	 * Sets some useful globals
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses plugin_basename()
	 * @uses plugin_dir_path() to build BP Sticky Groups plugin path
	 * @uses plugin_dir_url() to build BP Sticky Groups plugin url
	 * @uses bp_get_option() to get the sticky setting
	 */
	private function setup_globals() {
		// plugin's version
		$this->version = '1.0.3';

		// some globals
		$this->file          = __FILE__;
		$this->basename      = apply_filters( 'bp_sticky_groups_plugin_basename', plugin_basename( $this->file ) );
		$this->plugin_dir    = apply_filters( 'bp_sticky_groups_plugin_dir_path', plugin_dir_path( $this->file ) );
		$this->plugin_url    = apply_filters( 'bp_sticky_groups_plugin_dir_url',  plugin_dir_url ( $this->file ) );

		// BuddyPress version
		$this->bp_version_required = '1.9';
		$this->bp_version_ok = defined( 'BP_VERSION' ) ? version_compare( BP_VERSION, $this->bp_version_required, '>=' ) : false ;

		// Languages
		$this->domain = 'bp-sticky-groups';

		// Are stickies enabled ?
		$this->enabled = bp_get_option( 'bp-sticky-groups-enabled', 1 );

		// this is where the stickies will be globalized
		$this->stickies = array();

	}

	/**
	 * It's about hooks!
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses is_admin() to check for WordPress backend
	 */
	private function setup_actions() {

		if( !empty( $this->enabled ) && !empty( $this->bp_version_ok ) ) {
			add_filter( 'bp_groups_get_paged_groups_sql', array( $this, 'alter_bp_groups_query'     ), 10, 2 );
			add_filter( 'groups_get_groups',              array( $this, 'prepend_stickies_on_front' ), 10, 2 );
			add_filter( 'bp_get_group_class',             array( $this, 'sticky_class' ),              10, 1 );
			
			add_action( 'bp_enqueue_scripts',             array( $this, 'enqueue_style'             ), 10    );
		}

		// translation
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 6 );

		if( is_admin() ) {

			if( !empty( $this->enabled ) && !empty( $this->bp_version_ok ) ) {
				add_filter( 'bp_groups_admin_row_class',  array( $this, 'sticky_class'              ), 10, 2 );
				add_action( 'bp_groups_admin_meta_boxes', array( $this, 'group_admin_ui_screen'     )        );
				add_action( 'bp_group_admin_edit_after',  array( $this, 'group_admin_ui_screen_save'), 10, 1 );
				add_action( 'bp_groups_admin_load',       array( $this, 'enqueue_style'             ), 10    );
			}

			if( empty( $this->bp_version_ok ) )
				add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', array( $this, 'needs_bp_upgrade' ) );
			else
				add_action( 'bp_register_admin_settings', array( $this, 'sticky_setting_field' ) );
		}
	}

	public function needs_bp_upgrade() {
		?>
		<div id="message" class="updated fade">
			<p><?php printf( __( 'BP Sticky Groups version %s requires at least <strong>BuddyPress %s</strong>, please upgrade', 'bp-sticky-groups' ), $this->version, $this->bp_version_required );?></p>
		</div>
		<?php
	}

	/**
	 * Enqueues a little stylesheet to make the title of sticky groups different than regular ones
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses trailingslashit() to add a final slash to url/path
	 * @uses get_stylesheet_directory() to get child theme directory
	 * @uses get_stylesheet_directory_uri() to get child theme url
	 * @uses get_template_directory() to get parent theme directory
	 * @uses get_template_directory_uri() to get parent theme url
	 * @uses wp_enqueue_style() to finally load the best css file
	 */
	public function enqueue_style() {
		
		$file = 'css/bp-sticky-groups.css';
		
		// Check child theme
		if ( file_exists( trailingslashit( get_stylesheet_directory() ) . $file ) ) {
			$location = trailingslashit( get_stylesheet_directory_uri() ) . $file ; 
			$handle   = 'bp-sticky-groups-child';

		// Check parent theme
		} elseif ( file_exists( trailingslashit( get_template_directory() ) . $file ) ) {
			$location = trailingslashit( get_template_directory_uri() ) . $file ;
			$handle   = 'bp-sticky-groups-parent';

		// use our style
		} else {
			$location = $this->plugin_url . $file;
			$handle   = 'bp-sticky-groups';
		}
		
		wp_enqueue_style(  $handle, $location, false, $this->version );
	}

	/**
	 * Loads a new meta box in single group Admin UI
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses add_meta_box() to register the meta box
	 * @uses get_current_screen() to get the current screen object
	 */
	public function group_admin_ui_screen() {
		
		add_meta_box( 
			'bp_sticky_groups_admin_meta_box', 
			_x( 'Sticky Group', 'group admin edit screen', 'bp-sticky-groups' ), 
			array( &$this, 'group_admin_ui_metabox'), 
			get_current_screen()->id, 
			'side', 
			'high' 
		);
		
	}

	/**
	 * Displays the meta box in single group Admin UI
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @param BP_Groups_Group $group the group object
	 * @uses groups_get_groupmeta() to get the sticky option for the group
	 * @uses checked() to eventually add a checked attribute
	 */
	public function group_admin_ui_metabox( $group = false ) {

		$group_id = !empty( $group->id ) ? $group->id : false;

		if( empty( $group_id ) )
			return;

		$sticky = groups_get_groupmeta( $group_id, 'sticky' );
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php _e( 'Stick this group to top', 'bp-sticky-groups' );?></legend>
			<p><?php _e( 'Stick this group to the top of groups directory front page?', 'bp-sticky-groups' ); ?></p>

			<div class="field-group">

				<?php if( 'hidden' != $group->status ):?>
					<div class="checkbox">
						<label for="group-stick-group">
							<input type="checkbox" name="group-stick-group" id="group-stick-group" <?php checked( $sticky ); ?> /> 
							<?php _e( 'Yes', 'bp-sticky-groups' ) ?>
						</label>
					</div>
				<?php else:?>
					<p class="description"><?php _e( 'Hidden groups cannot be sticked to front', 'bp-sticky-groups' ); ?></p>
				<?php endif;?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Displays the meta box in single group Admin UI
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @param integer $group_id the group id
	 * @uses groups_update_groupmeta() to save the sticky option for the group
	 * @uses groups_delete_groupmeta() to eventually delete the sticky option for the group
	 */
	public function group_admin_ui_screen_save( $group_id = 0 ) {
		if( empty( $group_id ) )
			return;

		$sticky   = ( isset( $_POST['group-stick-group'] ) ) ? 1 : 0; 
 	    
 	    if( !empty( $sticky ) ) 
 		    groups_update_groupmeta( $group_id, 'sticky', 1 ); 
 		
 		else 
 		    groups_delete_groupmeta( $group_id, 'sticky' ); 
	}

	/**
	 * Displays the meta box in single group Admin UI
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @global $wpdb the WordPress db API
	 * @param string $query the regular group query
	 * @param array $sql_arg the query exploded in an array
	 * @uses wp_parse_id_list() to build an array of ids out of a comma separated string
	 * @uses BP_Sticky_Groups::convert_order_to_type() to convert sql order to group loop one
	 * @uses BP_Groups_Group::get_group_extras() to populate stickies with extra datas
	 * @return string $query the altered query
	 */
	public function alter_bp_groups_query( $query = '', $sql_args = array() ) {
		global $wpdb;
		$bp = buddypress();

		if( empty( $sql_args ) || !is_array( $sql_args ) ) 
 			return false;

		$sticky_sql = array();
		$order_fishing = array_diff_key(
			$sql_args,
			array( 
				'select'     => '',
				'from'       => '',
				'group_from' => '',
				'where'      => '',
				'hidden'     => '',
				'pagination' => '',
				'exclude'    => '',
			)
		);

		// No Stickies in case of search
		if ( ! empty( $sql_args['search'] ) ) 
 			return $query; 
 	
 		// In case of restricting the group query to limited groups, we don't need stickies 
 		if ( ! empty( $sql_args['include'] ) ) 
 			return $query;

 		// No stickies in case of meta query
 		if ( ! empty( $sql_args['meta'] ) ) 
 			return $query;
 		
 		// We only play in Group Directory so a user id tells us we're no on groups directory 
 		if ( ! empty( $sql_args['members_from'] ) ) 
 			return $query; 

 		// Adding the sticky type to $groups_template->group->sticky
 		$sticky_sql['select']     = $sql_args['select'] . ', 1 as sticky';

 		$sticky_sql['from']       = $sql_args['from'] . " {$bp->groups->table_name_groupmeta} gms,";
 		$sticky_sql['group_from'] = $sql_args['group_from'];
 		$sticky_sql['where']      = $sql_args['where'] . " AND g.id = gms.group_id AND gms.meta_key = 'sticky'";

 		// hidden can't be sticky
 		$sticky_sql['hidden']     = " AND g.status != 'hidden'";

 		if( !empty( $sql_args['exclude'] ) ) {
 			preg_match( '/AND g.id NOT IN \((.*)\)/', $sql_args['exclude'], $matches );
 			$sticky_sql['exclude'] = $sql_args['exclude'];
 		}
 			
 		// Will be used later to rebuild the query
 		$exclude = !empty( $matches[1] ) ? wp_parse_id_list( $matches[1] ): array();

 		// Trying to rebuild Order for stickies else default to last active
 		$sticky_sql['order'] = count( $order_fishing ) == 1 ? implode( '', $order_fishing ) : 'ORDER BY last_activity DESC';
 		
 		// offset is per page is max number of sticky groups
 		$offset = explode( ',', str_replace( array( 'LIMIT', ' '), '', $sql_args['pagination'] ) );
 		$offset = array_map( 'intval', $offset);

 		$sticky_sql['pagination'] = "LIMIT {$offset[1]}";

 		// Time for a sticky request !
		$sticky_group = $wpdb->get_results( join( ' ', (array) $sticky_sql ) );

		if( empty( $sticky_group ) || !is_array( $sticky_group ) || count( $sticky_group ) < 1 )
			return $query;

		// sticky request will be run another time if page is full of stickies and we're on front page
		if( count( $sticky_group ) == $offset[1] && 0 == $offset[0] ) {
			return join( ' ', (array) $sticky_sql );
		}

		$sticky_ids = array();
		// Excluding the stickies from groups query adding their ids to excluded ones
		foreach( $sticky_group as $sticky ) {
			$sticky_ids[] = $sticky->id;
			$exclude[]    = $sticky->id;
		}

		$type = self::convert_order_to_type( $sticky_sql['order'] );

		/*
		The stickies will be globalized in buddypress()->groups->extend->bp_sticky_groups->stickies
		in order to use it later to add them on top of groups.

		we default to populate extras as we need it in groups directory
		*/
		$this->stickies = BP_Groups_Group::get_group_extras( $sticky_group, $sticky_ids, $type );

		$exclude = implode( ',', $exclude );
		$exclude_arg = " AND g.id NOT IN ({$exclude})";

		// we first exclude the stickies to put them on front later
		if( empty( $sql_args['exclude'] ) )
			$sql_args['where'] .= $exclude_arg;
		else
			$sql_args['exclude'] = $exclude_arg;

		// tweaking pagination
		if( 0 == $offset[0] )
			$sql_args['pagination'] = 'LIMIT 0, ' . intval( $offset[1] - count( $this->stickies ) );
		
		else
			$sql_args['pagination'] = 'LIMIT ' . intval( $offset[0] - count( $this->stickies ) ) . ', '. $offset[1] ;

		
 		$query = join( ' ', (array) $sql_args );

		return $query;
	}

	/**
	 * Converts a sql order into a groups loop one
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @param string $order the sql order clause
	 * @return string $type the groops loop order
	 */
	protected function convert_order_to_type( $order = '' ) {
		$order = str_replace( array( 'ORDER BY ', ' DESC', ' ASC' ), '', $order );
		$type = '';

		switch ( $order ) {
			case 'g.date_created' :
				$type = 'newest';
				break;

			case 'last_activity' :
				$type = 'active';
				break;

			case 'CONVERT(gm1.meta_value, SIGNED)' :
				$type = 'popular';
				break;

			case 'g.name' :
				$type = 'alphabetical';
				break;

			case 'rand()' :
				$type = 'random';
				break;
		}

		return $type;
	}

	/**
	 * Sticks on top of front groups directory page the sticky groups
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @param array $groups the list of groups
	 * @param array $loop_args the arguments of groups_get_groups()
	 * @return array $groups sticky groups merged with regular ones
	 */
	public function prepend_stickies_on_front( $groups = array(), $loop_args = array() ) {

		if( !empty( $loop_args['user_id'] ) )
			return $groups;

		if( !empty( $loop_args['include'] ) )
			return $groups;

		if( !empty( $loop_args['search_terms'] ) )
			return $groups;

		if( !empty( $loop_args['meta_query'] ) )
			return $groups;

		if( $loop_args['page'] > 1 )
			return $groups;

		if( empty( $this->stickies ) )
			return $groups;

		$groups['groups'] = array_merge( $this->stickies, $groups['groups'] );

		return $groups;
	}

	/**
	 * Adds a sticky-group class to li or tr container
	 *
	 * @package BP Sticky Groups
	 * @since 1.0.3
	 */
	public function sticky_class( $group_classes = array(), $group_id = 0 ) {
		global $groups_template;

		if( empty( $groups_template->group ) )
			return $group_classes;

		if( !empty( $group_id ) ) {
			$groups_admin = $groups_template->groups;

			foreach( $groups_template->groups as $group ) {
				if( $group->id == $group_id && !empty( $group->sticky ) )
					$group_classes[] = 'sticky-group';
			}

		} else if ( !empty(  $groups_template->group->sticky ) ) {
			$group_classes[] = 'sticky-group';
		}

		return $group_classes;
	}

	/**
	 * Loads the translation
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 * 
	 * @uses get_locale()
	 * @uses load_textdomain()
	 */
	public function load_textdomain() {
		// try to get locale
		$locale = apply_filters( 'bp_sticky_groups_load_textdomain_get_locale', get_locale() );

		// if we found a locale, try to load .mo file
		if ( !empty( $locale ) ) {
			// default .mo file path
			$mofile_default = sprintf( '%s/languages/%s-%s.mo', $this->plugin_dir, $this->domain, $locale );
			// final filtered file path
			$mofile = apply_filters( 'bp_sticky_groups_textdomain_mofile', $mofile_default );
			// make sure file exists, and load it
			if ( file_exists( $mofile ) ) {
				load_textdomain( $this->domain, $mofile );
			}
		}
	}

	/**
	 * Register a new BuddyPress setting for sticky groups
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses  bp_is_active() to check the group component is active
	 * @uses add_settings_field() to add the setting field & his callback function
	 * @uses register_setting() to register it
	 */
	public function sticky_setting_field() {
		if ( bp_is_active( 'groups' ) ) {

			// Allow avatar uploads
			add_settings_field( 
				'bp-sticky-groups-enabled', 
				__( 'Sticky groups', 'bp-sticky-groups' ), 
				array( &$this, 'sticky_setting_field_callback' ),   
				'buddypress', 
				'bp_groups' 
			);

			register_setting( 'buddypress', 'bp-sticky-groups-disabled', 'intval' );

		}
	}

	/**
	 * Displays the setting field for sticky groups
	 *
	 * @package BP Sticky Groups
	 * @since 1.0
	 *
	 * @uses checked() to eventually add a checked attribute
	 */
	public function sticky_setting_field_callback() {
	?>

		<input id="bp-sticky-groups-enabled" name="bp-sticky-groups-enabled" type="checkbox" value="1" <?php checked( $this->enabled ); ?> />
		<label for="bp-sticky-groups-enabled"><?php _e( 'Enable sticky groups in Groups Directory', 'bp-sticky-groups' ); ?></label>
		<p class="description"><?php _e( 'Administrators can set groups as sticky from the Group Admin UI', 'bp-sticky-groups' ); ?></p>

	<?php
	}

}

/**
 * Main BP Sticky Groups Function
 *
 * Loads the plugin once BuddyPress is fully loaded 
 *
 * @package BP Sticky Groups
 * @since 1.0
 *
 * @uses buddypress() to get BuddyPress main instance
 * @uses bp_is_active() to check the group component is active
 * @uses BP_Sticky_Groups::instance() to attach globals to BuddyPress instance
 */
function bp_sticky_groups_loader() {
	$bp =  buddypress();

	if( bp_is_active( 'groups' ) ) {

		if( empty( $bp->groups->extend ) )
			$bp->groups->extend = new stdClass();

		$bp->groups->extend->bp_sticky_groups = BP_Sticky_Groups::instance();
	}

}


add_action( 'bp_include', 'bp_sticky_groups_loader' );

endif;
