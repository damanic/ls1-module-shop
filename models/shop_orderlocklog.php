<?php
	/**
	 * Logs the locked/unlocked state of an order
	 * @documentable
	 * @see Shop_Order
	 * @package shop.models
	 * @author github:damanic
	 */
	class Shop_OrderLockLog extends Db_ActiveRecord {
		public $table_name = 'shop_order_lock_logs';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = false;


		public $calculated_columns = array(
			'status_name'=>array(
				'sql'=>'shop_order_statuses.name', 
				'join'=>array('shop_order_statuses'=>'shop_order_statuses.id=status_id'), 'type'=>db_text),
			'status_color'=>array('sql'=>'shop_order_statuses.color')
		);

		public static function create() {
			return new self();
		}

		/**
		 * Adds a lock/unlock event record to the log
		 * @param Shop_Order $order The orders lock state will be logged
		 * @param null $comment Optional comment to accompany the log
		 *
		 * @return void
		 */
		public static function add_log($order, $comment=null) {
			$log_record = self::create();
			$log_record->init_columns_info();
			$log_record->status_id = $order->status_id;
			$log_record->order_id = $order->id;
			$log_record->comment = $comment;
			$log_record->locked_state = $order->is_order_locked() ? 1 : null;
			$log_record->save();
		}

		public function define_columns($context = null) {
			$this->define_column('comment', 'Comment')->validation()->fn('trim');
		}

		public function define_form_fields($context = null) {}

	}

?>