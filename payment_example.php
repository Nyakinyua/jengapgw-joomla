<?php
/*
* @package		Example payment plugin for j2store

* @subpackage	J2Store
* @author    	John doe
* @copyright	Copyright (c) 2015 XYZ company. All rights reserved.
* @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
* --------------------------------------------------------------------------------
*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');
require_once(JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php');
class plgJ2StorePayment_example extends J2StorePaymentPlugin
{
    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    var $_element = 'payment_example';
    var $jpgw_username;
    var $jpgw_password;
    var $jpgw_grant_type = 'password';
    var $jpgw_api_key;
    var $jpgw_token;
    var $jpgw_outletcode;
    var $jpgw_merchantname;
    var $jpgw_logo;
    var $environment;
    var $configured_token_environment;
    var $configured_launch_environment;
    var $_isLog           = false;
    var $payload;


    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param    array $config An array that holds the plugin configuration
     * @since 1.5
     */
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage('', JPATH_ADMINISTRATOR);

        $this->jpgw_username = $this->params->get( 'username' );
        $this->jpgw_password = $this->params->get( 'password' );
        $this->jpgw_grant_type = $this->params->get( 'grant_type' );
        $this->jpgw_api_key = $this->params->get( 'api_key' );
        $this->jpgw_outletcode = $this->params->get('outletCode');
        $this->jpgw_merchantname = $this->params->get('merchantName');
        $this->jpgw_logo =$this->params->get( 'display_image' );
        $this->environment = $this->params->get('environment');

        if($this->params->get('debug', 0)) {
            $this->_isLog = true;
        }
    }


    /**
     * Prepares variables for the payment form.
     * Displayed when customer selects the method in Shipping and Payment step of Checkout
     *
     * @return unknown_type
     */
    function _renderForm($data)
    {
        $user = JFactory::getUser();
        $vars = new JObject();
        $vars->onselection_text = $this->params->get('onselection', '');
        //if this is a direct integration, the form layout should have the credit card form fields.
        $html = $this->_getLayout('form', $vars);

        return $html;
    }

//    function simToken() {
//        return"Hello Job";
//    }

    function simToken(){

        if ($this->environment == 'Sandbox'){
            $this->configured_token_environment = 'https://api-test.equitybankgroup.com/v1/token';
            $this->configured_launch_environment = 'https://api-test.equitybankgroup.com/v2/checkout/launch';
        } else {
            $this->configured_token_environment = 'https://api.equitybankgroup.com/v1/token';
            $this->configured_launch_environment = 'https://api.equitybankgroup.com/v2/checkout/launch';
        }

        $url = $this->configured_token_environment;

        // Collection object
        $data = [
            'merchantCode' => $this->jpgw_username,
            'password' => $this->jpgw_password,
            'grant_type' => 'password'
        ];
// Initializes a new cURL session
        $curl = curl_init($url);
// Set the CURLOPT_RETURNTRANSFER option to true
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
// Set the CURLOPT_POST option to true for POST request
        curl_setopt($curl, CURLOPT_POST, true);
// Set the request data as JSON using json_encode function
        curl_setopt($curl, CURLOPT_POSTFIELDS,  json_encode($data));
// Set custom headers for RapidAPI Auth and Content-Type header
        curl_setopt($curl, CURLOPT_HTTPHEADER,[
            "Authorization:$this->jpgw_api_key",
            'Content-Type: application/json'
        ]);
// Execute cURL request with all previous settings
        $response = curl_exec($curl);
        $token = json_decode($response,true);
// Close cURL session
        curl_close($curl);
        return $token['payment-token'];
    }

    function debug_log( $object=null, $label=null ){ $message = json_encode($object, JSON_PRETTY_PRINT); $label = "Debug" . ($label ? " ($label): " : ': '); echo "<script>console.log(\"$label\", $message);</script>"; }


    /**
     * Method to display a Place order button either to redirect the customer or process the credit card information.
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _prePayment($data)
    {
        // get component params
        $params = J2Store::config();
        $currency = J2Store::currency();

        // prepare the payment form

        $vars = new JObject();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];

        F0FTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_j2store/tables');
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
        $order->load(array('order_id' => $data['order_id']));
        $currency_values = $this->getCurrency($order);

        $vars->currency_code = $currency_values['currency_code'];
        $vars->orderpayment_amount = $currency->format($order->order_total, $currency_values['currency_code'], $currency_values['currency_value'], false);

        $vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');

//        $return_url = $rootURL . JRoute::_("index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=display");
//        $cancel_url = $rootURL . JRoute::_("index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=cancel");

        $callback_url = JURI::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=callback&tmpl=component";

        $orderinfo = $order->getOrderInformation();


        //Send POST REQ to API TEST HERE
        $this->debug_log($vars->orderpayment_amount);
        $this->debug_log($vars->order_id);
        $vars->tokens = $this->simToken();
        $vars->invoice = $order->getInvoiceNumber();

        $html = $this->_getLayout('prepayment', $vars);
        return $html;
    }

    /**
     * Processes the payment form
     * and returns HTML to be displayed to the user
     * generally with a success/failed message
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    function _postPayment($data)
    {
        // Process the payment
        $app = JFactory::getApplication();
        $paction = $app->input->getString('paction');

        $vars = new JObject();

        switch ($paction) {
            case "display":
                $vars->message = 'Thank you for the order.';
                $html = $this->_getLayout('message', $vars);
                //get the thank you message from the article (ID) provided in the plugin params
                $html .= $this->_displayArticle();
                break;
            case "callback":
                //Its a call back. You can update the order based on the response from the payment gateway
                $vars->message = 'Some message to the gateway';
                //process the response from the gateway
                $this->_processSale();
                $html = $this->_getLayout('message', $vars);
                echo $html;
                $app->close();
                break;
            case "cancel":
                //cancel is called.
                $vars->message = 'Sorry, you have cancelled the order';
                $html = $this->_getLayout('message', $vars);
                break;
            default:
                $vars->message = 'Seems an unknow request.';
                $html = $this->_getLayout('message', $vars);
                break;
        }

        return $html;
    }


    /**
     * Processes the sale payment
     *
     */
    private function _processSale()
    {

        $app = JFactory::getApplication();
        $data = $app->input->getArray($_POST);

        //get the order id sent by the gateway. This may differ based on the API of your payment gateway
        $order_id = $data['YOUR_PAYMENT_GATEWAY_FIELD_HOLDING_ORDER_ID'];

        // load the orderpayment record and set some values
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();

        if ($order->load(array('order_id' => $order_id))) {

            $order->add_history(JText::_('J2STORE_CALLBACK_RESPONSE_RECEIVED'));

            //run any checks you want here.
            //if payment successful, call : $order->payment_complete ();


            // save the data
            if (!$order->store()) {
                $errors[] = $order->getError();
            }
            //clear cart
            $order->empty_cart();
        }

        return count($errors) ? implode("\n", $errors) : '';
    }
}