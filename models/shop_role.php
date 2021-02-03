<?php

	class Shop_Role extends Db_ActiveRecord
	{
		public $table_name = 'shop_roles';
		
		public $calculated_columns = array( 
			'user_num'=>array('sql'=>'select count(*) from users where shop_role_id=shop_roles.id', 'type'=>db_number),
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify role name");
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('can_create_orders', 'Can create orders')->validation();
			$this->define_column('notified_on_out_of_stock', 'Stock level notifications')->validation();
			$this->define_column('user_num', 'Number of users');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->tab('Role');
			$this->add_form_field('description')->tab('Role');
			$this->add_form_field('can_create_orders')->tab('Role')->comment('If checked, users with this role will able to create new orders.');
			$this->add_form_field('notified_on_out_of_stock')->tab('Role')->comment('If checked, users with this role will be notified about products in low and out of stock states.');
		}        
		
		public function before_delete($id=null)
		{
			$in_use = Db_DbHelper::scalar('select count(*) from users where shop_role_id=:id', array('id'=>$this->id));
			if ($in_use)
				throw new Phpr_ApplicationException("The role cannot be deleted because $in_use user(s) have this role assigned.");
		}

		public static function get_users_notified_on_out_of_stock(){
			$users = Users_User::create()->from('users', 'distinct users.*');
			$users->join('shop_roles', 'shop_roles.id=users.shop_role_id');
			$users->where('shop_roles.notified_on_out_of_stock is not null and shop_roles.notified_on_out_of_stock=1');
			$users->where('(users.status is null or users.status = 0)');
			return $users->find_all();
		}
	}

?>