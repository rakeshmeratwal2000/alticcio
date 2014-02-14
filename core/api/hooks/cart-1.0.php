<?php

function get_cart() {
	$args = func_get_args();
	$api = array_shift($args);
	$method = array_shift($args);
	$cart = new API_Cart($api);
	return call_user_func_array(array($cart, $method) ,$args);
}

function post_cart() {
	$args = func_get_args();
	$api = array_shift($args);
	$method = array_shift($args);

	$cart = new API_Cart($api);
	return call_user_func_array(array($cart, $method) ,$args);
}

function post_cart_add() {
	$args = func_get_args();
	$api = array_shift($args);

	$cart = new API_Cart($api);

	call_user_func_array(array($cart, "add_safe") ,$args);

	return cart_number($cart);
}

function cart_number($cart) {
	$cart_number = 0;
	foreach ($cart->items() as $item) {
		$cart_number += $item['qte'];
	}

	$ret = array(
		'cart_number' => $cart_number
	);

	return $ret;
}

function get_cart_remove($api, $perso) {
	$cart = new API_Cart($api);
	$cart->remove($perso);
	
	return cart_number($cart);
}

function get_cart_update($api, $perso, $qte) {
	$cart = new API_Cart($api);
	$cart->update_safe($perso, $qte);
	
	return cart_number($cart);
}

function get_cart_content() {
	$args = func_get_args();
	$api = array_shift($args);

	$cart = new API_Cart($api);

	if ($cart->is_empty()) {
		$ret = array(
			'empty' => true,
			'products' => array(),
			'total_ht' => 0,
		);
	}
	else {
		$ret = $cart->content();
		$ret['empty'] = false;
	}

	return $ret;
}

function get_cart_livraison() {
	$args = func_get_args();
	$api = array_shift($args);
	$method = array_shift($args);

	$livraison = new API_Livraison($api);
	return call_user_func_array(array($livraison, $method) ,$args);
}

function post_cart_livraison() {
	$args = func_get_args();
	$api = array_shift($args);
	$method = array_shift($args);

	$livraison = new API_Livraison($api);
	return call_user_func_array(array($livraison, $method) ,$args);
}

function post_cart_checkout() {
	return array();
}

function get_cart_safe_qte($api, $id_produits, $id_sku, $qte, $sample = false) {
	$cart = new API_Cart($api);
	list($less, $more) = $cart->safe_qte($id_produits, $id_sku, $qte, $sample);

	return array('qte' => $qte, 'less' => $less, 'more' => $more);
}
