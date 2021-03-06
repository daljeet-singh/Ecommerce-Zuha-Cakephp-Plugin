<?php
App::uses('AppController', 'Controller');
/**
 * OrdersAppController Controller
 *
 * @property OrdersAppController $Order
 */
class OrdersAppController extends AppController {

	public $components = array('Cookie'); // used for the guest cart

	function beforeFilter() {
		# used for guest cart
		$this->Cookie->name = 'zuhaCart';
		$this->Cookie->time = '2 Weeks';
		$this->Cookie->domain = $_SERVER['HTTP_HOST'];
		$this->Cookie->key = 'ghh8398kjfkju3889';
		parent::beforeFilter();
	}


	function _addToCookieCart($data) {
		# there are multiple items to add at once
		if (!empty($data['OrderItem'][0])) :
			if ($this->_addAllToCookieCart($data)) :
				$return = array('state' => true, 'msg' => 'Quantities updated.');
			else :
				$return = array('state' => false, 'msg' => 'Error updating quantities.');
			endif;
			return $return;
		else :
			$data['OrderItem']['id'] = 'X';
			$data = $this->OrderItem->createOrderItemFromCatalogItem($data);
			$cookieCart = $this->Cookie->read('cookieCart');
			$cookieCart[] = serialize($data);
			$this->Cookie->write('cookieCart', $cookieCart);
			return true;
		endif;
	}


	/**
	 * Used for the incoming cookie data that has many order items instead of one.
	 * Must contain all order items that should be in the cart (any not submitted will be deleted)
	 */
	function _addAllToCookieCart($data) {
		# reorder the incoming data into a typical input data array
		foreach ($data['OrderItem'] as $orderItem) :
			$orderItems[] = array('OrderItem' => $orderItem);
		endforeach;

		# merge the items into one order item with updated quantities
		$mergedItems = $this->OrderItem->mergeCartDuplicates($orderItems);
		foreach ($mergedItems as $item) :
			$cookieCart[] = serialize($this->OrderItem->createOrderItemFromCatalogItem($item));
		endforeach;

		# write the cookie data
		if($this->Cookie->write('cookieCart', $cookieCart)) :
			return true;
		else :
			return false;
		endif;
	}


	function _prepareCartData() {
		$userId = $this->Session->read('Auth.User.id');
		$cookieCart = $this->Cookie->read('cookieCart');
		$Model = $this->name == 'OrderTransactions' ? $this->OrderTransaction->OrderItem : $this->OrderItem;
		if (!empty($cookieCart) && !empty($userId)) {
          #debug('merged');
			# merge a user cart with cookieCart and delete the cookieCart
			foreach ($cookieCart as $items) {
				$orderItems[] = unserialize($items);
			}

			foreach ($orderItems as $orderItem) {
				$cookieOrderItems[] = $Model->createOrderItemFromCatalogItem($orderItem);
			}
            #debug($cookieCart);
            #debug($orderItems);
            #debug($cookieOrderItems);
			foreach ($Model->cartExtras($cookieOrderItems) as $cookieItem) {
				$Model->addToCart($cookieItem, $userId);
			}

			$this->Cookie->delete('cookieCart');

			return $Model->prepareCartData($userId);

		} else if (!empty($cookieCart) && empty($userId)) {
          #debug('cookieCart');
			# just show the cookieCart
			foreach ($cookieCart as $items) :
				$orderItems[] = unserialize($items);
			endforeach;
			foreach ($orderItems as $orderItem) :
				$cookieOrderItems[] = $Model->createOrderItemFromCatalogItem($orderItem);
			endforeach;

			return $Model->cartExtras($cookieOrderItems);

		} else if (!empty($userId)) {
          #debug('logged-in cart');
			# regular logged in cart
			return $Model->prepareCartData($userId);
		} else {
          #debug('empty cart');
			# cart is empty
			return null;
		}

		/*$data['OrderItem']['id'] = 'X';
		$orderItem[]['OrderItem'] = $data['OrderItem'];
		$this->Session->delete('GuestCart');
		$this->Session->write('GuestCart', $orderItem);
		$cartCount = $this->Session->read('OrdersCartCount');
		$this->Session->write('OrdersCartCount', ($cartCount + 1));
		$return = array('state' => true, 'msg' => 'Item added to cart');
		*/
	}
}