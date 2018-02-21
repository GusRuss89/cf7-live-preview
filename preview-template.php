<!doctype html>

<html <?php language_attributes(); ?>>

<head>
  <meta charset="utf-8">
  <title>Contact Form 7 Live Preview</title>

  <meta name="HandheldFriendly" content="True">
  <meta name="MobileOptimized" content="320">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <?php wp_head(); ?>
  <style type="text/css">
    .cf7md-admin-customize-message { display: none; }
  </style>
</head>

<body>
  <div id="form-container" style="padding: 30px;">
    <?php echo do_shortcode( '[contact-form-7 id="' . get_option( 'cf7lp_preview_post_id' ) . '"]' ); ?>
  </div>

  <?php wp_footer(); ?>

</body>

</html>

