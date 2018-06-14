<?php
/**
 * Copyright 2017 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
?>
<!-- BEGIN GAINWP v<?php echo GAINWP_CURRENT_VERSION; ?> Tag Manager - https://intelligencewp.com/google-analytics-in-wordpress/ -->
<script>
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push(<?php echo $data['vars']; ?>);
</script>

<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
	new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
	j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
	'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
	})(window,document,'script','dataLayer','<?php echo $data['containerid']; ?>');
</script>
<!-- END GAINWP Tag Manager -->

