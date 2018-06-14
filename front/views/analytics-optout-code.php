<?php
/**
 * Copyright 2018 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
?>
<script>
var gainwpDnt = false;
var gainwpProperty = '<?php echo $data['uaid']?>';
var gainwpDntFollow = <?php echo $data['gaDntOptout'] ? 'true' : 'false'?>;
var gainwpOptout = <?php echo $data['gaOptout'] ? 'true' : 'false'?>;
var disableStr = 'ga-disable-' + gainwpProperty;
if(gainwpDntFollow && (window.doNotTrack === "1" || navigator.doNotTrack === "1" || navigator.doNotTrack === "yes" || navigator.msDoNotTrack === "1")) {
	gainwpDnt = true;
}
if (gainwpDnt || (document.cookie.indexOf(disableStr + '=true') > -1 && gainwpOptout)) {
	window[disableStr] = true;
}
function gaOptout() {
	var expDate = new Date;
	expDate.setFullYear(expDate.getFullYear( ) + 10);
	document.cookie = disableStr + '=true; expires=' + expDate.toGMTString( ) + '; path=/';
	window[disableStr] = true;
}
</script>
