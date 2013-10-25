<?php
/**
 * TWP_Setup class.
 *
 * Plugin setup class
 *
 * @package TheatreWP
 * @author  Jose Bolorino <jose.bolorino@gmail.com>
 */

if ( realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) )
	exit('Do not access this file directly.');

class TWP_Setup {

	protected static $plugin_dir;

	protected static $default_spectacles_number = 5;

	protected static $default_performances_number = 5;

	public $performance;

	public $spectacle;

	public static $default_options = array();
	/**
	 * List of available templates
	 * @var array
	 */
	public static $templates = array(
		'single-spectacle'    => 'single-spectacle.php',
		'single-performance'  => 'single-performance.php',
		'archive-spectacle'   => 'archive-spectacle.php',
		'archive-performance' => 'archive-performances.php'
		);

	public function __construct( $plugin_dir, $spectacle, $performance ) {
		self::$plugin_dir = $plugin_dir;

		self::$default_options = array(
			'twp_spectacle_name'      => __( 'Spectacle', 'theatrewp' ),
			'twp_spectacle_slug'      => sanitize_title_with_dashes( __( 'spectacle', 'theatrewp' ), false, 'save' ),
			'twp_performance_name'    => __( 'Performance', 'theatrewp' ),
			'twp_performance_slug'    => sanitize_title_with_dashes( __( 'performance', 'theatrewp' ), false, 'save' ),
			'twp_spectacles_number'   => self::$default_spectacles_number,
			'twp_performances_number' => self::$default_performances_number,
			'twp_clean_on_uninstall'  => false
		);

		$this->spectacle = $spectacle;
		$this->performance = $performance;

		// Actions
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Init Theatre WordPress plugin.
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		// Set up localisation @TODO
		// $this->load_plugin_textdomain();

		// Setup custom posts
		add_action( 'init', array( $this, 'create_spectacles' ) );
		add_action( 'init', array( $this, 'create_performances' ) );
		add_action( 'init', array( $this, 'twp_metaboxes' ) );

		// Filters
		// Default custom posts templates
		add_filter( 'single_template', array( $this, 'get_twp_single_template' ) );
		add_filter( 'archive_template', array( $this, 'get_twp_archive_template' ) );
		// Enable a different post_per_page param for custom post
		add_filter( 'option_posts_per_page', array( 'TWP_Setup', 'twp_option_post_per_page' ) );

		// Admin menu
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'TWP_Setup', 'twp_menu' ) );
			add_action( 'admin_init', array( 'TWP_Setup', 'twp_register_settings' ) );


			add_filter( 'manage_edit-performance_columns', array( 'TWP_Setup', 'twp_performances_columns' ) );
			add_action( 'manage_performance_posts_custom_column', array( $this, 'twp_manage_performances_columns' ), 10, 2);
		}

		// Widgets
		wp_register_sidebar_widget(
			'twp-show-spectacles',
			__( 'Spectacles', 'theatrewp' ),
			array( $this, 'widget_show_spectacles' ),
			array(
				'description' => __('Display a list of your spectacles', 'theatrewp')
			)
		);
		wp_register_widget_control(
			'twp-show-spectacles',
			__('Spectacles', 'theatrewp'),
			array ( $this, 'widget_show_spectacles_control' )
		);

		wp_register_sidebar_widget( 'twp-show-next-performances', __( 'Spectacle Next Performances', 'theatrewp' ), array( $this, 'widget_show_next_performances' ) );
		wp_register_sidebar_widget( 'twp-next-performances', __( 'Global Next Performances', 'theatrewp' ), array( $this, 'widget_next_performances' ) );
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    0.2
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		self::twp_register_settings();

		// Set default options
		foreach ( self::$default_options as $key => $value ) {
			update_option( $key, $value );
		}

		global $wp_rewrite;
		$wp_rewrite->flush_rules();

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    0.2
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		self::twp_unregister_settings();

		flush_rewrite_rules();
	}

	/**
	 * Define Spectacles custom post.
	 *
	 * @access public
	 * @return void
	 */
	public function create_spectacles() {
		$spectacles_args = array(
			'labels' => array(
				'name'          => __('Shows', 'theatrewp'),
				'singular_name' => __('Show', 'theatrewp'),
				'add_new'       => __('Add new', 'theatrewp'),
				'add_new_item'  => __('Add new Show', 'theatrewp'),
				'edit_item'     => __('Edit Show', 'theatrewp'),
				'new_item'      => __('New Show', 'theatrewp'),
				'view'          => __('View Show', 'theatrewp'),
				'view_item'     => __('View Show', 'theatrewp'),
				'search_items'  => __('Search Shows', 'theatrewp')
				),
			'singular_label'  => __('Show', 'theatrewp'),
			'public'          => true,
			'has_archive'     => true,
			'capability_type' => 'post',
			'show_ui'         => true,
			'rewrite'         => true,
			'menu_position'   => 5,
			'supports'        => array( 'title', 'editor', 'thumbnail' )
			);

		register_post_type( 'spectacle', $spectacles_args );

		return;
	}

	/**
	 * Define Performances custom post.
	 *
	 * @access public
	 * @return void
	 */
	public function create_performances() {
		$performances_args = array(
			'labels' => array(
				'name'          => __('Performances', 'theatrewp'),
				'singular_name' => __('Performance', 'theatrewp'),
				'add_new'       => __('Add new', 'theatrewp'),
				'add_new_item'  => __('Add new Performance', 'theatrewp'),
				'edit_item'     => __('Edit Performance', 'theatrewp'),
				'new_item'      => __('New Performance', 'theatrewp'),
				'view'          => __('View Performances', 'theatrewp'),
				'view_item'     => __('View Performance', 'theatrewp'),
				'search_items'  => __('Search Performance', 'theatrewp')
				),
			'singular_label'  => __('Performance', 'theatrewp'),
			'public'          => true,
			'has_archive'     => 'performances',
			'rewrite'         => true,
			'exclude_from_search' => false,
			'capability_type' => 'post',
			'menu_position'   => 6,
			'supports'        => array( 'title' )
			);

		register_post_type( 'performance', $performances_args );

		return;
	}

	/**
	 * Localisation.
	 *
	 * @access public
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'theatrewp' );

		load_plugin_textdomain('theatrewp', false, '/' . self::$plugin_dir . '/languages/' );
	}

	/**
	 * Get the path to the custom posts (spectacle/performance) single templates
	 *
	 * @TODO Avoid ../ in path
	 * @access public
	 * @return string
	 */
	public function get_twp_single_template( $template ) {

		if ( 'spectacle' == get_post_type( get_queried_object_id() ) && ! $this->_check_theme_templates(self::$templates['single-spectacle']) ) {
			$template = plugin_dir_path(__FILE__) . '../templates/single-spectacle.php';
		}

		if ( 'performance' == get_post_type( get_queried_object_id() ) && ! $this->_check_theme_templates(self::$templates['single-performance']) ) {
			$template = plugin_dir_path(__FILE__) . '../templates/single-performance.php';
		}

		return $template;

	}

	/**
	 * Get the path to the custom posts (spectacle/performance) archive templates
	 *
	 * @access public
	 * @return string
	 */
	public function get_twp_archive_template( $template ) {
		// Custom post archive pages
		if ( is_post_type_archive( 'performance' ) && ! $this->_check_theme_templates(self::$templates['archive-performance']) ) {
			$template = plugin_dir_path(__FILE__) . '../templates/archive-performances.php';
		}

		if ( is_post_type_archive( 'spectacle' ) && ! $this->_check_theme_templates(self::$templates['archive-spectacle']) ) {
			$template = plugin_dir_path(__FILE__) . '../templates/archive-spectacle.php';
		}

		return $template;
	}

	/**
	 * Checks if template files exists
	 *
	 * @access private
	 * @return bool
	 */
	private function _check_theme_templates( $template)  {
		if ( ! locate_template( $template, false ) ) {
			return false;
		}

		return true;
	}

	/**
	* Adding scripts and styles
	*
	* @access public
	* @return void
	*
	*/
	public static function twp_scripts( $hook ) {
		global $wp_version;

		// only enqueue our scripts/styles on the proper pages
		if ( 'post.php' == $hook || 'post-new.php' == $hook ) {
			$twp_script_array = array( 'jquery-ui-datepicker' );
			$twp_style_array = array( 'thickbox' );

			wp_register_script( 'twp-timepicker', TWP_META_BOX_URL . 'js/jquery.timePicker.min.js' );
			wp_register_script( 'twp-scripts', TWP_META_BOX_URL . 'js/twp.js', $twp_script_array, '0.9.1' );
			wp_localize_script( 'twp-scripts', 'twp_ajax_data', array( 'ajax_nonce' => wp_create_nonce( 'ajax_nonce' ), 'post_id' => get_the_ID() ) );
			wp_enqueue_script( 'twp-timepicker' );
			wp_enqueue_script( 'twp-scripts' );

			wp_register_style( 'twp-styles', TWP_META_BOX_URL . 'style.css', $twp_style_array );
			wp_enqueue_style( 'twp-styles' );
		}

		return true;
	}

	/**
	 * Performances dashboard columns
	 *
	 * @access public
	 * @return array
	 */
	public static function twp_performances_columns( $performance_columns ) {
		$new_columns['cb'] = '<input type="checkbox" />';

		$new_columns['id'] = __( 'ID' );
		$new_columns['title'] = _x('Performance', 'column name');
		$new_columns['spectacle'] = __( 'Spectacle' );
		$new_columns['first_date'] = __( 'First Date' );
		$new_columns['last_date'] = __( 'Last Date' );
		$new_columns['event'] = __( 'Event' );

		return $new_columns;
	}

	/**
	 * Performances dashboard columns data
	 *
	 * @access public
	 * @return void
	 */
	public function twp_manage_performances_columns( $column_name, $ID) {
		$meta = $this->performance->get_performance_custom( $this->spectacle, $ID );

		switch ( $column_name ) {
			case 'id':
				echo $ID;
				break;

			case 'spectacle':
				echo $meta['title'];
				break;

			case 'first_date':
				echo date( 'd-F-Y', $meta['date_first'] );
				break;

			case 'last_date':
				if ( ! empty( $meta['date_last'] ) && $meta['date_last'] != $meta['date_first'] ) {
					echo date( 'd-F-Y', $meta['date_last'] );
				}
				break;

			case 'event':
				echo $meta['event'];
				break;

			default:
				break;
		}

	}

	/**
	 * TWP Options menu
	 *
	 * @access public
	 * @return void
	 */
	public static function twp_menu() {
		add_options_page( __('Theatre WP Options', 'theatrewp'), 'Theatre WP', 'manage_options', 'theatre-wp', array( 'TWP_Setup', 'twp_options' ) );
	}

	/**
	 * Register Settings
	 *
	 * @access public
	 * @return void
	 */
	public static function twp_register_settings() {
		register_setting( 'twp-main', 'twp_spectacle_name' );
		register_setting( 'twp-main', 'twp_spectacle_slug' );
		register_setting( 'twp-main', 'twp_performance_name' );
		register_setting( 'twp-main', 'twp_performance_slug' );
		register_setting( 'twp-main', 'twp_spectacles_number', 'intval' );
		register_setting( 'twp-main', 'twp_performances_number', 'intval' );
		register_setting( 'twp-main', 'twp_clean_on_uninstall' );
	}

	/**
	 * Unregister Settings
	 *
	 * @access public
	 * @return void
	 */
	public static function twp_unregister_settings() {
		unregister_setting( 'twp-main', 'twp_spectacle_name' );
		unregister_setting( 'twp-main', 'twp_spectacle_slug' );
		unregister_setting( 'twp-main', 'twp_performance_name' );
		unregister_setting( 'twp-main', 'twp_performance_slug' );
		unregister_setting( 'twp-main', 'twp_spectacles_number', 'intval' );
		unregister_setting( 'twp-main', 'twp_performances_number', 'intval' );
		unregister_setting( 'twp-main', 'twp_clean_on_uninstall' );
	}

	/**
	 * Admin Options
	 *
	 * @access public
	 * @return void
	 */
	public static function twp_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.') );
		}

		include( plugin_dir_path( __FILE__ ) . '../templates/admin/admin-options.php' );
	}

	/**
	 * Filters post per page option for custom posts
	 *
	 * @access public
	 * @return int
	 */
	public static function twp_option_post_per_page( $value ) {
		global $option_posts_per_page;

		if ( is_tax( 'performance' ) ) {
			return get_option( 'twp_performances_number' );
		}

		if ( is_tax( 'spectacle' ) ) {
			return get_option( 'twp_spectacles_number' );
		}

		return $option_posts_per_page;
	}

	public function twp_metaboxes( ) {
		$TWP_meta_boxes = array(
			array(
				'id'       => 'spectacle-meta-box',
				'title'    => __('Spectacle Options', 'theatrewp'),
				'pages'    => array('spectacle'),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => array(
					array(
						'name' => __('Synopsis', 'theatrewp'),
						'desc' => __('Short description', 'theatrewp'),
						'id' => Theatre_WP::$twp_prefix . 'synopsis',
						'type' => 'textarea',
						'std' => ''
						),
					array(
						'name' => __('Audience', 'theatrewp'),
						'desc' => __('Intended Audience', 'theatrewp'),
						'id' => Theatre_WP::$twp_prefix . 'audience',
						'type' => 'select',
						'options' => TWP_Spectacle::$audience
						),
					array(
						'name' => __('Credits', 'theatrewp'),
						'desc' => __('Credits Titles', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'credits',
						'type' => 'wysiwyg',
						'std'  => ''
						),
					array(
						'name' => __('Sheet', 'theatrewp'),
						'desc' => __('Technical Sheet', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'sheet',
						'type' => 'textarea',
						'std'  => ''
						),
					array(
						'name' => __('Video', 'theatrewp'),
						'desc' => __('Video URL. The link to the video in YouTube or Vimeo', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'video',
						'type' => 'text',
						'std'  => ''
						)
					)
				),
			array (
				'id'       => 'performance-meta-box',
				'title'    => __('Performance Options', 'theatrewp'),
				'pages'    => array('performance'),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => array(
					array(
						'name'    => __('Show', 'theatrewp'),
						'desc'    => __('Performing Show', 'theatrewp'),
						'id'      => Theatre_WP::$twp_prefix . 'performance',
						'type'    => 'select',
						'options' => $this->spectacle->get_spectacles_titles()
						),
					array(
						'name' => __('First date', 'theatrewp'),
						'desc' => __('First performing date. [Date selection / Time]', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'date_first',
						'type' => 'text_datetime_timestamp',
						'std'  => ''
						),
					array(
						'name' => __('Last date', 'theatrewp'),
						'desc' => __('Last performing date. [Date selection / Time]', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'date_last',
						'type' => 'text_datetime_timestamp',
						'std'  => ''
						),
					array(
						'name' => __('Event', 'theatrewp'),
						'desc' => __('Event in which the show is performed (Festival, Arst Program...)', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'event',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Stage', 'theatrewp'),
						'desc' => __('Where is the Show to be played (Theatre)', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'place',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Theatre Address', 'theatrewp'),
						'desc' => '',
						'id'   => Theatre_WP::$twp_prefix . 'address',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Postal Code', 'theatrewp'),
						'desc' => '',
						'id'   => Theatre_WP::$twp_prefix . 'postal_code',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Town', 'theatrewp'),
						'desc' => __('Performing in this Town', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'town',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Region', 'theatrewp'),
						'desc' => __('e.g. Province, County...', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'region',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Country', 'theatrewp'),
						'desc' => '',
						'id'   => Theatre_WP::$twp_prefix . 'country',
						'type' => 'text',
						'std'  => ''
						),
					array(
						'name' => __('Display Map', 'theatrewp'),
						'desc' => __('Check to display map', 'theatrewp'),
						'id'   => Theatre_WP::$twp_prefix . 'display_map',
						'type' => 'checkbox',
						'std'  => ''
						)
					)
		)
		);

		foreach ( $TWP_meta_boxes as $meta_box ) {
		    $my_box = new TWP_Metaboxes( $meta_box );
		}

	}

	/**
	 * Spectacles Widget
	 *
	 * @access public
	 * @return void
	 */
	public function widget_show_spectacles( $args ) {

		$widget_title = get_option( 'twp_widget_spectacles_title' );
		$spectacles_number = get_option( 'twp_widget_spectacles_number' );

		if ( ! $spectacles = $this->spectacle->get_spectacles( $spectacles_number ) ) {
			return false;
		}

		extract( $args );

		echo $before_widget;

		echo $before_title . $widget_title . $after_title;

		echo $spectacles;

		echo $after_widget;

	}

	public function widget_show_spectacles_control( $args=array(), $params=array() ) {

		if ( isset( $_POST['submitted'] ) ) {
			update_option( 'twp_widget_spectacles_title', $_POST['widget_title'] );
			update_option( 'twp_widget_spectacles_number', intval( $_POST['number'] ) );
		}

		$widget_title = get_option( 'twp_widget_spectacles_title' );
		$spectacles_number = get_option( 'twp_widget_spectacles_number' );

		$output = '<p>'
		. '<label for="widget-show-spectacles-title">'
		. __( 'Title:' ) . '</label>'
		. '<input type="text" class="widefat" id="widget-show-spectacles-title" name="widget_title" value="' . stripslashes( $widget_title ) .'">'
		. '</p>'
		. '<p>'
		. '<label for="widget-show-spectacles-number">'
		. __( 'Number of spectacles to show (0 for all):', 'theatrewp' )
		. '</label>'
		. '<input type="text" size="3" value="' . $spectacles_number . '" id="widget-show-spectacles-number" name="number">'
		. '<input type="hidden" name="submitted" value="1">'
		. '</p>';

		echo $output;
	}

	/**
	 * Upcoming Performances Widget
	 *
	 * @access public
	 * @return void
	 */
	public function widget_next_performances( $args ) {
		if ( ! $performances = $this->performance->get_next_performances() ) {
			return false;
		}

		extract( $args );

		echo $before_widget;
		echo $before_title . __('Upcoming Performances', 'theatrewp') . $after_title;

		echo $performances;

		echo $after_widget;
	}

	/**
	 * Current Spectacle Upcoming Performances Widget
	 *
	 * @access public
	 * @return void
	 */
	public function widget_show_next_performances( $args ) {
		global $post;
		$current_category = get_post_type();

		if ( $current_category != 'spectacle' OR ! is_single() ) {
			return false;
		}

		$title = get_the_title( $post->ID );

		if ( ! $performances = $this->performance->get_show_next_performances() ) {
			return false;
		}

		extract( $args );

		echo $before_widget;
		echo $before_title . sprintf( __( '“%s” Next Performances', 'theatrewp' ), $title ) . $after_title;

		echo $performances;

		echo $after_widget;
	}
}