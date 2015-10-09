<?php

	class Shop_StatusTransition extends Db_ActiveRecord 
	{
		public $table_name = 'shop_status_transitions';  
		public $stateId = -1;
		
		public $calculated_columns = array( 
			'to_color'=>array('sql'=>"shop_order_statuses.color", 'join'=>array('shop_order_statuses'=>'shop_order_statuses.id=to_state_id')),
			'to_name'=>array('sql'=>"shop_order_statuses.name"),
			'role_name'=>array('sql'=>"select name from shop_roles where shop_roles.id=role_id")
		);

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('to_state_id', 'Destination Status')->validation()->required('Please select a destination status.');
			$this->define_column('role_id', 'Role')->validation()->required('Please select role.');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('to_state_id')->comment('Please select status an order can be transferred from current status.', 'above')->renderAs(frm_dropdown)->emptyOption('<please select>');
			$this->add_form_field('role_id')->comment('Please select a role of users who can transfer orders from the current status to the status you specified above.', 'above')->renderAs(frm_dropdown)->emptyOption('<please select>');
		}

		public function get_to_state_id_options()
		{
			$result = array();

			$records = Shop_OrderStatus::create()->order('id');
			if (strlen($this->stateId))
				$records->where('id <> ?', $this->stateId);

			return $records->find_all()->as_array('name', 'id');
		}
		
		public function get_role_id_options()
		{
			return Shop_Role::create()->order('id')->find_all()->as_array('name', 'id');
		}
		
		public static function listAvailableTransitions($role_id, $from_state_id, $to_state_id = null)
		{
			$obj = self::create();
			if ($role_id)
				$obj->where('role_id=?', $role_id);

			$obj->where('from_state_id=?', $from_state_id);
			
			if ($to_state_id !== null)
				$obj->where('to_state_id=?', $to_state_id);
				
			return $obj->find_all();
		}
		
		public static function listAvailableTransitionsMulti($role_id, $from_state_ids)
		{
			$end_statuses = array();
			$all_end_statuses = array();

			foreach ($from_state_ids as $from_id)
			{
				$transitions = self::listAvailableTransitions($role_id, $from_id);

				if (!array_key_exists($from_id, $end_statuses))
					$end_statuses[$from_id] = array();

				foreach ($transitions as $transition)
				{
					$end_statuses[$from_id][$transition->to_state_id] = $transition;

					$all_end_statuses[$transition->to_state_id] = $transition;
				}
			}

			$result = array();
			foreach ($all_end_statuses as $end_status_id=>$end_status)
			{
				foreach ($end_statuses as $current_statuses)
				{
					if (!count($current_statuses))
						return array();
					
					$current_status_found = false;
					foreach ($current_statuses as $current_status)
					{
						if ($current_status->to_state_id == $end_status_id)
							$current_status_found = true;
					}
					
					if (!$current_status_found)
						continue 2;
				}

				$result[] = $end_status;
			}
			
			return $result;
		}
		
		/**
		 * Returns true in case if a current user could change requests states 
		 */
		public static function userTransitionsAllowed()
		{
			$user = Phpr::$security->getUser();
			$role = $user->role;
			
			if (!$role)
				return false;

			return Db_DbHelper::scalar('select count(*) from shop_status_transitions where role_id=?', $role->id);
		}
	}
?>