<div class="wrap">
<h2>Send to Statsd</h2>
<form action="options.php" method="POST" accept-charset="utf-8">
  <?php
  do_settings_sections( 'send-to-statsd' );
  foreach( $sts_defaults as $section => $fields ) {
    settings_fields( 'send-to-statsd' );
  }
  ?>
  <?php submit_button(); ?>
</form>
</div>
