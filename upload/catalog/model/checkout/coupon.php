<?php
class ModelCheckoutCoupon extends Model {
	public function getCouponCompanies() {
		/* This function gets the coupon's names, it should be the Coupon Company names or Campaign names,
		 * whatever the client want to be shown in the cart.
		 */
		$couponcomp_query = $this->db->query("SELECT DISTINCT name, codename, codesecurityname FROM " . DB_PREFIX . "coupon WHERE code_regexp = 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'");
		
		if($couponcomp_query->num_rows){
			return $couponcomp_query->rows;
		}else{
			return null;
		}
		
	}

	public function getCoupon($code, $couponCompany = NULL, $codesecurity = NULL) {
		/* RegExp Coupons
		 * 
		 * Now this function must differentiate between fixed coupons and regexp ones.
		 * For example: 
		 * 
		 * Fixed coupon is when I set coupon's value to 9999 and then I give the code 9999 to the user.
		 * 
		 * Regexp coupon is when I have a campaign from a coupon company and the coupons on this campaign have
		 * codes like two letters and 9 digits. This is a regexp like this: /\w{2}\d{9}/
		 * 
		 */
		$status = true;
		if($couponCompany==NULL || $couponCompany==''){
			/* fixed code
			 * The function remains as the original.
			 */
			$coupon_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coupon WHERE code = '" . $this->db->escape($code) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1'");
				
			if ($coupon_query->num_rows) {
				if ($coupon_query->row['total'] >= $this->cart->getSubTotal()) {
					$status = false;
				}
			
				$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");
	
				if ($coupon_query->row['uses_total'] > 0 && ($coupon_history_query->row['total'] >= $coupon_query->row['uses_total'])) {
					$status = false;
				}
				
				if ($coupon_query->row['logged'] && !$this->customer->getId()) {
					$status = false;
				}
				
				if ($this->customer->getId()) {
					$coupon_history_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch WHERE ch.coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "' AND ch.customer_id = '" . (int)$this->customer->getId() . "'");
					
					if ($coupon_query->row['uses_customer'] > 0 && ($coupon_history_query->row['total'] >= $coupon_query->row['uses_customer'])) {
						$status = false;
					}
				}
					
				$coupon_product_data = array();
					
				$coupon_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coupon_product WHERE coupon_id = '" . (int)$coupon_query->row['coupon_id'] . "'");
	
				foreach ($coupon_product_query->rows as $result) {
					$coupon_product_data[] = $result['product_id'];
				}
					
				if ($coupon_product_data) {
					$coupon_product = false;
						
					foreach ($this->cart->getProducts() as $product) {
						if (in_array($product['product_id'], $coupon_product_data)) {
							$coupon_product = true;
								
							break;
						}
					}
						
					if (!$coupon_product) {
						$status = false;
					}
				}
			} else {
				$status = false;
			}
			
			if ($status) {
				return array(
					'coupon_id'     => $coupon_query->row['coupon_id'],
					'code'          => $coupon_query->row['code'],
					'name'          => $coupon_query->row['name'],
					'type'          => $coupon_query->row['type'],
					'discount'      => $coupon_query->row['discount'],
					'shipping'      => $coupon_query->row['shipping'],
					'total'         => $coupon_query->row['total'],
					'product'       => $coupon_product_data,
					'date_start'    => $coupon_query->row['date_start'],
					'date_end'      => $coupon_query->row['date_end'],
					'uses_total'    => $coupon_query->row['uses_total'],
					'uses_customer' => $coupon_query->row['uses_customer'],
					'status'        => $coupon_query->row['status'],
					'date_added'    => $coupon_query->row['date_added']
				);
			}
		}else{ //if($couponCompany==null || $couponCompany==''){
			/* RegExp Coupons
			 * 
			 * This function is for RegExp Coupons. I must search for a coupon company name (aka coupon's name in the DB).
			 * So it is possible to have more than one result (two or more campaigns for the same company).
			 * Then I must look for the campaign in which my code fits, then work with it.
			 *
			 * Only search in records with code_regexp = '1'
			 *  
			 * Since the RegExp coupons can be used just once I will look for the coupon code in
			 * the history table, so if it was used in a non-cancelled order it can't be used again.
			 * 
			 */
			$coupon_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coupon WHERE name = '" . $this->db->escape($couponCompany) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1' AND code_regexp = '1'");

			if ($coupon_query->num_rows) {
				// Ok I have records
				$myrecord = NULL;
				foreach($coupon_query->rows as $coupon_query_row){
					if(preg_match($coupon_query_row['code'], $code)){
						//check security code
						if($coupon_query_row['codesecurity']!=NULL && $coupon_query_row['codesecurity']!=''){
							if(preg_match($coupon_query_row['codesecurity'], $codesecurity)){
								$myrecord = $coupon_query_row;
							}
						}else{
							$myrecord = $coupon_query_row;
						}
						break;							
					}
				}
				if($myrecord==NULL){
					$status = false;
				}
				
				if ($myrecord['total'] >= $this->cart->getSubTotal()) {
					$status = false;
				}
			
				/* RegExp Coupon
				 * 
				 * Search in history for used coupon. Remember the code can be used just once.
				 * I look for the given code with the following status conditions:
				 *    The code is known as already-used only if the order is not canceled.
				 * 
				 */
				$coupon_history_query_statemen = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch ";
				$coupon_history_query_statemen .= "INNER JOIN oc_order AS o ON (o.order_id = ch.order_id) ";
				//The coupon ID that matched with its RegExp
				$coupon_history_query_statemen .= "WHERE ch.coupon_id = '" . $myrecord['coupon_id'] . "' ";
				//To be used status should not be one of the followings: canceled, cenceled reversal, failed, denied (edit this line if you need)
				$coupon_history_query_statemen .= "AND o.order_status_id <> 7 AND o.order_status_id <> 8 AND o.order_status_id <> 9 AND o.order_status_id <> 10 ";
				//The given code
				$coupon_history_query_statemen .= "AND ch.code = '".$code."' ";

				$coupon_history_query = $this->db->query($coupon_history_query_statemen);

				if ($coupon_history_query->row['total'] > 0) {
					$status = false;
				}
				
				if ($coupon_query->row['logged'] && !$this->customer->getId()) {
					$status = false;
				}
				
				$coupon_product_data = array();
					
				$coupon_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coupon_product WHERE coupon_id = '" . (int)$myrecord['coupon_id'] . "'");
	
				foreach ($coupon_product_query->rows as $result) {
					$coupon_product_data[] = $result['product_id'];
				}
					
				if ($coupon_product_data) {
					$coupon_product = false;
						
					foreach ($this->cart->getProducts() as $product) {
						if (in_array($product['product_id'], $coupon_product_data)) {
							$coupon_product = true;
								
							break;
						}
					}
						
					if (!$coupon_product) {
						$status = false;
					}
				}
			} else {
				$status = false;
			}
			
			if ($status) {
				return array(
					'coupon_id'     => $myrecord['coupon_id'],
					'code'          => $code,
					'name'          => $myrecord['name'],
					'type'          => $myrecord['type'],
					'discount'      => $myrecord['discount'],
					'shipping'      => $myrecord['shipping'],
					'total'         => $myrecord['total'],
					'product'       => $coupon_product_data,
					'date_start'    => $myrecord['date_start'],
					'date_end'      => $myrecord['date_end'],
					'uses_total'    => $myrecord['uses_total'],
					'uses_customer' => $myrecord['uses_customer'],
					'status'        => $myrecord['status'],
					'date_added'    => $myrecord['date_added']
				);
			}

		}
	}
	
	public function redeem($coupon_id, $order_id, $customer_id, $amount, $code = NULL, $codesecurity = NULL) {
		if($code==NULL){
			$code = "";
		}else{
			$code = ", code = '".$code."' ";
			if($codesecurity!=NULL){
				$code .= ", codesecurity ='".$codesecurity."' ";
			}
		}
		$this->db->query("INSERT INTO `" . DB_PREFIX . "coupon_history` SET coupon_id = '" . (int)$coupon_id . "', order_id = '" . (int)$order_id . "', customer_id = '" . (int)$customer_id . "', amount = '" . (float)$amount . "', date_added = NOW()".$code);
	}
}
?>