<?

/**
 * Presents payment dispute information.
 * Objects of this class are returned by some methods of {@link Shop_PaymentMethod} class.
 * @documentable
 * @see     Shop_PaymentMethod
 * @package shop.models
 * @author  MJM
 */
class Shop_TransactionDisputeUpdate {

	/**
	 * @var string Specifies the dispute case identifier
	 * @documentable
	 */
	public $case_id;

	/**
	 * @var string Specifies the transaction amount disputed.
	 * @documentable
	 */
	public $amount_disputed;

	/**
	 * @var string Specifies the transaction amount reversed/lost in the dispute
	 * @documentable
	 */
	public $amount_lost;

	/**
	 * @var string A description of the reason this dispute was raised
	 * @documentable
	 */
	public $reason_description;

	/**
	 * @var string A description of the current status for this dispute
	 * @documentable
	 */
	public $status_description;

	/**
	 * @var int Case closed flag
	 * indicates that the dispute has been settled
	 * @documentable
	 */
	public $case_closed;


	/**
	 * @var string Notes.
	 * Any notes to assist backend administrators
	 * @documentable
	 */
	public $notes;

	/**
	 * @var string API Data
	 * An array or object of the dispute object returned by the payment API gateway
	 * @documentable
	 */
	public $gateway_api_data;


	public function __construct( $update_data = array() ) {

		$update_data_params = array(
			'amount_disputed'    => 0,
			'amount_lost'        => 0,
			'status_description' => null,
			'reason_description' => null,
			'case_closed'        => null,
			'notes'              => null,
			'gateway_api_data'   => null
		);
		$update_data        = (object) array_merge( $update_data_params, $update_data );
		$this->amount_disputed    = $update_data->amount_disputed;
		$this->amount_lost        = $update_data->amount_lost;
		$this->status_description = $update_data->status_description;
		$this->reason_description = $update_data->reason_description;
		$this->case_closed        = $update_data->case_closed;
		$this->notes              = $update_data->notes;
		$this->gateway_api_data   = $update_data->gateway_api_data;
	}

    public function is_same_status($old_status)
    {
        $relevant_fields = array(
            'amount_disputed',
            'amount_lost',
            'status_description',
            'reason_description',
            'case_closed',
            'notes',
        );
        foreach ($relevant_fields as $field) {
            $newValue = is_numeric($this->$field) ? round($this->$field, 8) : trim($this->$field);
            $oldValue = is_numeric($old_status->$field) ? round($old_status->$field, 8) : trim($old_status->$field);
            $newValue = (string)$newValue;
            $oldValue = (string)$oldValue;
            if ($newValue !== $oldValue) {
                return false;
            }
        }
        return true;
    }

}
