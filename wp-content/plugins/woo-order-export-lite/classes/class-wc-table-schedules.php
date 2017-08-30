<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Table_Schedules extends WP_List_Table {

	var $current_destination = '';

	public function __construct() {

		parent::__construct( array(
			'singular' => __( 'item', 'woocommerce-pickingpal' ),
			'plural'   => __( 'items', 'woocommerce-pickingpal' ),
			'ajax'     => true
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
			       value="<?php _e( 'Add Schedule', 'woocommerce-order-export' ); ?>" id="add_schedule">
		</div><br>
		<?php
	}

	public function prepare_items() {


		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

//		$this->items = array(
//			0 => array( 'recurrence' => 2 ),
//		);
		$this->items = get_option( 'woocommerce-order-export-cron', array() );

		foreach ( $this->items as $index => $item ) {
			$this->items[ $index ]['id'] = $index;
		}
//		var_dump( $this->items );
	}

	public function get_columns() {
		$columns                        = array();
		$columns['active']              = __( 'Active', 'woocommerce-order-export' );
		$columns['id']          		= __( 'Id', 'woocommerce-order-export' );
		$columns['title']          		= __( 'Title', 'woocommerce-order-export' );
		$columns['format']          	= __( 'Format', 'woocommerce-order-export' );
		$columns['recurrence']          = __( 'Recurrence', 'woocommerce-order-export' );
		$columns['destination']         = __( 'Destination', 'woocommerce-order-export' );
		$columns['destination_details'] = __( 'Destination Details', 'woocommerce-order-export' );
		$columns['next_event']          = __( 'Next event', 'woocommerce-order-export' );
		$columns['actions']             = __( 'Actions', 'woocommerce-order-export' );

		return $columns;
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'active':
				return "<input type='checkbox' data-action='change-schedule-status' data-id='{$item['id']}' " . ( ! isset( $item['active'] ) || $item['active'] ? 'checked' : '' ) . "/>";
				break;
			case 'title':
				return '<a href="admin.php?page=wc-order-export&tab=schedules&wc_oe=edit_schedule&schedule_id=' . $item[ 'id' ] . '">' . $item[ $column_name ] . '</a>';
				break;
			case 'recurrence':
				$r = '';
				if ( isset( $item[ 'schedule' ] ) ) {
					if ( $item[ 'schedule' ][ 'type' ] == 'schedule-1' ) {
						$r = __( 'Run ', 'woocommerce-order-export' );
						if ( isset( $item[ 'schedule' ][ 'weekday' ] ) ) {
							$days = array_keys( $item[ 'schedule' ][ 'weekday' ] );
							$r .= __( " on ", 'woocommerce-order-export' ) . implode( ', ', $days );
						}
						if ( isset( $item[ 'schedule' ][ 'run_at' ] ) ) {
							$r .= __( '  at ', 'woocommerce-order-export' ) . $item[ 'schedule' ][ 'run_at' ];
						}
						//nothing selected 
						if( empty( $days ) )
							$r = __( 'Never', 'woocommerce-order-export' );
					} else {
						if ( $item[ 'schedule' ][ 'interval' ] == 'first_day_month' ) {
							$r = __( "First Day Every Month", 'woocommerce-order-export' );
						} elseif ( $item[ 'schedule' ][ 'interval' ] == 'first_day_quarter' ) {
							$r = __( "First Day Every Quarter", 'woocommerce-order-export' );
						} elseif ( $item[ 'schedule' ][ 'interval' ] == 'custom' ) {
							$r = sprintf( __( "to run every %s minute(s)", 'woocommerce-order-export' ), $item[ 'schedule' ][ 'custom_interval' ] );
						} else {
							foreach ( wp_get_schedules() as $name => $schedule ) {
								if ( $item[ 'schedule' ][ 'interval' ] == $name ) {
									$r = $schedule[ 'display' ];
								}
							}
						}
					}
				}

				return $r;
			case 'destination':
				$this->current_destination = isset( $item['destination']['type'] ) ? $item['destination']['type'] : '';
				$al                        = array(
					'ftp'   => __( 'Ftp', 'woocommerce-order-export' ),
					'http'  => __( 'Http post', 'woocommerce-order-export' ),
					'email' => __( 'Email', 'woocommerce-order-export' ),
					'folder' => __( 'Folder', 'woocommerce-order-export' ),
				);
				if ( isset( $item['destination']['type'] ) ) {
					return $al[ $item['destination']['type'] ];
				}

				return '';
			case 'destination_details':
				if ( $this->current_destination == 'http' ) {
					return esc_html( $item['destination']['http_post_url'] );
				}
				if ( $this->current_destination == 'email' ) {
					return __( 'Subject: ',
						'woocommerce-order-export' ) . esc_html( $item['destination']['email_subject'] ) . "<br>" . __( 'To: ',
						'woocommerce-order-export' ) . esc_html( $item['destination']['email_recipients'] );
				}
				if ( $this->current_destination == 'ftp' ) {
					return esc_html( $item['destination']['ftp_user'] ) . "@" . esc_html( $item['destination']['ftp_server'] ) . $item['destination']['ftp_path'];
				}
				if ( $this->current_destination == 'folder' ) {
					return esc_html( $item['destination']['path'] );
				}

				//print_r($item);
				return '';
			case 'next_event':
//                var_dump($item);
//                print_r($item['schedule']['last_run']);
				$last_run = isset( $item['schedule']['last_run'] ) ? $item['schedule']['last_run'] : null;
				$active =  ( ! isset( $item['active'] ) || $item['active'] );
				if ( !$active )
					return  __( 'Inactive', 'woocommerce-order-export' );
				if ( isset( $item['schedule']['next_run'] ) ) {
					$next_run_local =  $item['schedule']['next_run'];
					if($next_run_local)
						return gmdate('M j Y', $next_run_local) . ' at ' . gmdate('G:i', $next_run_local);
					else
						 return __( '', 'woocommerce-order-export' );
				} else {
					return  __( 'Not installed', 'woocommerce-order-export' );
				}
			case 'actions':
				return 
					'<div class="btn-edit button-secondary" data-id="' . $item['id'] .  '" title="' . __( 'Edit', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-edit"></span></div>&nbsp;' .
					'<div class="btn-clone button-secondary" data-id="' . $item['id'] . '" title="' . __( 'Copy', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-admin-page"></span></div>&nbsp;'.
					'<div class="btn-trash button-secondary" data-id="' . $item['id'] . '" title="' . __( 'Delete', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-trash"></span></div>&nbsp;&nbsp;'.
					'<div class="btn-export button-secondary" data-id="'  . $item['id'] . '" title="' . __( 'Export', 'woocommerce-order-export' ) . '"><span class="dashicons dashicons-download"></span></div>';
				break;
			default:

				return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
		}
	}

}
