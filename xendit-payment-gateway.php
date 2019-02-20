<?php
/*
Plugin Name: Xendit Payment Gateway
Plugin URI: http://www.wpstriker.com/plugins
Description: Plugin for Xendit Payment Gateway
Version: 1.0
Author: wpstriker
Author URI: http://www.wpstriker.com
License: GPLv2
Copyright 2019 wpstriker (email : wpstriker@gmail.com)
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'XENDIT_PAYMENT_GATEWAY_URL', plugin_dir_url( __FILE__ ) );
define( 'XENDIT_PAYMENT_GATEWAY_DIR', plugin_dir_path( __FILE__ ) );

require_once XENDIT_PAYMENT_GATEWAY_DIR . 'functions.php';
require_once XENDIT_PAYMENT_GATEWAY_DIR . 'xendit_payments_list_table.php';

global $is_txn_deleted;
$is_txn_deleted	= false;

if( ! class_exists( 'Xendit_Payment_Gateway' ) ) :

class Xendit_Payment_Gateway {
	public function __construct() {
		$this->init();
	}
	
	public function init() {
		add_action( 'admin_menu', array( $this, 'xendit_payment_admin_menu' ), 99 );		
		
		add_action( 'wp', array( $this, 'maybe_xendit_payment_submit' ), 8 );
			
		add_action( 'wp_enqueue_scripts', array( $this, 'xendit_payment_scripts' ) );
		
		add_shortcode( 'xendit_payment_page', array( $this, 'xendit_payment_page' ) );
		
		add_action( 'load-toplevel_page_xendit_payments', array( $this, 'xendit_payments_add_screen_options' ) );
		
		add_filter( 'set-screen-option', array( $this, 'xendit_payments_set_option' ), 10, 3 );
	}
	
	public function xendit_payments_add_screen_options() {
		$option = 'per_page';
	 
		$args 	= array(
			'label' 	=> 'Payments',
			'default' 	=> 20,
			'option' 	=> 'xendit_payments_per_page'
		);
	 
		add_screen_option( $option, $args );
	}

	public function xendit_payments_set_option( $status, $option, $value ) {
	 
		if ( 'xendit_payments_per_page' == $option ) 
			return $value;
	 
		return $status; 
	}

	public function xendit_payment_scripts() {
		wp_enqueue_style( 'xendit-payment-gateway', XENDIT_PAYMENT_GATEWAY_URL . 'css/xendit-payment-gateway.css' );
		wp_enqueue_script( 'xendit-creditcard', 'https://js.xendit.co/v1/xendit.min.js', array(), '', true );
		wp_enqueue_script( 'xendit-payment-gateway', XENDIT_PAYMENT_GATEWAY_URL . 'js/xendit-payment-gateway.js', array(), '', true );
		wp_localize_script( 'xendit-payment-gateway', 'xendit', array(
			'xendit_public_api_key'	=> get_option( 'xendit_public_api_key' ),
		));		
	}

	public function xendit_payment_admin_menu() {
		add_menu_page( 'Xendit Payments', 'Xendit Payments', 'administrator', 'xendit_payments', array( $this, 'xendit_payments_page' ) );		
		add_submenu_page( 'xendit_payments', 'Xendit Settings', 'Xendit Settings', 'administrator', 'xendit_settings', array( $this, 'xendit_settings_page' ) );	
	}
	
	public function xendit_payments_page() {
		global $wpdb;
	
		$xendit_payments_list = new Xendit_Payments_List_Table();
		$xendit_payments_list->prepare_items();
			
		?>
		<div class="wrap">
			
			<div style="clear:both;"></div>
			
			<div id="icon-users" class="icon32"><br/></div>
			<h2>Payments List</h2>
			
			<?php
			global $is_txn_deleted;
			if( $is_txn_deleted ){ ?>
			<div class="updated settings-error notice is-dismissible" style="margin: 0 0 20px; max-width: 845px;"> 
				<p><strong>Payment deleted successfully.</strong></p>
				<button class="notice-dismiss" type="button">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
			<?php } ?>
				
			<div style="clear:both;"></div>
			
            <form id="images_table-filter" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<?php $xendit_payments_list->display() ?>
			</form>
			
		</div>
		<?php	
	}
	
	public function xendit_settings_page() {
		$is_settins_saved	= false;
		
		if( isset($_POST['submit']) && $_POST['submit'] != '' ) {
			update_option( 'xendit_secret_api_key', $_POST['xendit_secret_api_key'] );
			update_option( 'xendit_public_api_key', $_POST['xendit_public_api_key'] );
			
			$is_settins_saved	= true;
		}	
		?>
		<div class="wrap">
            <h1>Xendit Settings</h1>
          	
            <?php if( $is_settins_saved ) { ?>  
            <div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
				<p><strong>Settings saved.</strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
           	</div>
            <?php } ?>

            <form method="post">
            
	            <table class="form-table">
                
                    <tr>
                        <th scope="row"><label for="xendit_secret_api_key">Xendit Secret API Key</label></th>
                        <td><input name="xendit_secret_api_key" type="text" id="xendit_secret_api_key" value="<?php echo get_option( 'xendit_secret_api_key' );?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="xendit_public_api_key">Xendit Public API Key</label></th>
                        <td><input name="xendit_public_api_key" type="text" id="xendit_public_api_key" value="<?php echo get_option( 'xendit_public_api_key' );?>" class="regular-text" /></td>
                    </tr>
                    
                </table>
                
                <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
                
            </form>        
        </div>
    	<?php
	}
	
	
	function xendit_payment_page( $atts = array() ) {
		$content	= "";
		
		ob_start();	
		?>
		<div class="xendit-payment-wrap">
        
        <form name="xendit-payment-form" class="xendit-form-item" method="post" enctype="multipart/form-data">
			
            <div class="xendit-payment-left">
            	<div class="xendit-title">Billing Information</div>
                
                <div class="xendit-row">
                	<input type="text" name="xendit_first_name" id="xendit_first_name" value="" placeholder="First Name" />	
                	<input type="text" name="xendit_last_name" id="xendit_last_name" value="" placeholder="Last Name" />	
                	<input type="text" name="xendit_email" id="xendit_email" value="" placeholder="Email" />	
                </div>
                
                <div class="xendit-row">
                	<input type="text" name="xendit_street_address" id="xendit_street_address" value="" placeholder="Street Address" />	
                	<input type="text" name="xendit_street_address_2" id="xendit_street_address_2" value="" placeholder="Street Address Line 2 (Optional)" />	
                	<input type="text" name="xendit_city" id="xendit_city" value="" placeholder="City" />	
                </div>
                
                <div class="xendit-row">
                	<div class="xendit-row-sub">State</div>
                    <select name="xendit_state" id="xendit_state">
                    	<option value="">Select</option>
                        <option value="Atjeh">Atjeh</option>
                        <option value="Djogdjakarta">Djogdjakarta</option>
                    </select>
                	<input type="text" name="xendit_postcode" id="xendit_postcode" value="" placeholder="Zip/Post Code" />	
                </div>
                
                <div class="xendit-row">
                	<div class="xendit-row-sub">Country</div>
                    <select name="xendit_country" id="xendit_country">
                    	<option value="">Select</option>
                        <option value="Indonesia">Indonesia</option>
                        <option value="USA">USA</option>
                    </select>
                	<input type="text" name="xendit_phone" id="xendit_phone" value="" placeholder="Phone" />	
                </div>
                
                <div class="xendit-title">Payment Info</div>
                <div class="xendit-row">
                	<div class="xendit-row-cc">
                    	<input type="text" name="xendit_credit_card" id="xendit_credit_card" value="" placeholder="Credit Card Number" />	
                		<input type="text" name="xendit_credit_card_cvc" id="xendit_credit_card_cvc" value="" placeholder="CVC" />
                    </div>
                    
                    <div class="xendit-row-cc2">
                        <div class="xendit-row-cc2-1">
                            <div class="xendit-row-sub">Month</div>
                            <select name="xendit_credit_card_month" id="xendit_credit_card_month">
                                <option value="01">01</option>
                                <option value="02">02</option>
                                <option value="03">03</option>
                                <option value="04">04</option>
                                <option value="05">05</option>
                                <option value="06">06</option>
                                <option value="07">07</option>
                                <option value="08">08</option>
                                <option value="09">09</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                            </select>
                        </div>
                        <div class="xendit-row-cc2-2">
                        	<div class="xendit-row-sub">Year</div>
                            <select name="xendit_credit_card_year" id="xendit_credit_card_year">
                                <?php
                                for( $i = 2019; $i <= 2050; $i++ ){
									echo '<option value="' . $i . '">' . $i . '</option>';	
								}
								?>
                            </select>
                        </div>                    
                    </div>
                </div>
                
                
                <div class="xendit-title">Summary</div>
                	            
            </div>
        	
            <div class="xendit-payment-right">
       			
                Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.		     
            
            </div>
            
		</form>
        
        </div>
		<?php
		
		$content	= ob_get_contents();
		
		ob_clean();
		
		return $content;	
	}
	
	public function maybe_xendit_payment_submit() {
		
		if( ! $_POST )
			return;
		
		if( ! isset( $_POST['xendit_payment_submit'] ) || ! $_POST['xendit_payment_submit'] )			
			return;
		
		$payment_amount	= trim( sipost( 'payment_amount' ) );		
		
		exit;
	}
		
	public function get_domain_name() {
		$domain = site_url( "/" ); 
		$domain = str_replace( array( 'http://', 'https://', 'www' ), '', $domain );
		$domain = explode( "/", $domain );
		$domain	= $domain[0] ? $domain[0] : $_SERVER['SERVER_ADDR'];	
		
		return $domain;
	}
	
	public function base64_url_encode( $data ) { 
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); 
	}
	
	public function base64_url_decode( $data ) { 
		return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) ); 
	}

	public function _log( $msg = "" ) {
		
		define( "LOG_FILE", __DIR__ . "/debug.log" );
		
		$msg	= function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $msg ) : $msg;
		
		$msg	= ( is_array( $msg ) || is_object( $msg ) ) ? print_r( $msg, 1 ) : $msg;
		 	
		error_log( date('[Y-m-d H:i:s e] ') . $msg . PHP_EOL, 3, LOG_FILE );
	}
}

endif;

new Xendit_Payment_Gateway();	// Init



// install payment data table 
function install_xendit_payments() {
	
	global $wpdb;
	
	$table_name 		= $wpdb->prefix . 'xendit_payments';
	
	$charset_collate 	= $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				user_id mediumint(9) NOT NULL,
				first_name varchar(255) NOT NULL,
				last_name varchar(255) NOT NULL,
				email_address varchar(100) NOT NULL,
				payment_amount varchar(100) NOT NULL,
				street_address  tinytext  NOT NULL,
				street_address2  tinytext  NOT NULL,
				city  varchar(100)  NOT NULL,
				state  varchar(30)  NOT NULL,
				postcode  varchar(30)  NOT NULL,
				country  varchar(30)  NOT NULL,
				phone_no  varchar(20)  NOT NULL,
				payment_status	varchar(50) DEFAULT 'pending' NOT NULL,
				txn_id varchar(50)  NOT NULL,
				monthly_payment int(1) DEFAULT '0' NOT NULL,
				subscription_id varchar(50)  NOT NULL,	
				updated_at datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id)
	) $charset_collate;";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'install_xendit_payments' );


// uninstall 
function uninstall_xendit_payments(){
	global $wpdb;
	
	$table_name	= $wpdb->prefix . 'xendit_payments';
	$sql 		= "DROP TABLE IF EXISTS $table_name";
   	$wpdb->query( $sql );
}
register_uninstall_hook( __FILE__, 'uninstall_xendit_payments' );