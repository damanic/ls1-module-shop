<?php

	/**
	 * Represents a customer's product review and rating record. 
	 * Objects of this class are available through the {@link Shop_Product::list_reviews()} and {@link Shop_Product::list_all_reviews()} methods.
	 * @documentable
	 * @property string $author Specifies the review author name.
	 * @property Phpr_DateTime $created_at Specifies the review date and time. 
	 * @property float $rating Specifies a rating assigned to the review. 
	 * This field could be empty if rating has not been assigned.
	 * @property string $review_author_email Specifies the author's email address.
	 * @property string $review_text Specifies the review text.
	 * @property string $title Specifies the review title.
	 * @see http://lemonstand.com/docs/displaying_product_rating_and_reviews Displaying product rating and reviews
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ProductReview extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_reviews';
		
		const status_new = 'new';
		const status_approved = 'approved';

		public static $moderation_statuses = array(
			'new'=>'New',
			'approved'=>'Approved'
		);

		public $belongs_to = array(
			'customer_link'=>array('class_name'=>'Shop_Customer', 'foreign_key'=>'created_customer_id'),
			'product_link'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'prv_product_id')
		);
		
		public $calculated_columns = array(
			'review_status'=>array('sql'=>"if(prv_moderation_status = 'new', 'New', 'Approved')"),
			'author'=>'review_author_name',
			'rating'=>'prv_rating',
			'title'=>'review_title'
		);

		public $prv_moderation_status = 'new';
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('review_status', 'Status');
			$this->define_column('prv_moderation_status', 'Status')->invisible();
			$this->define_relation_column('product_link', 'product_link', 'Product', db_varchar, '@name');
			$this->define_column('prv_rating', 'Rating')->type(db_text);
			$this->define_column('created_at', 'Created At')->dateFormat('%x %H:%M')->order('desc');
			$this->define_column('customer_ip', 'Author IP')->defaultInvisible();
			
			$this->define_column('review_title', 'Title')->validation()->fn('trim')->required("Please specify the review title");
			$this->define_column('review_author_name', 'Author')->validation()->fn('trim')->method('validate_author_name');
			$this->define_column('review_author_email', 'Author Email')->validation()->fn('trim')->email(true, 'Please specify a valid email address')->method('validate_author_email');
			$this->define_column('review_text', 'Review Text')->invisible()->validation()->fn('trim')->required("Please enter the review text");
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendProductReviewModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('prv_moderation_status')->renderAs(frm_dropdown)->tab('Review');
			$this->add_form_field('review_title')->tab('Review');
			$this->add_form_field('review_author_name', 'left')->tab('Review');
			$this->add_form_field('review_author_email', 'right')->tab('Review');
			$this->add_form_field('prv_rating')->tab('Review')->renderAs(frm_dropdown)->emptyOption('<no rating specified>');
			
			$this->add_form_field('review_text')->tab('Review');
			
			Backend::$events->fireEvent('shop:onExtendProductReviewForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductReviewFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}

			return false;
		}

		public function get_prv_moderation_status_options($key_value = -1)
		{
			return self::$moderation_statuses;
		}
		
		public function get_prv_rating_options($key_value = -1)
		{
			return array(
				1 => '1 star',
				2 => '2 stars',
				3 => '3 stars',
				4 => '4 stars',
				5 => '5 stars',
			);
		}
		
		public function get_rating_name()
		{
			if (!$this->prv_rating)
				return null;
				
			$options = $this->get_prv_rating_options();
			if (!array_key_exists($this->prv_rating, $options))
				return null;
				
			return $options[$this->prv_rating];
		}
		
		public function before_create($deferred_session_key = null) 
		{
			$this->customer_ip = Phpr::$request->getUserIp();
			Backend::$events->fireEvent('shop:onProductReviewBeforeCreate', $this);
		}
		
		public function after_create()
		{
			$config = Shop_ReviewsConfiguration::create();

			if ($config->send_notifications)
			{
				try
				{
					$template = System_EmailTemplate::create()->find_by_code('shop:product_review_internal');
					if ($template)
					{
						$customer_email = $email = trim($this->review_author_email);
						if (!strlen($email))
							$email = 'email is not specified';

						$product = Shop_Product::create()->find($this->prv_product_id);
						$rating_name = $this->get_rating_name();
						if (!strlen($rating_name))
							$rating_name = '<no rating provided>';

						$review_edit_url = Phpr::$request->getRootUrl().url('shop/reviews/edit/'.$this->id.'?'.uniqid());
						$message = $this->set_email_variables($template->content, $rating_name, $review_edit_url, $product, $email);
						$template->subject = $this->set_email_variables($template->subject, $rating_name, $review_edit_url, $product, $email);
						
						$users = Users_User::list_users_having_permission('shop', 'manage_products');
						
						$template->send_to_team($users, $message, null, null, $customer_email, $this->review_author_name);
					}
				}
				catch (exception $ex) {}
			}
			
			Backend::$events->fireEvent('shop:onProductReviewAfterCreate', $this);
		}
		
		protected function set_email_variables($message, $rating_name, $review_edit_url, $product, $email)
		{
			$message = str_replace('{review_author_name}', h($this->review_author_name), $message);
			$message = str_replace('{review_author_email}', h($email), $message);
			$message = str_replace('{review_product_name}', h($product->name), $message);
			$message = str_replace('{review_text}', nl2br(h($this->review_text)), $message);
			$message = str_replace('{review_title}', h($this->review_title), $message);
			$message = str_replace('{review_rating}', h($rating_name), $message);
			$message = str_replace('{review_edit_url}', h($review_edit_url), $message);
			
			return $message;
		}
		
		public static function create_review($product, $customer, $review_data)
		{
			if (!$product)
				throw new Phpr_ApplicationException('Product not found');
				
			if ($product->grouped)
				$product = $product->master_grouped_product;

			if (!$product)
				throw new Phpr_ApplicationException('Product not found');
			
			$config = Shop_ReviewsConfiguration::create();
				
			if ($config->no_duplicate_reviews && self::ip_customer_review_exists(Phpr::$request->getUserIp(), $customer, $product->id))
			{
				$message = trim($config->duplicate_review_message);
				if (!strlen($message))
					$message = 'Posting multiple reviews for a single product is not allowed.';

				throw new Phpr_ApplicationException($message);
			}
			
			$rating = isset($review_data['rating']) ? $review_data['rating'] : null;
			$review_data['prv_rating'] = $rating;
			
			if ($config->rating_required && !$rating)
				throw new Phpr_ApplicationException('Please specify the product rating.');
				
			if ($rating > 5)
				throw new Phpr_ApplicationException('Product rating cannot be more than 5.');

			$obj = self::create();
			$obj->validation->focusPrefix = null;
			$obj->prv_product_id = $product->id;
			$obj->created_customer_id = $customer ? $customer->id : null;
			
			if ($customer)
			{
				$review_data['review_author_name'] = $customer->get_display_name();
				$review_data['review_author_email'] = $customer->email;
			}

			$obj->save($review_data);
		}
		
		public function validate_author_name($name, $value)
		{
			if (!$this->created_customer_id && !strlen(trim($value)))
				$this->validation->setError('Please specify your name', $name, true);
				
			return true;
		}
		
		public function validate_author_email($name, $value)
		{
			$config = Shop_ReviewsConfiguration::create();
			if (!$config->email_required)
				return true;
			
			if (!$this->created_customer_id && !strlen(trim($value)))
				$this->validation->setError('Please specify your email address', $name, true);
				
			return true;
		}
		
		public static function ip_customer_review_exists($ip, $customer, $product_id)
		{
			$customer_id = $customer ? $customer->id : null;
			
			$bind = array(
				'customer_ip'=>$ip, 
				'created_customer_id'=>$customer_id,
				'prv_product_id'=>$product_id);

			if ($customer_id)
				return Db_DbHelper::scalar('select count(*) from shop_product_reviews where created_customer_id=:created_customer_id and prv_product_id=:prv_product_id', $bind);
			else
				return Db_DbHelper::scalar('select count(*) from shop_product_reviews where customer_ip=:customer_ip and prv_product_id=:prv_product_id', $bind);
		}
		
		public function approve()
		{
			$bind = array(
				'status'=>self::status_approved,
				'id'=>$this->id
			);
			Db_DbHelper::query('update shop_product_reviews set prv_moderation_status=:status where id=:id', $bind);
			Shop_Product::update_rating_fields($this->prv_product_id);

			if ($this->fetched['prv_moderation_status'] != 'approved')
				$this->triggerApproved();
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Product::update_rating_fields($this->prv_product_id);

			if ($this->prv_moderation_status == 'approved' 
				&& isset($this->fetched['prv_moderation_status']) 
				&& $this->fetched['prv_moderation_status'] != $this->prv_moderation_status) 
			{
				$this->triggerApproved();
			}
		}

		protected function triggerApproved()
		{
			Backend::$events->fireEvent('shop:onProductReviewApproved', $this);
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the product review model.
		 * The event handler should accept two parameters - the review object and the form 
		 * execution context string. To add new columns to the review model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the review object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_product_reviews</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductReviewModel', $this, 'extend_product_review_model');
		 *   Backend::$events->addEvent('shop:onExtendProductReviewForm', $this, 'extend_product_review_model');
		 * }
		 * 
		 * public function extend_product_review_model($product_review)
		 * {
		 *   $product_review->define_column('x_would_recommend', 'Would recommend this product to a friend');
		 * }
		 *      
		 * public function extend_product_review_model($product_review, $context)
		 * {
		 *   $product_review->add_form_field('x_would_recommend')->tab('Review');
		 * }
		 * </pre>
		 * @event shop:onExtendProductReviewModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductReviewForm
		 * @see shop:onGetProductReviewFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductReview $review Specifies the review object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendProductReviewModel($review, $context) {}
			
		/**
		 * Allows to add new fields to the Edit Product Review form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendProductReviewModel} event. 
		 * To add new fields to the review form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * review object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductReviewModel', $this, 'extend_product_review_model');
		 *   Backend::$events->addEvent('shop:onExtendProductReviewForm', $this, 'extend_product_review_model');
		 * }
		 * 
		 * public function extend_product_review_model($product_review)
		 * {
		 *   $product_review->define_column('x_would_recommend', 'Would recommend this product to a friend');
		 * }
		 *      
		 * public function extend_product_review_model($product_review, $context)
		 * {
		 *   $product_review->add_form_field('x_would_recommend')->tab('Review');
		 * }
		 * </pre>
		 * @event shop:onExtendProductReviewForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductReviewModel
		 * @see shop:onGetProductReviewFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductReview $review Specifies the review object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendProductReviewForm($review, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendProductReviewForm} event.
		 * Usually you do not need to use this event for fields which represent 
		 * {@link http://lemonstand.com/docs/extending_models_with_related_columns data relations}. But if you want a standard 
		 * field (corresponding an integer-typed database column, for example), to be rendered as a drop-down list, you should 
		 * handle this event.
		 *
		 * The event handler should accept 2 parameters - the field name and a current field value. If the current
		 * field value is -1, the handler should return an array containing a list of options. If the current 
		 * field value is not -1, the handler should return a string (label), corresponding the value.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductReviewModel', $this, 'extend_product_review_model');
		 *   Backend::$events->addEvent('shop:onExtendProductReviewForm', $this, 'extend_product_review_form');
		 *   Backend::$events->addEvent('shop:onGetProductReviewFieldOptions', $this, 'get_product_review_field_options');
		 * }
		 * 
		 * public function extend_product_review_model($review)
		 * {
		 *   $review->define_column('x_color', 'Color');
		 * }
		 * 
		 * public function extend_product_review_form($review, $context)
		 * {
		 *   $review->add_form_field('x_color')->tab('Options')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_product_review_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_color')
		 *   {
		 *     $options = array(
		 *       0 => 'Red',
		 *       1 => 'Green',
		 *       2 => 'Blue'
		 *     );
		 *     
		 *     if ($current_key_value == -1)
		 *       return $options;
		 *     
		 *     if (array_key_exists($current_key_value, $options))
		 *       return $options[$current_key_value];
		 *   }
		 * }
		 * </pre>
		 * @event shop:onGetProductReviewFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductReviewModel
		 * @see shop:onExtendProductReviewForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetProductReviewFieldOptions($db_name, $field_value) {}

		/**
		 * Triggered before a new product review record is created. 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onProductReviewBeforeCreate', $this, 'before_create_product_review');
		 * }
		 *
		 * public function before_create_product_review($product_review)
		 * {
		 *   //do something here
		 * }
		 * </pre>
		 * @event shop:onProductReviewBeforeCreate
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onProductReviewAfterCreate
		 * @see shop:onProductReviewApproved
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductReview $review Specifies the review object.
		 */
		private function event_onProductReviewBeforeCreate($review) {}
		
		/**
		 * Triggered after a new product review record is created. 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onProductReviewAfterCreate', $this, 'after_create_product_review');
		 * }
		 *
		 * public function after_create_product_review($product_review)
		 * {
		 *   //do something here
		 * }
		 * </pre>
		 * @event shop:onProductReviewAfterCreate
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onProductReviewBeforeCreate
		 * @see shop:onProductReviewApproved
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductReview $review Specifies the review object.
		 */
		private function event_onProductReviewAfterCreate($review) {}
		
		/**
		 * Triggered after a product review is approved. 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onProductReviewApproved', $this, 'product_review_approved');
		 * }
		 *
		 * public function product_review_approved($product_review)
		 * {
		 *   //do something here
		 * }
		 * </pre>
		 * @event shop:onProductReviewApproved
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onProductReviewBeforeCreate
		 * @see shop:onProductReviewAfterCreate
		 * @param Shop_ProductReview $review Specifies the review object.
		 */
		private function event_onProductReviewApproved($review) {}
	}

?>