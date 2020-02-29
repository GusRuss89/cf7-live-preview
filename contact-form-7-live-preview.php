<?php
/**
 *
 * @link              https://profiles.wordpress.org/contactform7addons/
 * @since             1.0.0
 * @package           Cf7_Live_Preview
 *
 * @wordpress-plugin
 * Plugin Name:       Live Preview for Contact Form 7
 * Description:       Live preview your CF7 forms without leaving the form editor.
 * Version:           1.2.0
 * Author:            Addons for Contact Form 7
 * Author URI:        https://profiles.wordpress.org/contactform7addons/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cf7-live-preview
 * Domain Path:       /languages
 */

// don't load directly
if (!defined('ABSPATH')) die('-1');

/**
 * Main plugin class
 */
class CF7_Live_Preview {

  private $preview_post_id;
  private $cf7md_active;
  private $cf7_active;

  /**
   * Constructor - add hooks here and define shortcode
   */
  function __construct() {

    // Debug
    //add_action( 'init', function() { echo '<pre>REQUEST' . print_r($_REQUEST, true ) . 'POST' . print_r($_POST, true) . '</pre>';});

    // Set members
    $this->cf7_active = is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
    $this->cf7md_active = is_plugin_active( 'material-design-for-contact-form-7/cf7-material-design.php' ) || is_plugin_active( 'cf7-material-design/cf7-material-design.php');

    // Register activation/deactivation hooks
    register_activation_hook( __FILE__, array( $this, 'activate' ) );
    register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    
    if( $this->cf7_active ) {

      // Add scripts and styles
      add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts_and_styles' ) );
      add_action( 'wp_enqueue_scripts', array( $this, 'add_frontend_scripts' ) );

      // Admin notice for activation error
      add_action( 'admin_notices', array( $this, 'alertNoPreviewPost' ) );

      // Ensure preview post is set on init
      add_action( 'init', array( $this, 'create_preview_post' ) );

      // Set the template for preview pane
      add_filter( 'template_include', array( $this, 'preview_template' ) );

      // Don't show admin bar in preview pane
      add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );

      // Don't show preview post on forms screen
      add_action( 'pre_get_posts', array( $this, 'hide_preview_post' ) );

      // Ajax endpoints
      //add_action( 'wp_ajax_cf7lp_update_preview', array( $this, 'update_preview' ) );
      //add_action( 'wp_ajax_cf7lp_get_preview', array( $this, 'get_preview' ) );
      add_action( 'wp_ajax_cf7lp_update_option', array( $this, 'update_option' ) );
      add_action( 'wp_ajax_cf7lp_update_preview', array( $this, 'save_preview' ) );

    }

  }


  /**
   * Activation hook
   */
  static function activate() {

    // Default options
    add_option( 'cf7lp_autoreload', 1 );
    add_option( 'cf7lp_entirepage', 0 );
	add_option( 'cf7lp_background', '#ffffff' );
	add_option( 'cf7lp_width', '800' );
	add_option( 'cf7lp_preview_post_id' );

  }


  /**
   * Deactivation hook
   */
  static function deactivate() {

    // Delete the preview post
    $preview_post_id = get_option( 'cf7lp_preview_post_id' ); // Can't use $this here
    wp_delete_post( $preview_post_id, true );

  }


  /**
   * Add scripts and styles
   */
  public function add_scripts_and_styles( $hook ) {

    // Scripts
    // @see https://codex.wordpress.org/Function_Reference/wp_register_script
    wp_register_script( 'cf7-live-preview-js', plugins_url( './assets/js/cf7-live-preview.js', __FILE__ ), array('wp-color-picker'), '1.0', true );
    
    // Localize the script with the html
    $localize = array(
      'live_preview_metabox' => $this->get_metabox_html(),
      'sidebar_metabox' => $this->get_sidebar_metabox(),
      'preview_form_id' => strval( $this->get_preview_ID() ),
      'admin_post_url' => admin_url( 'admin-post.php' )
    );
    wp_localize_script( 'cf7-live-preview-js', 'cf7lp_data', $localize );
    
    // Styles
    // @see https://codex.wordpress.org/Function_Reference/wp_register_style
    wp_register_style( 'cf7-live-preview-css', plugins_url( './assets/css/cf7-live-preview.css', __FILE__ ) );

    // Only load on ?page=wpcf7&post=??
    if( strpos( $hook, 'wpcf7' ) !== false && isset( $_GET['post'] ) ) {
      wp_enqueue_script( 'cf7-live-preview-js');
      wp_enqueue_style( 'cf7-live-preview-css');
      wp_enqueue_style( 'wp-color-picker' );
    }

  }


  /**
   * Create preview post
   */
  public function create_preview_post() {
    if( !is_admin() )
      return;

    if( ! is_null( get_post( get_option( 'cf7lp_preview_post_id' ) ) ) )
      return;

    $preview_post_id = wp_insert_post(array(
      'post_type' => 'wpcf7_contact_form',
      'post_title' => 'CF7 Live Preview (Do not use or delete this form)'
    ));
    if ( is_wp_error( $preview_post_id ) ) {
      set_transient( 'cf7lp_notice_no_preview_post', true, 60 );
    } else {
      update_option( 'cf7lp_preview_post_id', $preview_post_id );
    }

  }


  /**
   * Add frontend scripts and styles
   */
  public function add_frontend_scripts() {
    if ( isset( $_GET['cf7lp-preview'] ) ) {
      wp_enqueue_script( 'cf7-live-preview-iframe', plugins_url( './assets/js/cf7-live-preview-iframe.js', __FILE__ ), array(), '1.0', true );

      $localize = array(
        'ajax_url' => admin_url( 'admin-ajax.php' )
      );
      wp_localize_script( 'cf7-live-preview-iframe', 'ajax_object', $localize );
    }
  }


  /**
   * Couldn't create preview post alert
   */
  public function alertNoPreviewPost() {
    if ( get_transient( 'cf7lp_notice_no_preview_post' ) ) {
      $class = 'notice notice-error';
      $message = __( 'Contact Form 7 Live Preview will not work because a new form could not be created. This should never happen. If you see this message, please let me know on the support forums.', 'cf7-live-preview' );

      printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
      delete_transient( 'cf7lp_notice_no_preview_post' );
    }
  }


  /**
   * Metabox html
   */
  private function get_metabox_html() {
    ob_start();

    $iframe_url = add_query_arg( array(
      'cf7lp-preview' => 1
    ), get_site_url() );

    $autoreload = get_option( 'cf7lp_autoreload' );
    $entirepage = get_option( 'cf7lp_entirepage' );
	$background = get_option( 'cf7lp_background' );
	$width      = get_option( 'cf7lp_width' );

    ?>
    <div id="cf7lp-metabox" class="cf7lp-metabox">
      <h2 class="cf7lp-title">Live Preview</h2>
      <div class="cf7lp-toolbar">
        <div class="cf7lp-toolbar-item">
          <a id="cf7lp-refresh" href="#">Force reload</a>
        </div>
        <div class="cf7lp-toolbar-item">
          <input id="cf7lp-autoreload" class="cf7lp-option" name="cf7lp-autoreload" type="checkbox" data-option="autoreload" value="1" <?php checked( $autoreload, 1 ) ?>>
          <label for="cf7lp-autoreload">
            Auto reload
            <span class="dashicons dashicons-editor-help" title="Auto-refresh the preview as changes are made."></span>
          </label>
        </div>
        <?php /*
        <div class="cf7lp-toolbar-item">
          <input id="cf7lp-entirepage" class="cf7lp-option" name="cf7lp-entirepage" type="checkbox" data-option="entirepage" value="1" <?php checked( $entirepage, 1 ) ?>>
          <label for="cf7lp-entirepage">Preview entire page</label>
        </div>
        */ ?>
        <div class="cf7lp-toolbar-item">
			<label for="cf7lp-background">
            Background
            <span class="dashicons dashicons-editor-help" title="Change the background colour to match your page."></span>
          </label>
		  <input id="cf7lp-background" class="cf7lp-option" name="cf7lp-background" type="text" data-option="background" value="<?php echo esc_attr( $background ); ?>">
        </div>
		<div class="cf7lp-toolbar-item">
			<label for="cf7lp-width">Width</label>
			<input type="range" min="320" max="1024" class="cf7lp-option" value="<?php echo esc_attr( $width ); ?>" data-option="width" id="cf7lp-width" />
		</div>
      </div>
      <iframe id="cf7lp-preview" class="cf7lp-iframe" src="<?php echo $iframe_url; ?>"></iframe>
      <div class="cf7lp-explainer">
        <span class="dashicons dashicons-editor-help"></span>
        <p>The live preview loads your site's scripts and styles as long as they're enqueued via <a href="https://codex.wordpress.org/Plugin_API/Action_Reference/wp_enqueue_scripts" target="_blank">hooks</a>. If it looks wrong for you, make sure your scripts and styles are loaded correctly. You can test the validation and success messages but no emails will be sent.</p>
      </div>
      <div id="cf7lp-debug"></div>
    </div>
    <?php            
        
    $return = ob_get_contents();
    ob_end_clean();

    return $return;
  }


  /**
   * Sidebar metabox HTML
   */
  private function get_sidebar_metabox() {
    $plugin_name = 'material-design-for-contact-form-7';
    $url = esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_name ) );

    ob_start();

    ?>
    <div id="cf7lp-cf7md-ad" class="cf7lp-cf7md-ad postbox">
      <h3>CF7 Live Preview</h3>
      <div class="inside">
        <p>Like this plugin?<br><a href="https://wordpress.org/support/plugin/cf7-live-preview/reviews/?rate=5#new-post" target="_blank">Rate it &rarr;</a></p>
        <p>Having problems?<br><a href="https://wordpress.org/support/plugin/cf7-live-preview" target="_blank">Get support &rarr;</a></p>
        <?php if( ! $this->cf7md_active ) : ?>
          <p>If you like Contact Form 7 Live Preview, you might like my other plugin, Material Design for Contact Form 7. Add Google's "Material Design" style to your forms to make your website feel as responsive and interactive as an app.<br><a href="<?php echo $url; ?>" target="_blank">More info &rarr;</a></p>
        <?php endif; ?>
      </div>
    </div>
    <?php            
        
    $return = ob_get_contents();
    ob_end_clean();

    return $return;
  }


  /**
   * Use the preview template
   */
  public function preview_template( $original_template ) {
    if ( isset( $_GET['cf7lp-preview']) ) {
      return plugin_dir_path( __FILE__ ) . 'preview-template.php';
    } else {
      return $original_template;
    }
  }


  /**
   * Save preview
   */
  public function save_preview() {		
	if( ! isset( $_POST['wpcf7-form'] ) ) {
      echo 'Something went wrong';
      wp_die();
	}

	// Get post ID
	$post_id = $_POST['post_ID'];

	// Check user permissions
	if( ! current_user_can( 'wpcf7_edit_contact_form', $post_id ) ) {
		wp_die( __( 'You are not allowed to edit this item.', 'contact-form-7' ) );
	}

	// Verify CF7's nonce field
	check_ajax_referer( 'wpcf7-save-contact-form_' . $post_id );

    // This stuff is based on wpcf7_load_contact_form_admin
	// function in contact-form-7/admin/admin.php
	// Sanitization is handled by CF7
    $args = array();
    $args['id'] = $this->get_preview_ID();

    $args['title'] = isset( $_POST['post_title'] )
		? $_POST['post_title'] : null;

	$args['locale'] = isset( $_POST['wpcf7-locale'] )
		? $_POST['wpcf7-locale'] : null;

	$args['form'] = isset( $_POST['wpcf7-form'] )
		? $_POST['wpcf7-form'] : '';

	$args['mail'] = isset( $_POST['wpcf7-mail'] )
		? $_POST['wpcf7-mail'] : array();

	$args['mail_2'] = isset( $_POST['wpcf7-mail-2'] )
		? $_POST['wpcf7-mail-2'] : array();

	$args['messages'] = isset( $_POST['wpcf7-messages'] )
		? $_POST['wpcf7-messages'] : array();
      
    // Add demo_mode: on to any additional settings
    $args['additional_settings'] = isset( $_POST['wpcf7-additional-settings'] )
      ? "demo_mode: on\r\n" . $_POST['wpcf7-additional-settings'] : 'demo_mode: on';
    
    $contact_form = wpcf7_save_contact_form( $args );
    
    if( $contact_form ) {
      echo 'Success';
    } else {
      echo 'Fail';
    }
    wp_die();
  }


  /**
   * Ajax endpoint for updating options
   */
  public function update_option() {
    if ( ! isset( $_POST['option'] ) || ! isset( $_POST['value'] ) )
      return;
    
    $option = 'cf7lp_' . sanitize_text_field( $_POST['option'] );
    $value = sanitize_text_field( $_POST['value'] );
    update_option( $option, $value );
    wp_die();
  }


  /**
   * Get preview ID
   */
  private function get_preview_ID() {
    if (! isset( $this->preview_post_id ) ) {
      $this->preview_post_id = get_option( 'cf7lp_preview_post_id' );
    }
    return $this->preview_post_id;
  }


  /**
   * Hide the admin bar on the preview page
   */
  public function hide_admin_bar( $show ) {
    if ( isset( $_GET['cf7lp-preview']) ) {
      $show = false;
    }
    return $show;
  }


  /**
   * Don't show the preview post on the forms page
   */
  public function hide_preview_post( $query ) {
    if ( is_admin() ) {
      $query->set( 'post__not_in', array( $this->get_preview_ID() ) );
    }
  }

}

// Finally initialize code, but only if CF7 is active
if( !function_exists('is_plugin_active') ) {
  include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$cf7_live_preview = new CF7_Live_Preview();
