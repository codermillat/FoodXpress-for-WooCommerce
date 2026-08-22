<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Delivery-agent work tracking + cash settlement rendering.
 *
 * Sibling of FXW_Dashboard_Render (v1.2.9 split pattern): renders the
 * per-agent performance table, the settle-cash action, the per-agent
 * unsettled-order breakdown, and the recent-settlements ledger on the
 * admin Deliveries dashboard. Numbers reuse
 * FXW_Delivery_Boy_View::build_dashboard_state() and
 * FXW_Agent_Cash::get_agent_cash_summary() so admin and agent
 * dashboards always agree.
 *
 * @since      1.4.0
 * @package    FoodXpress
 * @author     MD Millat Hosen <https://millat.is-a.dev/>
 */
class FXW_Dashboard_Agents
{

	/**
	 * Render the whole section (called from FXW_Dashboard_Render).
	 *
	 * @since 1.4.0
	 */
	public static function render()
	{
		$agents = get_users(array('role' => 'delivery_boy', 'orderby' => 'display_name'));
		if (empty($agents)) {
			return;
		}
		?>
		<div class="fxw-dashboard-section">
			<h2><?php esc_html_e('Delivery Agents — Work Tracking', 'foodxpress'); ?></h2>
			<table class="wp-list-table widefat fixed striped fxw-agent-performance">
				<thead>
					<tr>
						<th><?php esc_html_e('Agent', 'foodxpress'); ?></th>
						<th><?php esc_html_e('Mobile', 'foodxpress'); ?></th>
						<th><?php esc_html_e('Active now', 'foodxpress'); ?></th>
						<th><?php esc_html_e('Delivered today', 'foodxpress'); ?></th>
						<th><?php esc_html_e('Cash collected today', 'foodxpress'); ?></th>
						<th><?php esc_html_e('Cash to hand over', 'foodxpress'); ?></th>
						<th><?php esc_html_e('All-time delivered', 'foodxpress'); ?></th>
						<th><?php esc_html_e('Settle cash', 'foodxpress'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($agents as $agent): ?>
						<?php
						$state = class_exists('FXW_Delivery_Boy_View')
							? FXW_Delivery_Boy_View::build_dashboard_state($agent->ID)
							: array('counts' => array('new' => 0, 'in_progress' => 0, 'delivered' => 0), 'today' => array('delivered' => 0, 'collected' => 0));
						$cash = class_exists('FXW_Agent_Cash') ? FXW_Agent_Cash::get_agent_cash_summary($agent->ID) : array('unsettled' => 0, 'unsettled_orders' => array(), 'pending' => null, 'last_accepted' => null);
						$mobile = trim((string) get_user_meta($agent->ID, 'fxw_agent_phone', true));
						if ('' === $mobile) {
							$mobile = trim((string) get_user_meta($agent->ID, 'billing_phone', true));
						}
						$active = absint($state['counts']['new']) + absint($state['counts']['in_progress']);
						?>
						<tr>
							<td><strong><?php echo esc_html($agent->display_name); ?></strong></td>
							<td>
								<?php if ($mobile): ?>
									<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $mobile)); ?>"><?php echo esc_html($mobile); ?></a>
								<?php else: ?>
									<span class="fxw-muted"><?php esc_html_e('Not set', 'foodxpress'); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html($active); ?></td>
							<td><?php echo esc_html($state['today']['delivered']); ?></td>
							<td><?php echo wp_kses_post(wc_price($state['today']['collected'], array('currency' => get_woocommerce_currency()))); ?></td>
							<td>
								<strong><?php echo wp_kses_post(wc_price($cash['unsettled'], array('currency' => get_woocommerce_currency()))); ?></strong>
								<?php if (!empty($cash['unsettled_orders'])): ?>
									<details class="fxw-settle-details">
										<summary>
											<?php
											// translators: %d = number of orders.
											printf(esc_html__('%d order(s) in this balance', 'foodxpress'), count($cash['unsettled_orders']));
											?>
										</summary>
										<ul class="fxw-settle-orders">
											<?php foreach ($cash['unsettled_orders'] as $uo): ?>
												<li>
													<a href="<?php echo esc_url(admin_url('admin.php?page=wc-orders&action=edit&id=' . absint($uo['id']))); ?>">#<?php echo esc_html($uo['number']); ?></a>
													— <?php echo wp_kses_post(wc_price($uo['total'], array('currency' => get_woocommerce_currency()))); ?>
													<span class="fxw-muted">(<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $uo['completed'])); ?>)</span>
												</li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html($state['counts']['delivered']); ?></td>
							<td>
								<?php if ($cash['pending']): ?>
									<em class="fxw-muted"><?php esc_html_e('Hand-over pending approval', 'foodxpress'); ?></em>
								<?php elseif ($cash['unsettled'] > 0): ?>
									<span class="fxw-muted">
										<?php
										// translators: %s = formatted amount.
										printf(esc_html__('Agent holds %s — awaiting their request', 'foodxpress'), wp_kses_post(wc_price($cash['unsettled'], array('currency' => get_woocommerce_currency()))));
										?>
									</span>
								<?php elseif ($cash['last_accepted']): ?>
									<span class="fxw-muted">
										<?php
										$approver = get_user_by('id', absint($cash['last_accepted']['reviewed_by']));
										/* translators: 1: date/time, 2: approver name. */
										printf(esc_html__('Settled %1$s (by %2$s)', 'foodxpress'),
											esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $cash['last_accepted']['reviewed_at'])),
											esc_html($approver ? $approver->display_name : __('manager', 'foodxpress'))
										);
										?>
									</span>
								<?php else: ?>
									<span class="fxw-muted">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e('Active = assigned + picked up. Agents see the same numbers for themselves on their dashboard and can self-report a handover there.', 'foodxpress'); ?>
			</p>

			<?php self::render_recent_settlements(); ?>
		</div>
		<?php
	}

	/**
	 * Cash hand-over requests + history (newest first, last 15).
	 * Pending rows carry Accept / Reject actions for managers/admins.
	 *
	 * @since 1.4.0
	 */
	private static function render_recent_settlements()
	{
		if (!class_exists('FXW_Agent_Cash')) {
			return;
		}

		$settlements = array_slice(FXW_Agent_Cash::get_settlements(), 0, 15);
		?>
		<h3><?php esc_html_e('Cash hand-overs — requests & history', 'foodxpress'); ?></h3>
		<table class="wp-list-table widefat fixed striped fxw-settlements">
			<thead>
				<tr>
					<th><?php esc_html_e('Requested', 'foodxpress'); ?></th>
					<th><?php esc_html_e('Agent', 'foodxpress'); ?></th>
					<th><?php esc_html_e('Amount', 'foodxpress'); ?></th>
					<th><?php esc_html_e('Status', 'foodxpress'); ?></th>
					<th><?php esc_html_e('Reviewed by', 'foodxpress'); ?></th>
					<th><?php esc_html_e('Actions', 'foodxpress'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($settlements)): ?>
					<tr><td colspan="6"><?php esc_html_e('No hand-over requests yet. Agents send these from their dashboard when they hand cash to the store.', 'foodxpress'); ?></td></tr>
				<?php endif; ?>
				<?php foreach ($settlements as $entry): ?>
					<?php
					$agent = get_user_by('id', absint($entry['agent_id']));
					$requester = get_user_by('id', absint($entry['requested_by']));
					$reviewer = absint($entry['reviewed_by']) ? get_user_by('id', absint($entry['reviewed_by'])) : null;
					$is_pending = FXW_Agent_Cash::STATUS_PENDING === $entry['status'];
					?>
					<tr class="<?php echo $is_pending ? 'fxw-settlement-pending' : ''; ?>">
						<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $entry['requested_at'])); ?></td>
						<td><strong><?php echo esc_html($agent ? $agent->display_name : ('#' . absint($entry['agent_id']))); ?></strong></td>
						<td><strong><?php echo wp_kses_post(wc_price($entry['amount'], array('currency' => get_woocommerce_currency()))); ?></strong></td>
						<td>
							<span class="fxw-settle-status fxw-settle-status--<?php echo esc_attr($entry['status']); ?>">
								<?php
								if (FXW_Agent_Cash::STATUS_PENDING === $entry['status']) {
									esc_html_e('Pending approval', 'foodxpress');
								} elseif (FXW_Agent_Cash::STATUS_ACCEPTED === $entry['status']) {
									esc_html_e('Accepted', 'foodxpress');
								} else {
									esc_html_e('Rejected', 'foodxpress');
								}
								?>
							</span>
						</td>
						<td>
							<?php if ($reviewer): ?>
								<?php echo esc_html($reviewer->display_name); ?><br>
								<span class="fxw-muted"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $entry['reviewed_at'])); ?></span>
							<?php else: ?>
								<span class="fxw-muted">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($is_pending && current_user_can('edit_shop_orders')): ?>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:6px;">
									<?php wp_nonce_field('fxw_review_cash_' . $entry['id']); ?>
									<input type="hidden" name="action" value="fxw_review_settlement" />
									<input type="hidden" name="settlement_id" value="<?php echo esc_attr($entry['id']); ?>" />
									<input type="hidden" name="decision" value="accepted" />
									<button type="submit" class="button button-small button-primary"><?php esc_html_e('Accept', 'foodxpress'); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
									<?php wp_nonce_field('fxw_review_cash_' . $entry['id']); ?>
									<input type="hidden" name="action" value="fxw_review_settlement" />
									<input type="hidden" name="settlement_id" value="<?php echo esc_attr($entry['id']); ?>" />
									<input type="hidden" name="decision" value="rejected" />
									<button type="submit" class="button button-small fxw-reject-btn"><?php esc_html_e('Reject', 'foodxpress'); ?></button>
								</form>
							<?php elseif ($is_pending): ?>
								<span class="fxw-muted"><?php esc_html_e('Awaiting a manager', 'foodxpress'); ?></span>
							<?php else: ?>
								<span class="fxw-muted">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e('Agents request a hand-over of their collected cash; accepting clears it from their balance. Rejecting keeps the amount on the agent\'s balance.', 'foodxpress'); ?>
		</p>
		<?php
	}
}

new FXW_Dashboard_Agents();
