<?php
/**
 *
 * @link              https://profiles.wordpress.org/gusruss89/
 * @since             1.0.0
 * @package           Cf7_Live_Preview
 *
 * @wordpress-plugin
 * Plugin Name:       Contact Form 7 Live Preview
 * Description:       Live preview your CF7 forms without leaving the form editor.
 * Version:           0.1.0
 * Author:            Angus Russell
 * Author URI:        https://profiles.wordpress.org/gusruss89/
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

  /**
   * Constructor - add hooks here and define shortcode
   */
  function __construct() {

    // Debug
    //add_action( 'init', function() { echo '<pre>REQUEST' . print_r($_REQUEST, true ) . 'POST' . print_r($_POST, true) . '</pre>';});

    // Register activation/deactivation hooks
    register_activation_hook( __FILE__, array( $this, 'activate' ) );
    register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    
    // Add scripts and styles
    add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts_and_styles' ) );
    add_action( 'wp_enqueue_scripts', array( $this, 'add_frontend_scripts' ) );

    // Admin notice for activation error
    add_action( 'admin_notices', array( $this, 'alertNoPreviewPost' ) );

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
    add_action( 'admin_post_cf7lp_update_preview', array( $this, 'save_preview' ) );

  }


  /**
   * Activation hook
   */
  static function activate() {

    // Default options
    add_option( 'cf7lp_autoreload', 1 );
    add_option( 'cf7lp_entirepage', 0 );
    add_option( 'cf7lp_background', '#ffffff' );
    add_option( 'cf7lp_preview_post_id' );

    // Create preview post
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
   * Deactivation hook
   */
  static function deactivate() {

    // Delete the preview post
    $preview_post_id = $this->get_preview_ID();
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
      'cf7md_ad' => $this->get_cf7md_ad_html(),
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
      $message = __( 'Contact Form 7 Live Preview will not work because a new form could not be created on activation. Try deactivating and re-activating. If you see this message again, please let me know on the support forums.', 'cf7-live-preview' );

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
          <input id="cf7lp-background" class="cf7lp-option" name="cf7lp-background" type="text" data-option="background" value="<?php echo $background; ?>">
          <label for="cf7lp-background">
            Background
            <span class="dashicons dashicons-editor-help" title="Change the background colour to match your page."></span>
          </label>
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
   * Get CF7MD Ad HTML
   */
  private function get_cf7md_ad_html() {
    $plugin_name = 'material-design-for-contact-form-7';
    $url = esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_name ) );

    ob_start();

		?>
		<div id="cf7lp-cf7md-ad" class="cf7lp-cf7md-ad postbox">
      <h3>Like the live preview?</h3>
      <div class="inside">
        <p>You might like my other plugin, Material Design for Contact Form 7. Add Google's "Material Design" style to your forms to make your website feel as responsive and interactive as an app.</p>
        <a href="<?php echo $url; ?>" target="_blank">More info</a>
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
   * Deprecated: Ajax endpoint for updating the preview post
   * This is a first attempt at manually updating the preview form
   * It has been replaced by the more elegant save_preview
   */
  public function update_preview() {
    if ( ! isset( $_POST['formData'] ) )
      return;

    echo '<pre>' . print_r( $_POST['formData']['wpcf7-messages'], true ) . '</pre>';
    wp_die();

    $form_data = $_POST['formData'];
    $preview_post_id = $this->get_preview_ID();
    $messages = array();

    foreach( $form_data as $field ) {

      // Update main form
      if( 'wpcf7-form' === $field['name'] ) {
        update_post_meta( $preview_post_id, '_form', wp_kses_post( $field['value'] ) );

      // Update additional settings
      } else if( 'wpcf7-additional-settings' === $field['name'] ) {
        $additional_settings = "demo_mode: on\r\n" . $field['value'];
        update_post_meta( $preview_post_id, '_additional_settings', $additional_settings );

      // Build messages array
      } else if( strpos( $field['name'], 'wpcf7-messages' ) === 0 ) {
        preg_match( "/\[([^\]]*)\]/", $field['name'], $msg );
        $messages[$msg[1]] = $field['value'];
      }
      
    }

    // Update messages
    update_post_meta( $preview_post_id, '_messages', $messages );

    echo 'Success';
    wp_die();
  }


  /**
   * Save preview
   */
  public function save_preview() {
    if( ! isset( $_POST['wpcf7-form'] ) ) {
      echo 'Something went wrong';
      wp_die();
    }

    // This stuff is based on wpcf7_load_contact_form_admin
    // function in contact-form-7/admin/admin.php 
    $args = array();
    $args['id'] = $this->get_preview_ID();

    $args['locale'] = isset( $_POST['wpcf7-locale'] )
			? $_POST['wpcf7-locale'] : null;

		$args['form'] = isset( $_POST['wpcf7-form'] )
			? $_POST['wpcf7-form'] : '';

    $args['messages'] = isset( $_POST['wpcf7-messages'] )
      ? $_POST['wpcf7-messages'] : array();
      
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
   * Deprecated: Ajax endpoint for getting the form content from the preview post
   */
  public function get_preview() {
    $preview_post_id = $this->get_preview_ID();
    $html = '[contact-form-7 id="' . $preview_post_id . '"]';
    echo apply_filters( 'the_content', $html );
    wp_die();
  }


  /**
   * Ajax endpoint for updating options
   */
  public function update_option() {
    if ( ! isset( $_POST['option'] ) || ! isset( $_POST['value'] ) )
      return;
    
    update_option( 'cf7lp_' . $_POST['option'], $_POST['value'] );
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
  public function hide_admin_bar() {
    if ( isset( $_GET['cf7lp-preview']) ) {
      return false;
    }
    return true;
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
if( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
  $cf7_live_preview = new CF7_Live_Preview();
}