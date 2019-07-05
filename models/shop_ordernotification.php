<?php

/**
 * This model represents a notification which has been sent to a customer in relation to an order
 */
class Shop_OrderNotification extends Shop_CustomerNotification {


	public static function add_message($order, $customer, $message_text, $subject, $user_id = null, $reply_to = array()) {
		try
		{
			$obj = self::create();
			$obj->order_id = $order->id;
			$obj->customer_id = $customer->id;
			$obj->email = $order->billing_email;
			$obj->subject = $subject;
			$obj->message = $message_text;

			if ($reply_to)
			{
				$keys = array_keys($reply_to);
				$values = array_values($reply_to);
				$obj->reply_to_email = $keys[0];
				$obj->reply_to_name = $values[0];
			} else
			{
				$params = System_EmailParams::get();
				$obj->reply_to_email = $params->sender_email;
				$obj->reply_to_name = $params->sender_name;
			}

			$obj->save();
		} catch (Exception $ex) {}
	}

	public static function add_system_message($order, $users, $message_text, $subject, $user_id = null, $reply_to = array()) {
		try
		{
			$obj = self::create();
			$obj->order_id = $order->id;

			$emails = array();
			foreach ($users as $user)
				$emails[] = $user->email;

			$obj->email = implode(', ', $emails);

			$obj->subject = $subject;
			$obj->message = $message_text;
			$obj->is_system = 1;

			if ($reply_to)
			{
				$keys = array_keys($reply_to);
				$values = array_values($reply_to);
				$obj->reply_to_email = $keys[0];
				$obj->reply_to_name = $values[0];
			} else
			{
				$params = System_EmailParams::get();
				$obj->reply_to_email = $params->sender_email;
				$obj->reply_to_name = $params->sender_name;
			}

			$obj->save();
		} catch (Exception $ex) {}
	}
}