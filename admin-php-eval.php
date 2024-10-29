<?php
/*
Plugin Name: Admin PHP Eval
Description: Storing and evaluating PHP scripts within WordPress administration.
Author: Zaantar
Version: 1.1
Author URI: http://zaantar.eu
Donate Link: http://zaantar.eu/financni-prispevek
Plugin URI: http://wordpress.org/extend/plugins/admin-php-eval/
License: GPLv2 or later
*/


define( 'APE_SLUG', 'admin-php-eval' );

if( !class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );
}

new AdminPhpEval;


class AdminPhpEval {


	function __construct() {
		add_action( 'init', array( &$this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
	}
	
	
	function load_textdomain() {
		load_plugin_textdomain( APE_SLUG, false, basename( dirname(__FILE__) ).'/languages' );
	}
	
	
	function log( $message, $severity ) {
		if( defined( 'WLS' ) && wls_is_registered( APE_SLUG ) ) {
			wls_simple_log( APE_SLUG, $message, $severity );
		}
	}
	
	
	function admin_menu() {
		add_submenu_page( 'tools.php', __( 'Admin PHP Eval', APE_SLUG ), __( 'Admin PHP Eval', APE_SLUG ), 
			"manage_options", APE_SLUG, array( &$this, 'page' ) );
	}
	
	
	function page() {
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'default';
		switch( $action ) {
		case "add":
			if( !wp_verify_nonce( $_REQUEST["_wpnonce"], APE_SLUG."_add" ) ) {
				$this->die_of_nonce();
			}
			$sanitized_name = $this->create_script( $_POST['name'] );
			$this->page_edit( $sanitized_name );
			break;
		case "edit":
			if( !wp_verify_nonce( $_REQUEST["_wpnonce"], APE_SLUG."_edit" ) ) {
				$this->die_of_nonce();
			}
			$this->page_edit( $_GET['name'] );
			break;
		case "update":
			if( !wp_verify_nonce( $_REQUEST["_wpnonce"], APE_SLUG."_update" ) ) {
				$this->die_of_nonce();
			}
			$sanitized_name = $this->update_script( $_POST['prev_name'], $_POST['script'] );
			if( isset( $_POST['eval_after_update'] ) ) {
				$this->page_eval( $sanitized_name );
			} else {
				$this->page_edit( $sanitized_name );
			}
			break;
		case "eval":
			if( !wp_verify_nonce( $_REQUEST["_wpnonce"], APE_SLUG."_eval" ) ) {
				$this->die_of_nonce();
			}
			$this->page_eval( $_GET['name'] );
			break;
		case "delete":
			if( !wp_verify_nonce( $_REQUEST["_wpnonce"], APE_SLUG."_delete" ) ) {
				$this->die_of_nonce();
			}
			$this->delete_script( $_GET['name'] );
			$this->page_default();
			break;
		default:
			$this->page_default();
			break;
		}
	}
	
	
	function die_of_nonce() {
		wp_die( __( "Error! Security check not passed.", APE_SLUG ), __( "Error", APE_SLUG ), array( "back_link" => true ) );
	}
	
	
	function page_default() {
		$script_table = new AdminPhpEval_ScriptTable( $this );
		$script_table->prepare_items();
		?>
		<div id="wrap">
			<h2><?php _e( 'Admin PHP Eval', APE_SLUG ); ?></h2>
			<form method="post">
				<input type="hidden" name="action" value="add" />
				<?php wp_nonce_field( APE_SLUG."_add" ); ?>
				<label><?php _e( 'Create new script: ', APE_SLUG ); ?></label>
				<input type="text" name="name" />
				<input type="submit" value="<?php _e( 'Create', APE_SLUG ); ?>" />
			</form>
			<form id="scripts" method="get" style="margin-right: 15px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ) ?>" />
				<?php $script_table->display(); ?>
			</form>
			<p><small><?php printf( __( 'Note: To enable logging create log category %s in the WordPress Logging Service.', APE_SLUG ),
				"<code>".APE_SLUG."</code>" );
			?></small></p>
		</div>
		<?php
	}
	
	
	function page_edit( $name ) {
		$script = $this->get_script( $name );
		if( $script == NULL ) {
			$this->page_default();
			return;
		}
		?>
		<div id="wrap">
			<h2><?php _e( 'Admin PHP Eval', APE_SLUG ); ?></h2>
			<p>&laquo; <a href="?page=<?php echo APE_SLUG; ?>&action=default"><?php _e( 'Script list', APE_SLUG ); ?></a> &laquo;</p>
			<form method="post">
				<input type="hidden" name="action" value="update" />
				<?php wp_nonce_field( APE_SLUG."_update" ); ?>
				<input type="hidden" name="prev_name" value="<?php echo esc_attr( $name ); ?>" />
				<table class="form-table">
					<tr valign="top">
						<th><label><?php _e( 'Name', APE_SLUG ); ?></label></th>
						<td><input type="text" name="script[name]" value="<?php echo esc_attr( $script['name'] ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th><label><?php _e( 'Description', APE_SLUG ); ?></label></th>
						<td><textarea name="script[description]" rows="3" cols="30"><?php 
							echo stripslashes( esc_textarea( $script['description'] ) ); 
						?></textarea></td>
					</tr>
					<tr valign="top">
						<th><label><?php _e( 'Code', APE_SLUG ); ?></label></th>
						<td><textarea name="script[code]" rows="25" cols="60" style="font-family:monospace;"><?php 
							echo stripslashes( esc_textarea( $script['code'] ) ); 
						?></textarea></td>
					</tr>
					<tr valign="top">
						<th><label><?php _e( 'Evaluate after updating', APE_SLUG ); ?></label></th>
						<td><input type="checkbox" name="eval_after_update" /></td>
					</tr>
				</table>
				<p class="submit">
			        <input type="submit" class="button-primary" value="<?php _e( 'Save', APE_SLUG ); ?>" />    
			    </p>
            </form>
            <p>&laquo; <a href="?page=<?php echo APE_SLUG; ?>&action=default"><?php _e( 'Script list', APE_SLUG ); ?></a> &laquo;</p>
        </div>
		<?php
	}
	
	
	function page_eval( $name ) {
		$script = $this->get_script( $name );
		if( $script == NULL ) {
			$this->page_default();
			return;
		}
		$code = stripslashes( $script['code'] );
		?>
		<div id="wrap">
			<h2><?php _e( 'Admin PHP Eval', APE_SLUG ); ?></h2>
			<p><?php printf( __( 'Evaluating script %s.', APE_SLUG ), "<strong>".esc_html( $script['name'] )."</strong>" ); ?></p>
			<p><small><?php echo esc_html( $script['description'] ); ?></small></p>
			<p>&laquo; <a href="?page=<?php echo APE_SLUG; ?>&action=default"><?php _e( 'Script list', APE_SLUG ); ?></a> | <?php printf( "%s".__( 'Reevaluate', APE_SLUG )."%s", "<a href=\"?page=".APE_SLUG."&action=eval&name=".esc_attr( $name )."\">", "</a>" ); ?> | <?php printf( "%s".__( 'Edit script', APE_SLUG )."%s", "<a href=\"?page=".APE_SLUG."&action=edit&name=".esc_attr( $name )."\">", "</a>" ); ?> &raquo;</p>
			<p><?php printf( __( "Evaluated code is: %s", APE_SLUG ), "<code>".esc_html( $code )."</code>" ); ?></p>
			<div style="background-color:#EEE; width:80%;"><code>
				<?php
					$ret = eval( $code );
				?>
			</code></div>
			<p><?php printf( __( 'Return value is %s.', APE_SLUG ), "<code>".esc_html( print_r( $ret, true ) )."</code>" ); ?></p>
			<p>&laquo; <a href="?page=<?php echo APE_SLUG; ?>&action=default"><?php _e( 'Script list', APE_SLUG ); ?></a> | <?php printf( "%s".__( 'Reevaluate', APE_SLUG )."%s", "<a href=\"?page=".APE_SLUG."&action=eval&name=".esc_attr( $name )."\">", "</a>" ); ?> | <?php printf( "%s".__( 'Edit script', APE_SLUG )."%s", "<a href=\"?page=".APE_SLUG."&action=edit&name=".esc_attr( $name )."\">", "</a>" ); ?> &raquo;</p>
		</div>
		<?php
		$this->log( "Evaluated script $name with return value ".print_r( $ret, true ).".", 2 );
	}
	
	
	
	function get_scripts() {
		return get_option( APE_SLUG, array() );
	}
	
	
	function update_scripts( $scripts ) {
		update_option( APE_SLUG, $scripts );
	}
	
	
	function create_script( $name ) {
		$scripts = $this->get_scripts();
		$sanitized_name = sanitize_title( $name, 'new-script' );
		if( !isset( $scripts[$sanitized_name] ) ) {
			$scripts[$sanitized_name] = array(
				'name' => $sanitized_name,
				'description' => '',
				'code' => ''
			);
		}
		$this->log( "Creating script $sanitized_name ($name).", 2 );
		$this->update_scripts( $scripts );
		return $sanitized_name;
	}
	
	
	function delete_script( $name ) {
		$scripts = $this->get_scripts();
		unset( $scripts[$name] );
		$this->update_scripts( $scripts );
		$this->log( "Deleted script $name", 2 );
	}
	
	
	function get_script( $name ) {
		$scripts = $this->get_scripts();
		return isset( $scripts[$name] ) ? $scripts[$name] : NULL;
	}
	
	
	function update_script( $prev_name, $script ) {
		$scripts = $this->get_scripts();
		unset( $scripts[$prev_name] );
		$sanitized_name = sanitize_title( $script["name"], 'new-script' );
		$script["name"] = $sanitized_name;
		$script["description"] = sanitize_text_field( $script["description"] );
		$script["code"] = sanitize_text_field( $script["code"] );
		$scripts[$sanitized_name] = $script;
		$this->update_scripts( $scripts );
		$this->log( "Script $prev_name has been updated to ".print_r( $script, true ).".", 1 );
		return $sanitized_name;
	}
	

}



class AdminPhpEval_ScriptTable extends WP_List_Table {

	
	const scripts_per_page = 50;
	private $_p;
	
	
	function __construct( $ape ) {
		$this->_p = $ape;
		parent::__construct( array(
	    	'singular'  => 'script',	//singular name of the listed records
	        'plural'    => 'scripts',   //plural name of the listed records
	        'ajax'      => false        //does this table support ajax?
	    ) );
	}
	
	
	function get_columns() {
		$columns = array(
			'name' => __( 'Name', PCD_TXD ),
			'description' => __( 'Description', PCD_TXD ),
		);
		return $columns;
	}
	
	
	function get_sortable_columns() {
		$sortable_columns = array(
		    'name' => array( 'name', true ), // true means its already sorted
		);
	    return $sortable_columns;
	}
	
	
	function prepare_items() {
		
		$columns = $this->get_columns();
    	$hidden = array();
    	$sortable = $this->get_sortable_columns();
    	
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$per_page = AdminPhpEval_ScriptTable::scripts_per_page;
		$current_page = $this->get_pagenum();
		
		$this->items = $this->_p->get_scripts();
		
		$total_items = count($this->items);
		
		$order = ( !empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc'; 
		$cmp = create_function( '$a,$b', 
			'$c = strcoll( mb_strtoupper($a["name"], "UTF-8"), mb_strtoupper($b["name"], "UTF-8") );
			if( $c == 0 ) {
				return 0;
			} else if( $c > 0 ) {
				return ( '.$order.' == "asc" ) ? 1 : -1;
			} else {
				return ( '.$order.' == "desc" ) ? 1 : -1;
			}'
		);	
		usort( $this->items, $cmp );
		
		$this->set_pagination_args( array(
	        'total_items' => $total_items,                  //WE have to calculate the total number of items
	        'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
	        'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
    	) );
	}
	
	
	function column_name( $item ) {
		$lt = '<a href="?page=%s&action=%s&name=%s&_wpnonce=%s">%s</a>';
		$actions = array(
			'edit' => sprintf( $lt, esc_attr( $_REQUEST['page'] ), 'edit' , esc_attr( $item["name"] ), wp_create_nonce( APE_SLUG."_edit" ), __( 'Edit', APE_SLUG ) ),
			'eval' => sprintf( $lt, esc_attr( $_REQUEST['page'] ), 'eval' , esc_attr( $item["name"] ), wp_create_nonce( APE_SLUG."_eval" ), __( 'Evaluate', APE_SLUG ) ),
			'delete' => sprintf( $lt, esc_attr( $_REQUEST['page'] ), 'delete' , esc_attr( $item["name"] ), wp_create_nonce( APE_SLUG."_delete" ), __( 'Delete', APE_SLUG ) )
		);

		return "<strong>".esc_html( $item["name"] )."</strong>".$this->row_actions( $actions );
	}
	
	
	function column_description( $item ) {
		return "<small>".esc_html( stripslashes( $item['description'] ) )."</small>";
	}
	
}

