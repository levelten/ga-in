<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit();

final class GAINWP_Frontend_Widget extends WP_Widget {

	private $gainwp;

	public function __construct() {
		$this->gainwp = GAINWP();

		parent::__construct( 'gainwp-frontwidget-report', __( 'Google Analytics', 'ga-in' ), array( 'description' => __( "Will display your google analytics stats in a widget", 'ga-in' ) ) );
		// Frontend Styles
		if ( is_active_widget( false, false, $this->id_base, true ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'load_styles_scripts' ) );
		}
	}

	public function load_styles_scripts() {
		$lang = get_bloginfo( 'language' );
		$lang = explode( '-', $lang );
		$lang = $lang[0];

		wp_enqueue_style( 'gainwp-front-widget', GAINWP_URL . 'front/css/widgets.css', null, GAINWP_CURRENT_VERSION );
		wp_enqueue_script( 'gainwp-front-widget', GAINWP_URL . 'front/js/widgets.js', array( 'jquery' ), GAINWP_CURRENT_VERSION );
		wp_enqueue_script( 'googlecharts', 'https://www.gstatic.com/charts/loader.js', array(), null );
	}

	public function widget( $args, $instance ) {
		$widget_title = apply_filters( 'widget_title', $instance['title'] );
		$title = __( "Sessions", 'ga-in' );
		echo "\n<!-- BEGIN GAINWP v" . GAINWP_CURRENT_VERSION . " Widget - https://intelligencewp.com/google-analytics-in-wordpress/ -->\n";
		echo $args['before_widget'];
		if ( ! empty( $widget_title ) ) {
			echo $args['before_title'] . $widget_title . $args['after_title'];
		}

		if ( isset( $this->gainwp->config->options['theme_color'] ) ) {
			$css = "colors:['" . $this->gainwp->config->options['theme_color'] . "','" . GAINWP_Tools::colourVariator( $this->gainwp->config->options['theme_color'], - 20 ) . "'],";
			$color = $this->gainwp->config->options['theme_color'];
		} else {
			$css = "";
			$color = "#3366CC";
		}
		ob_start();
		if ( $instance['anonim'] ) {
			$formater = "var formatter = new google.visualization.NumberFormat({
					  suffix: '%',
					  fractionDigits: 2
					});

					formatter.format(data, 1);";
		} else {
			$formater = '';
		}
		$periodtext = "";
		switch ( $instance['period'] ) {
			case '7daysAgo' :
				$periodtext = sprintf( __( 'Last %d Days', 'ga-in' ), 7 );
				break;
			case '14daysAgo' :
				$periodtext = sprintf( __( 'Last %d Days', 'ga-in' ), 14 );
				break;
			case '30daysAgo' :
				$periodtext = sprintf( __( 'Last %d Days', 'ga-in' ), 30 );
				break;
			default :
				$periodtext = "";
				break;
		}
		switch ( $instance['display'] ) {
			case '1' :
				echo '<div id="gainwp-widget"><div id="gainwp-widgetchart"></div><div id="gainwp-widgettotals"></div></div>';
				break;
			case '2' :
				echo '<div id="gainwp-widget"><div id="gainwp-widgetchart"></div></div>';
				break;
			case '3' :
				echo '<div id="gainwp-widget"><div id="gainwp-widgettotals"></div></div>';
				break;
		}
		?>
<script type="text/javascript">
	google.charts.load('current', {'packages':['corechart']});
	google.charts.setOnLoadCallback( GAINWPWidgetLoad );
	function GAINWPWidgetLoad (){
		jQuery.post("<?php echo admin_url( 'admin-ajax.php' ); ?>", {action: "ajax_frontwidget_report", gainwp_number: "<?php echo $this->number; ?>", gainwp_optionname: "<?php  echo $this->option_name; ?>" }, function(response){
			if (!jQuery.isNumeric(response) && jQuery.isArray(response)){
				if (jQuery("#gainwp-widgetchart")[0]){
					gainwpFrontWidgetData = response[0];
					gainwp_drawFrontWidgetChart(gainwpFrontWidgetData);
				}
				if (jQuery("#gainwp-widgettotals")[0]){
					gainwp_drawFrontWidgetTotals(response[1]);
				}
			}else{
				jQuery("#gainwp-widgetchart").css({"background-color":"#F7F7F7","height":"auto","padding-top":"50px","padding-bottom":"50px","color":"#000","text-align":"center"});
				jQuery("#gainwp-widgetchart").html("<?php __( "This report is unavailable", 'ga-in' ); ?> ("+response+")");
			}
		});
	}
	function gainwp_drawFrontWidgetChart(response) {
		var data = google.visualization.arrayToDataTable(response);
		var options = {
			legend: { position: "none" },
			pointSize: "3",
			<?php echo $css; ?>
			title: "<?php echo $title; ?>",
			titlePosition: "in",
			chartArea: { width: "95%", height: "75%" },
			hAxis: { textPosition: "none"},
			vAxis: { textPosition: "none", minValue: 0, gridlines: { color: "transparent" }, baselineColor: "transparent"}
		}
		var chart = new google.visualization.AreaChart(document.getElementById("gainwp-widgetchart"));
		<?php echo $formater; ?>
		chart.draw(data, options);
	}
	function gainwp_drawFrontWidgetTotals(response) {
		if ( null == response ){
			response = 0;
		}
		jQuery("#gainwp-widgettotals").html('<div class="gainwp-left"><?php _e( "Period:", 'ga-in' ); ?></div> <div class="gainwp-right"><?php echo $periodtext; ?> </div><div class="gainwp-left"><?php _e( "Sessions:", 'ga-in' ); ?></div> <div class="gainwp-right">'+response+'</div>');
	}
</script>
<?php
		if ( 1 == $instance['give_credits'] ) :
			?>
<div style="text-align: right; width: 100%; font-size: 0.8em; clear: both; margin-right: 5px;"><?php _e( 'generated by', 'ga-in' ); ?> <a href="https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_report&utm_medium=link&utm_content=front_widget&utm_campaign=gainwp" rel="nofollow" style="text-decoration: none; font-size: 1em;">GAINWP</a>&nbsp;
</div>

		<?php
		endif;
		$widget_content = ob_get_contents();
		if ( ob_get_length() ) {
			ob_end_clean();
		}
		echo $widget_content;
		echo $args['after_widget'];
		echo "\n<!-- END GAINWP Widget -->\n";
	}

	public function form( $instance ) {
		$widget_title = ( isset( $instance['title'] ) ? $instance['title'] : __( "Google Analytics Stats", 'ga-in' ) );
		$period = ( isset( $instance['period'] ) ? $instance['period'] : '7daysAgo' );
		$display = ( isset( $instance['display'] ) ? $instance['display'] : 1 );
		$give_credits = ( isset( $instance['give_credits'] ) ? $instance['give_credits'] : 1 );
		$anonim = ( isset( $instance['anonim'] ) ? $instance['anonim'] : 0 );
		/* @formatter:off */
?>
<p>
    <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( "Title:",'ga-in' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $widget_title ); ?>">
</p>
<p>
    <label for="<?php echo $this->get_field_id( 'display' ); ?>"><?php _e( "Display:",'ga-in' ); ?></label> <select id="<?php echo $this->get_field_id('display'); ?>" class="widefat" name="<?php   echo $this->get_field_name( 'display' ); ?>">
        <option value="1" <?php selected( $display, 1 ); ?>><?php _e('Chart & Totals', 'ga-in');?></option>
        <option value="2" <?php selected( $display, 2 ); ?>><?php _e('Chart', 'ga-in');?></option>
        <option value="3" <?php selected( $display, 3 ); ?>><?php _e('Totals', 'ga-in');?></option>
    </select>
</p>
<p>
    <label for="<?php echo $this->get_field_id( 'anonim' ); ?>"><?php _e( "Anonymize stats:",'ga-in' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id( 'anonim' ); ?>" name="<?php echo $this->get_field_name( 'anonim' ); ?>" type="checkbox" <?php checked( $anonim, 1 ); ?> value="1">
</p>
<p>
    <label for="<?php echo $this->get_field_id( 'period' ); ?>"><?php _e( "Stats for:",'ga-in' ); ?></label> <select id="<?php echo $this->get_field_id('period'); ?>" class="widefat" name="<?php   echo $this->get_field_name( 'period' ); ?>">
        <option value="7daysAgo" <?php selected( $period, '7daysAgo' ); ?>><?php printf( __('Last %d Days', 'ga-in'), 7 );?></option>
        <option value="14daysAgo" <?php selected( $period, '14daysAgo' ); ?>><?php printf( __('Last %d Days', 'ga-in'), 14 );?></option>
        <option value="30daysAgo" <?php selected( $period, '30daysAgo' ); ?>><?php printf( __('Last %d Days', 'ga-in'), 30 );?></option>
    </select>
</p>
<p>
    <label for="<?php echo $this->get_field_id( 'give_credits' ); ?>"><?php _e( "Give credits:",'ga-in' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id( 'give_credits' ); ?>" name="<?php echo $this->get_field_name( 'give_credits' ); ?>" type="checkbox" <?php checked( $give_credits, 1 ); ?> value="1">
</p>
<?php
		/* @formatter:on */
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : 'Analytics Stats';
		$instance['period'] = ( ! empty( $new_instance['period'] ) ) ? strip_tags( $new_instance['period'] ) : '7daysAgo';
		$instance['display'] = ( ! empty( $new_instance['display'] ) ) ? strip_tags( $new_instance['display'] ) : 1;
		$instance['give_credits'] = ( ! empty( $new_instance['give_credits'] ) ) ? strip_tags( $new_instance['give_credits'] ) : 0;
		$instance['anonim'] = ( ! empty( $new_instance['anonim'] ) ) ? strip_tags( $new_instance['anonim'] ) : 0;
		return $instance;
	}
}
