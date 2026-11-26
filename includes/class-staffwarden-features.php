<?php
/**
 * StaffWarden: who changed what in your store.
 *
 * @package StaffWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Features and screens.
 */
class StaffWarden_Features {

	/**
	 * Product values before a save, by product ID.
	 *
	 * @var array
	 */
	private static $before = array();

	/* ------------------------------------------------------------------
	 * Frame contract
	 * ---------------------------------------------------------------- */

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'overview' => __( 'Overview', 'staffwarden' ),
			'timeline' => __( 'Timeline', 'staffwarden' ),
			'settings' => __( 'Settings', 'staffwarden' ),
		);
	}

	/**
	 * Tagline.
	 *
	 * @return string
	 */
	public static function tagline() {
		return __( 'Who changed what in your store, and when.', 'staffwarden' );
	}

	/**
	 * Sections.
	 *
	 * @return array
	 */
	public static function sections() {
		return array( 'settings' => array( 'track_products', 'track_orders', 'track_refunds', 'track_coupons', 'track_settings', 'track_roles', 'refund_alert', 'refund_min', 'notify_to', 'retention_days', 'delete_data' ) );
	}

	/**
	 * Messages.
	 *
	 * @return array
	 */
	public static function messages() {
		return array();
	}

	/**
	 * Pro intro.
	 *
	 * @return string
	 */
	public static function pro_intro() {
		return __( 'Hear about risky changes the moment they happen, and get a weekly summary of what every staff member did.', 'staffwarden' );
	}

	/**
	 * Pro points.
	 *
	 * @return array
	 */
	public static function pro_points() {
		return array(
			__( 'Instant alerts for refunds, price drops, role changes and payment settings', 'staffwarden' ),
			__( 'Alerts for changes made outside working hours', 'staffwarden' ),
			__( 'Weekly staff report by email', 'staffwarden' ),
			__( 'Export the timeline to CSV', 'staffwarden' ),
		);
	}

	/**
	 * CSS elsewhere.
	 *
	 * @param string $hook Hook.
	 * @return bool
	 */
	public static function needs_css( $hook ) {
		unset( $hook );
		return false;
	}

	/**
	 * Install.
	 *
	 * @return void
	 */
	public static function install() {}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {}

	/* ------------------------------------------------------------------
	 * Hooks
	 * ---------------------------------------------------------------- */

	/**
	 * Runtime hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'product_before' ), 5 );
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'product_after' ), 50 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_status' ), 50, 4 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'refunded' ), 50, 2 );
		add_action( 'woocommerce_before_coupon_object_save', array( __CLASS__, 'coupon_save' ), 50 );
		add_action( 'updated_option', array( __CLASS__, 'option_changed' ), 50, 3 );
		add_action( 'set_user_role', array( __CLASS__, 'role_changed' ), 50, 3 );
	}

	/**
	 * Admin hooks.
	 *
	 * @return void
	 */
	public static function admin_hooks() {}

	/**
	 * Is a person (not a script) making this change?
	 *
	 * @return int User ID or 0.
	 */
	private static function actor() {
		$id = get_current_user_id();
		if ( $id && ! current_user_can( 'edit_products' ) && ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'promote_users' ) ) {
			$id = 0;
		}
		return (int) apply_filters( 'staffwarden_actor', $id );
	}

	/**
	 * Write one change.
	 *
	 * @param string $type   Type.
	 * @param array  $args   Args.
	 * @return void
	 */
	private static function log( $type, $args ) {
		$args['user_id'] = self::actor();
		StaffWarden_Log::add( $type, $args );
	}

	/* ------------------------------------------------------------------
	 * Products
	 * ---------------------------------------------------------------- */

	/**
	 * Fields watched on products.
	 *
	 * @return array prop => label
	 */
	public static function product_fields() {
		return array(
			'regular_price'  => __( 'Regular price', 'staffwarden' ),
			'sale_price'     => __( 'Sale price', 'staffwarden' ),
			'stock_quantity' => __( 'Stock', 'staffwarden' ),
			'stock_status'   => __( 'Stock status', 'staffwarden' ),
			'status'         => __( 'Status', 'staffwarden' ),
			'name'           => __( 'Name', 'staffwarden' ),
			'sku'            => __( 'SKU', 'staffwarden' ),
		);
	}

	/**
	 * Remember stored values before a product save.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public static function product_before( $product ) {
		if ( ! StaffWarden_Settings::on( 'track_products' ) || ! self::actor() || ! $product->get_id() ) {
			return;
		}
		$changes = array_intersect_key( $product->get_changes(), self::product_fields() );
		if ( ! $changes ) {
			return;
		}
		$post = get_post( $product->get_id() );
		if ( ! $post ) {
			return;
		}
		$meta = array(
			'regular_price'  => '_regular_price',
			'sale_price'     => '_sale_price',
			'stock_quantity' => '_stock',
			'stock_status'   => '_stock_status',
			'sku'            => '_sku',
		);
		$old  = array();
		foreach ( array_keys( $changes ) as $prop ) {
			if ( 'status' === $prop ) {
				$old[ $prop ] = $post->post_status;
			} elseif ( 'name' === $prop ) {
				$old[ $prop ] = $post->post_title;
			} elseif ( isset( $meta[ $prop ] ) ) {
				$old[ $prop ] = get_post_meta( $post->ID, $meta[ $prop ], true );
			}
		}
		self::$before[ $product->get_id() ] = array(
			'old' => $old,
			'new' => $changes,
		);
	}

	/**
	 * Log product changes after the save.
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public static function product_after( $product ) {
		$id = $product->get_id();
		if ( empty( self::$before[ $id ] ) ) {
			return;
		}
		$set = self::$before[ $id ];
		unset( self::$before[ $id ] );
		foreach ( $set['new'] as $prop => $new ) {
			$old = isset( $set['old'][ $prop ] ) ? $set['old'][ $prop ] : '';
			if ( (string) $old === (string) $new ) {
				continue;
			}
			$drop = 0;
			if ( in_array( $prop, array( 'regular_price', 'sale_price' ), true ) && is_numeric( $old ) && is_numeric( $new ) && (float) $new < (float) $old ) {
				$drop = (float) $old - (float) $new;
			}
			self::log(
				'product',
				array(
					'object_id' => $id,
					'field'     => $prop,
					'old'       => is_scalar( $old ) || null === $old ? (string) $old : wp_json_encode( $old ),
					'new'       => is_scalar( $new ) || null === $new ? (string) $new : wp_json_encode( $new ),
					'amount'    => $drop,
					'detail'    => $product->get_name(),
				)
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Orders and refunds
	 * ---------------------------------------------------------------- */

	/**
	 * Staff changed an order status.
	 *
	 * @param int      $id    Order ID.
	 * @param string   $from  Old.
	 * @param string   $to    New.
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function order_status( $id, $from, $to, $order ) {
		if ( ! StaffWarden_Settings::on( 'track_orders' ) || ! self::actor() || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		self::log(
			'order',
			array(
				'object_id' => $id,
				'field'     => 'status',
				'old'       => $from,
				'new'       => $to,
				'amount'    => $order instanceof WC_Order ? (float) $order->get_total() : 0,
				/* translators: %s: order number */
				'detail'    => sprintf( __( 'Order #%s', 'staffwarden' ), $order instanceof WC_Order ? $order->get_order_number() : $id ),
			)
		);
	}

	/**
	 * A refund was made.
	 *
	 * @param int $order_id  Order.
	 * @param int $refund_id Refund.
	 * @return void
	 */
	public static function refunded( $order_id, $refund_id ) {
		if ( ! StaffWarden_Settings::on( 'track_refunds' ) ) {
			return;
		}
		$refund = wc_get_order( $refund_id );
		$order  = wc_get_order( $order_id );
		if ( ! $refund || ! $order ) {
			return;
		}
		$amount = (float) $refund->get_amount();
		$by     = (int) $refund->get_refunded_by();
		$by     = $by ? $by : self::actor();
		StaffWarden_Log::add(
			'refund',
			array(
				'object_id' => $order_id,
				'user_id'   => $by,
				'field'     => 'refund',
				'new'       => $refund->get_reason(),
				'amount'    => $amount,
				/* translators: %s: order number */
				'detail'    => sprintf( __( 'Order #%s', 'staffwarden' ), $order->get_order_number() ),
			)
		);
		$min = (float) StaffWarden_Settings::get( 'refund_min' );
		if ( StaffWarden_Settings::on( 'refund_alert' ) && $amount >= $min && $amount > 0 ) {
			$user = $by ? get_userdata( $by ) : null;
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			self::mail(
				/* translators: 1: amount, 2: site */
				sprintf( __( 'Refund of %1$s on %2$s', 'staffwarden' ), wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), $site ),
				sprintf(
					/* translators: 1: person, 2: amount, 3: order */
					__( '%1$s refunded %2$s on order #%3$s.', 'staffwarden' ),
					$user ? $user->display_name : __( 'Someone', 'staffwarden' ),
					wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ),
					$order->get_order_number()
				) . ( $refund->get_reason() ? "\n" . __( 'Reason:', 'staffwarden' ) . ' ' . $refund->get_reason() : '' ) . "\n\n" . $order->get_edit_order_url()
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Coupons, settings, roles
	 * ---------------------------------------------------------------- */

	/**
	 * Coupon created or its discount changed.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return void
	 */
	public static function coupon_save( $coupon ) {
		if ( ! StaffWarden_Settings::on( 'track_coupons' ) || ! self::actor() ) {
			return;
		}
		$changes = array_intersect_key( $coupon->get_changes(), array_flip( array( 'amount', 'discount_type', 'usage_limit', 'date_expires', 'free_shipping' ) ) );
		if ( ! $coupon->get_id() ) {
			self::log(
				'coupon',
				array(
					'field'  => 'created',
					'new'    => $coupon->get_amount() . ( 'percent' === $coupon->get_discount_type() ? '%' : '' ),
					'detail' => strtoupper( $coupon->get_code() ),
				)
			);
			return;
		}
		if ( ! $changes ) {
			return;
		}
		$stored = new WC_Coupon( $coupon->get_id() );
		foreach ( $changes as $prop => $new ) {
			$getter = 'get_' . $prop;
			$old    = is_callable( array( $stored, $getter ) ) ? $stored->{$getter}( 'edit' ) : '';
			$old    = $old instanceof WC_DateTime ? $old->date( 'Y-m-d' ) : $old;
			$new    = $new instanceof WC_DateTime ? $new->date( 'Y-m-d' ) : $new;
			if ( (string) $old === (string) $new ) {
				continue;
			}
			self::log(
				'coupon',
				array(
					'object_id' => $coupon->get_id(),
					'field'     => $prop,
					'old'       => is_bool( $old ) ? ( $old ? 'yes' : 'no' ) : (string) $old,
					'new'       => is_bool( $new ) ? ( $new ? 'yes' : 'no' ) : (string) $new,
					'detail'    => strtoupper( $coupon->get_code() ),
				)
			);
		}
	}

	/**
	 * Watched store options.
	 *
	 * @param string $name Option.
	 * @return string Label or ''.
	 */
	public static function option_label( $name ) {
		$fixed = array(
			'woocommerce_currency'               => __( 'Store currency', 'staffwarden' ),
			'woocommerce_email_from_address'     => __( 'Store email address', 'staffwarden' ),
			'admin_email'                        => __( 'Site admin email', 'staffwarden' ),
			'users_can_register'                 => __( 'Anyone can register', 'staffwarden' ),
			'default_role'                       => __( 'New user role', 'staffwarden' ),
			'woocommerce_calc_taxes'             => __( 'Taxes on or off', 'staffwarden' ),
			'woocommerce_enable_coupons'         => __( 'Coupons on or off', 'staffwarden' ),
			'woocommerce_manage_stock'           => __( 'Stock management', 'staffwarden' ),
			'woocommerce_enable_guest_checkout'  => __( 'Guest checkout', 'staffwarden' ),
		);
		if ( isset( $fixed[ $name ] ) ) {
			return $fixed[ $name ];
		}
		if ( preg_match( '/^woocommerce_(.+)_settings$/', $name, $m ) && function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$gateways = WC()->payment_gateways()->payment_gateways();
			if ( isset( $gateways[ $m[1] ] ) ) {
				/* translators: %s: payment method */
				return sprintf( __( 'Payment method: %s', 'staffwarden' ), wp_strip_all_tags( $gateways[ $m[1] ]->get_method_title() ) );
			}
		}
		return '';
	}

	/**
	 * A watched option changed.
	 *
	 * @param string $name Option.
	 * @param mixed  $old  Old value.
	 * @param mixed  $new  New value.
	 * @return void
	 */
	public static function option_changed( $name, $old, $new ) {
		if ( 0 !== strpos( $name, 'woocommerce_' ) && ! in_array( $name, array( 'admin_email', 'users_can_register', 'default_role' ), true ) ) {
			return;
		}
		if ( ! StaffWarden_Settings::on( 'track_settings' ) || ! self::actor() ) {
			return;
		}
		$label = self::option_label( $name );
		if ( '' === $label ) {
			return;
		}
		$old_text = is_scalar( $old ) ? (string) $old : '';
		$new_text = is_scalar( $new ) ? (string) $new : '';
		if ( is_array( $old ) && is_array( $new ) ) {
			$keys     = array();
			$all_keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );
			foreach ( $all_keys as $k ) {
				$a = isset( $old[ $k ] ) ? $old[ $k ] : null;
				$b = isset( $new[ $k ] ) ? $new[ $k ] : null;
				if ( $a !== $b ) {
					$keys[] = $k;
				}
			}
			if ( ! $keys ) {
				return;
			}
			$secret   = (bool) preg_grep( '/key|secret|token|password|webhook/i', $keys );
			$old_text = '';
			/* translators: %s: setting names */
			$new_text = sprintf( __( 'Changed: %s', 'staffwarden' ), implode( ', ', array_slice( $keys, 0, 8 ) ) ) . ( $secret ? ' ' . __( '(includes keys)', 'staffwarden' ) : '' );
		}
		self::log(
			'setting',
			array(
				'field'  => substr( sanitize_key( $name ), 0, 60 ),
				'old'    => $old_text,
				'new'    => $new_text,
				'detail' => $label,
			)
		);
	}

	/**
	 * A user's role changed.
	 *
	 * @param int    $user_id   User.
	 * @param string $role      New role.
	 * @param array  $old_roles Old roles.
	 * @return void
	 */
	public static function role_changed( $user_id, $role, $old_roles ) {
		if ( ! StaffWarden_Settings::on( 'track_roles' ) || ! self::actor() || in_array( $role, (array) $old_roles, true ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		self::log(
			'role',
			array(
				'object_id' => $user_id,
				'field'     => 'role',
				'old'       => implode( ', ', (array) $old_roles ),
				'new'       => $role,
				'detail'    => $user ? $user->user_login : '#' . $user_id,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Jobs
	 * ---------------------------------------------------------------- */

	/**
	 * Hourly.
	 *
	 * @return void
	 */
	public static function hourly() {}

	/**
	 * Daily.
	 *
	 * @return void
	 */
	public static function daily() {}

	/**
	 * Email the owner from the store's address.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 * @return bool
	 */
	public static function mail( $subject, $body ) {
		$to      = StaffWarden_Settings::get( 'notify_to' );
		$to      = '' !== $to ? array_map( 'trim', explode( ',', $to ) ) : array( get_option( 'admin_email' ) );
		$headers = array();
		$from    = get_option( 'woocommerce_email_from_address' );
		if ( is_email( $from ) ) {
			$site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$headers[] = 'From: ' . str_replace( array( "\r", "\n", '<', '>' ), '', (string) get_option( 'woocommerce_email_from_name', $site ) ) . ' <' . $from . '>';
		}
		return wp_mail( $to, $subject, $body, $headers );
	}

	/* ------------------------------------------------------------------
	 * Screens
	 * ---------------------------------------------------------------- */

	/**
	 * Kinds of change.
	 *
	 * @return array
	 */
	public static function kinds() {
		return array(
			'product' => __( 'Products', 'staffwarden' ),
			'order'   => __( 'Orders', 'staffwarden' ),
			'refund'  => __( 'Refunds', 'staffwarden' ),
			'coupon'  => __( 'Coupons', 'staffwarden' ),
			'setting' => __( 'Settings', 'staffwarden' ),
			'role'    => __( 'User roles', 'staffwarden' ),
		);
	}

	/**
	 * Readable description of one change.
	 *
	 * @param array $row Log row.
	 * @return string
	 */
	public static function describe( $row ) {
		$fields = self::product_fields();
		switch ( $row['type'] ) {
			case 'product':
				$what = isset( $fields[ $row['field'] ] ) ? $fields[ $row['field'] ] : $row['field'];
				return $what . ': ' . self::value( $row['field'], $row['old_value'] ) . ' > ' . self::value( $row['field'], $row['new_value'] );
			case 'order':
				return __( 'Status', 'staffwarden' ) . ': ' . wc_get_order_status_name( $row['old_value'] ) . ' > ' . wc_get_order_status_name( $row['new_value'] );
			case 'refund':
				return __( 'Refunded', 'staffwarden' ) . ' ' . wp_strip_all_tags( wc_price( (float) $row['amount'] ) ) . ( '' !== $row['new_value'] ? ', ' . $row['new_value'] : '' );
			case 'coupon':
				if ( 'created' === $row['field'] ) {
					return __( 'Coupon created', 'staffwarden' ) . ': ' . $row['new_value'];
				}
				return $row['field'] . ': ' . ( '' !== $row['old_value'] ? $row['old_value'] : '–' ) . ' > ' . ( '' !== $row['new_value'] ? $row['new_value'] : '–' );
			case 'role':
				return __( 'Role', 'staffwarden' ) . ': ' . ( '' !== $row['old_value'] ? $row['old_value'] : '–' ) . ' > ' . $row['new_value'];
			default:
				return '' !== $row['old_value'] ? $row['old_value'] . ' > ' . $row['new_value'] : $row['new_value'];
		}
	}

	/**
	 * Format a value for display.
	 *
	 * @param string $field Field.
	 * @param string $value Value.
	 * @return string
	 */
	private static function value( $field, $value ) {
		if ( '' === $value ) {
			return '–';
		}
		if ( in_array( $field, array( 'regular_price', 'sale_price' ), true ) && is_numeric( $value ) ) {
			return wp_strip_all_tags( wc_price( (float) $value ) );
		}
		return $value;
	}

	/**
	 * Name of the person.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function who( $user_id ) {
		$user = $user_id ? get_userdata( (int) $user_id ) : null;
		return $user ? $user->display_name : __( 'Automatic', 'staffwarden' );
	}

	/**
	 * Link for the changed thing.
	 *
	 * @param array $row Row.
	 * @return string
	 */
	private static function link( $row ) {
		$id = (int) $row['object_id'];
		if ( ! $id ) {
			return esc_html( $row['detail'] );
		}
		if ( in_array( $row['type'], array( 'order', 'refund' ), true ) ) {
			$order = wc_get_order( $id );
			$url   = $order ? $order->get_edit_order_url() : '';
		} elseif ( 'role' === $row['type'] ) {
			$url = get_edit_user_link( $id );
		} else {
			$post = get_post( $id );
			$id   = $post && $post->post_parent ? $post->post_parent : $id;
			$url  = get_edit_post_link( $id );
		}
		return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $row['detail'] ) . '</a>' : esc_html( $row['detail'] );
	}

	/**
	 * Table rows for log entries.
	 *
	 * @param array $entries Rows.
	 * @return array
	 */
	private static function rows( $entries ) {
		$a     = 'StaffWarden_Admin';
		$kinds = self::kinds();
		$rows  = array();
		foreach ( $entries as $row ) {
			$risky  = ( 'refund' === $row['type'] ) || ( 'product' === $row['type'] && (float) $row['amount'] > 0 ) || in_array( $row['type'], array( 'role', 'setting' ), true );
			$rows[] = array(
				'when'      => $a::ago( $row['created_at'] ),
				'who'       => self::who( $row['user_id'] ),
				'html_kind' => $a::tag( $risky ? 'is-would' : '', isset( $kinds[ $row['type'] ] ) ? $kinds[ $row['type'] ] : $row['type'] ),
				'html_item' => self::link( $row ),
				'change'    => self::describe( $row ),
			);
		}
		return $rows;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	private static function columns() {
		return array(
			'when'      => __( 'When', 'staffwarden' ),
			'who'       => __( 'Who', 'staffwarden' ),
			'html_kind' => __( 'Area', 'staffwarden' ),
			'html_item' => __( 'Item', 'staffwarden' ),
			'change'    => __( 'Change', 'staffwarden' ),
		);
	}

	/**
	 * People who made changes recently.
	 *
	 * @param int $secs Window.
	 * @return int[]
	 */
	public static function active_staff( $secs ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, report.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM %i WHERE user_id > 0 AND created_at >= %s', StaffWarden_Log::table(), gmdate( 'Y-m-d H:i:s', time() - $secs ) ) ) );
	}

	/**
	 * Overview.
	 *
	 * @return void
	 */
	public static function render_overview() {
		$a = 'StaffWarden_Admin';
		$a::banner( 'is-on', __( 'Every change is recorded', 'staffwarden' ), __( 'Prices, stock, orders, refunds, coupons, payment settings and user roles changed by people.', 'staffwarden' ) );
		$a::figures(
			array(
				__( 'Changes, 7 days', 'staffwarden' )        => number_format_i18n( StaffWarden_Log::count( array_keys( self::kinds() ), WEEK_IN_SECONDS ) ),
				__( 'Refunded, 7 days', 'staffwarden' )       => wp_strip_all_tags( wc_price( StaffWarden_Log::sum( 'refund', WEEK_IN_SECONDS ) ) ),
				__( 'Price cuts, 7 days', 'staffwarden' )     => number_format_i18n( self::price_cuts( WEEK_IN_SECONDS ) ),
				__( 'People active, 7 days', 'staffwarden' )  => number_format_i18n( count( self::active_staff( WEEK_IN_SECONDS ) ) ),
			)
		);
		$a::panel_open( __( 'Latest changes', 'staffwarden' ), __( 'Refunds, price cuts, settings and roles are highlighted.', 'staffwarden' ) );
		$rows = self::rows(
			StaffWarden_Log::recent(
				array(
					'types' => array_keys( self::kinds() ),
					'limit' => 15,
				)
			)
		);
		if ( $rows ) {
			$a::table( self::columns(), $rows );
		} else {
			$a::empty_state( __( 'No changes yet', 'staffwarden' ), __( 'Edit a product or an order and it shows up here.', 'staffwarden' ) );
		}
		$a::panel_close();
	}

	/**
	 * Price cuts in a window.
	 *
	 * @param int $secs Window.
	 * @return int
	 */
	private static function price_cuts( $secs ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- dashboard figure.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type = %s AND amount > 0 AND created_at >= %s', StaffWarden_Log::table(), 'product', gmdate( 'Y-m-d H:i:s', time() - $secs ) ) );
	}

	/**
	 * Timeline with filters.
	 *
	 * @return void
	 */
	public static function render_timeline() {
		$a = 'StaffWarden_Admin';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$kind  = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$staff = isset( $_GET['staff'] ) ? absint( $_GET['staff'] ) : 0;
		$page  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$kinds = self::kinds();
		$types = isset( $kinds[ $kind ] ) ? array( $kind ) : array_keys( $kinds );
		?>
		<form method="get" class="wd-filter staffwarden-panel">
			<input type="hidden" name="page" value="staffwarden">
			<input type="hidden" name="tab" value="timeline">
			<select name="kind" aria-label="<?php esc_attr_e( 'Area', 'staffwarden' ); ?>">
				<option value=""><?php esc_html_e( 'All areas', 'staffwarden' ); ?></option>
				<?php foreach ( $kinds as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $kind, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="staff" aria-label="<?php esc_attr_e( 'Person', 'staffwarden' ); ?>">
				<option value="0"><?php esc_html_e( 'Everyone', 'staffwarden' ); ?></option>
				<?php foreach ( self::active_staff( 90 * DAY_IN_SECONDS ) as $uid ) : ?>
					<option value="<?php echo esc_attr( (string) $uid ); ?>" <?php selected( $staff, $uid ); ?>><?php echo esc_html( self::who( $uid ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button staffwarden-btn-secondary"><?php esc_html_e( 'Show', 'staffwarden' ); ?></button>
		</form>
		<?php
		$per     = 50;
		$entries = StaffWarden_Log::recent(
			array(
				'types'   => $types,
				'user_id' => $staff,
				'limit'   => $per + 1,
				'offset'  => ( $page - 1 ) * $per,
			)
		);
		$more    = count( $entries ) > $per;
		$rows    = self::rows( array_slice( $entries, 0, $per ) );
		$a::panel_open( __( 'Timeline', 'staffwarden' ) );
		if ( $rows ) {
			$a::table( self::columns(), $rows );
		} else {
			$a::empty_state( __( 'Nothing found', 'staffwarden' ), __( 'Try another area or person.', 'staffwarden' ) );
		}
		$a::panel_close();
		if ( $page > 1 || $more ) {
			echo '<div class="staffwarden-actions">';
			if ( $page > 1 ) {
				echo '<a class="button staffwarden-btn-secondary" href="' . esc_url( $a::url( 'timeline', array( 'kind' => $kind, 'staff' => $staff, 'paged' => $page - 1 ) ) ) . '">' . esc_html__( 'Newer', 'staffwarden' ) . '</a> ';
			}
			if ( $more ) {
				echo '<a class="button staffwarden-btn-secondary" href="' . esc_url( $a::url( 'timeline', array( 'kind' => $kind, 'staff' => $staff, 'paged' => $page + 1 ) ) ) . '">' . esc_html__( 'Older', 'staffwarden' ) . '</a>';
			}
			echo '</div>';
		}
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	public static function render_settings() {
		$a = 'StaffWarden_Admin';
		$a::form_open( 'settings' );
		$a::panel_open( __( 'What to record', 'staffwarden' ) );
		$a::row(
			__( 'Changes by people', 'staffwarden' ),
			__( 'Automatic changes, like stock going down after a sale, are not recorded.', 'staffwarden' ),
			static function () use ( $a ) {
				$a::switch_field( 'track_products', __( 'Product prices, stock, status, name and SKU', 'staffwarden' ) );
				$a::switch_field( 'track_orders', __( 'Order status changes', 'staffwarden' ) );
				$a::switch_field( 'track_refunds', __( 'Refunds', 'staffwarden' ) );
				$a::switch_field( 'track_coupons', __( 'New coupons and discount changes', 'staffwarden' ) );
				$a::switch_field( 'track_settings', __( 'Payment methods and key store settings', 'staffwarden' ) );
				$a::switch_field( 'track_roles', __( 'User role changes', 'staffwarden' ) );
			}
		);
		$a::panel_close();
		$a::panel_open( __( 'Refund email', 'staffwarden' ) );
		$a::row(
			__( 'Big refunds', 'staffwarden' ),
			__( 'Know right away when money goes back out.', 'staffwarden' ),
			static function () use ( $a ) {
				$a::switch_field( 'refund_alert', __( 'Email me about refunds', 'staffwarden' ) );
				/* translators: %1$s: amount */
				$a::sentence( __( 'Of %1$s or more in your store currency.', 'staffwarden' ), array( 'refund_min' => __( 'Amount', 'staffwarden' ) ) );
				$a::text( 'notify_to', __( 'Email addresses', 'staffwarden' ), get_option( 'admin_email' ) );
			}
		);
		$a::panel_close();
		$a::panel_open( __( 'Data', 'staffwarden' ) );
		$a::row(
			__( 'History', 'staffwarden' ),
			__( 'Older changes are removed automatically.', 'staffwarden' ),
			static function () use ( $a ) {
				/* translators: %1$s: days */
				$a::sentence( __( 'Keep changes for %1$s days.', 'staffwarden' ), array( 'retention_days' => __( 'Days', 'staffwarden' ) ) );
				$a::switch_field( 'delete_data', __( 'Delete settings and data when the plugin is deleted', 'staffwarden' ) );
			}
		);
		$a::panel_close();
		$a::form_close();
	}
}
