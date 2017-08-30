<?php
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Table_Profiles extends WP_List_Table {

	var $current_destination = '';

	public function __construct() {

		parent::__construct( array(
			'singular'	 => __( 'item', 'woocommerce-pickingpal' ),
			'plural'	 => __( 'items', 'woocommerce-pickingpal' ),
			'ajax'		 => true
		) );
	}

	/**
	 * Output the report
	 */
	public function output() {
		$this->prepare_items();
		?>

		<div class="wp-wrap">
			<?php
			$this->display();
			?>
		</div>
		<?php
	}

	public function display_tablenav( $which ) {
		if ( 'top' != $which ) {
			return;
		}
		?>
		<div style="margin-top: 10px;">
			<input type="button" class="button-secondary"
				   value="<?php _e( 'Add Profile', 'woocommerce-order-export' ); ?>" id="add_profile">
		</div><br>
		<?php
	}

	public function prepare_items() {


		$columns	 = $this->get_columns();
		$hidden		 = array();
		$sortable	 = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

//		$this->items = array(
//			0 => array( 'recurrence' => 2 ),
//		);
		$this->items = get_option( 'woocommerce-order-export-profiles', array() );

		foreach ( $this->items as $index => $item ) {
			$this->items[ $index ][ 'id' ] = $index;
		}
//		var_dump( $this->items );
	}

	public function get_columns() {
		$columns				 = array();
		$columns[ 'format' ]	 = __( 'Format', 'woocommerce-order-export' );
		$columns[ 'title' ]		 = __( 'Profile', 'woocommerce-order-export' );
		$columns[ 'from_date' ]	 = __( 'From Date', 'woocommerce-order-export' );
		$columns[ 'to_date' ]	 = __( 'To Date', 'woocommerce-order-export' );
		$columns[ 'actions' ]	 = __( 'Actions', 'woocommerce-order-export' );

		return $columns;
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return '<a href="admin.php?page=wc-order-export&tab=profiles&wc_oe=edit_profile&profile_id=' . $item[ 'id' ] . '">' . $item[ $column_name ] . '</a>';
				break;
			//
			case 'actions':
				return 
				'<div class="btn-edit button-secondary" data-id="'  . $item['id'] . '" title="' . __( 'Edit', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-edit"></span></div>&nbsp;' .
				'<div class="btn-clone button-secondary" data-id="'        . $item['id'] . '" title="' . __( 'Copy', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-admin-page"></span></div>&nbsp;'.
				'<div class="btn-to-scheduled button-secondary" data-id="' . $item['id'] . '" title="' . __( 'Copy to Scheduled', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-migrate"></span></div>&nbsp;'.
				'<div class="btn-trash button-secondary" data-id="'        . $item['id'] . '" title="' . __( 'Delete', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-trash"></span></div>&nbsp;&nbsp;&nbsp;'.
				'<div class="btn-export button-secondary" data-id="'  . $item['id'] . '" title="' . __( 'Export', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-download"></span></div>';
				break;
			default:
				return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
		}
	}

}
