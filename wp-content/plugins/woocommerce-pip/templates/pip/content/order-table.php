<?php
/**
 * WooCommerce Print Invoices/Packing Lists
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Print
 * Invoices/Packing Lists to newer versions in the future. If you wish to
 * customize WooCommerce Print Invoices/Packing Lists for your needs please refer
 * to http://docs.woothemes.com/document/woocommerce-print-invoice-packing-list/
 *
 * @package   WC-Print-Invoices-Packing-Lists/Templates
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

/**
 * PIP Template Body content
 *
 * @version 3.0.0
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

					?>

					<thead class="order-table-head">
						<tr>
							<?php $column_widths = $document->get_column_widths(); ?>

							<?php foreach( $document->get_table_headers() as $column_id => $title ): ?>
								<th class="<?php echo sanitize_html_class( $column_id ); ?>" style="width: <?php echo esc_attr( $column_widths[ $column_id ] ); ?>%"><?php echo esc_html( $title ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>

					<?php if ( $type !== 'pick-list' ) : ?>

						<tfoot class="order-table-footer">
							<?php $rows = $document->get_table_footer(); ?>

							<?php foreach ( $rows as $cells ) : $i = 0; ?>
								<tr>
									<?php foreach ( $cells as $cell => $value ) : ?>
										<td class="<?php echo esc_attr( $cell ); ?>" <?php if ( 0 === $i ) { echo 'colspan="' . $document->get_table_footer_column_span( count( $cells ) ) . '"'; } ?>>
											<?php echo $value; $i++; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tfoot>

					<?php endif; ?>

					<?php
