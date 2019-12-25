<?php
/*
 * Class to Get the all products from CSV file from updated fresh inventory
 * Make a new array to map the fields for woocommerce import.
 */
 
class GetFinalProductsCSV{
	
	public $csvPath;
	public $product = array();
	public $products = array();
	public $csvproducts = array();
	public $variationTypes;
	
	//Autoload function
	public function __construct($csvPath){
		$this->csvPath = $csvPath;
		$this->variationTypes = array('Fabric Color','Design','Embroidery Color','Style','Size');
		$this->getProducts();
		$this->generateProductsWoo();
	}
	
	//Get all Products
	private function getProducts(){
		
		unset($this->product);
		
		$csvFile = fopen($this->csvPath,"r");
		$header = null;
		$lastarray = null;
		
		$currentKey = 0;
		$lastParentProduct = 0;

		while(($row = fgetcsv($csvFile)) !== false){
			
			if($header === null){
				$header = $row;
				continue;
			}
			
			$combined_array = array_combine($header, $row);
				
			if(($combined_array['Website Description'] == $lastarray['Website Description']) && !empty($lastarray['Website Description'])){
				//remove self if have more variations
				if(isset($this->csvproducts[$lastParentProduct]['variants']) && 
					in_array('self',$this->csvproducts[$lastParentProduct]['variants'])
				){
					unset($this->csvproducts[$lastParentProduct]['variants']);
				}
				$this->csvproducts[$lastParentProduct]['variants'][] = $combined_array;
			}
			else{
				$lastParentProduct = $currentKey;
				//set self if product has variant but single in row CSV
				foreach($this->variationTypes as $vtype){
					if(!empty(trim($combined_array[$vtype])) && trim($combined_array[$vtype]) != '-'){	
						$combined_array['variants'] = array('self');
					}
				}
				$this->csvproducts[] = $combined_array;
				$currentKey++;
			}
			unset($lastarray);
			$lastarray = $combined_array;	
			unset($row);
		
		}
		fclose($csvFile);
		
	}

	
	//generating the array of products for woocommerce
	public function generateProductsWoo(){
		
		$csvproducts = $this->csvproducts;
		// echo '<pre>'.print_r($csvproducts,true).'</pre>'; die;
		
		if(is_array($csvproducts) && sizeof($csvproducts) > 0){
			foreach($csvproducts as $product){

				// $corcrmID = trim($product['CORCRM ID']);
				if(!empty($product['Website Description'])){
					if(isset($product['variants'])){
						$genVariableProduct = $this->generateVariableProduct($product);
						if(is_array($genVariableProduct)){
							$this->products[] = $genVariableProduct;
						}
					}
					else{
						$genSimpleProduct = $this->generateSimpleProduct($product);
						if(is_array($genSimpleProduct)){
							$this->products[] = $genSimpleProduct;
						}
					}
				}
				
			}
		}
		
	}

	//Generate Products Meta for Simple and Variable Product
	public function generateGeneralMeta($product){
		$parent_title = trim($product['Website Description']);
		preg_match('!\d+\.*\d*!', $product['Whole Price'], $whole_price);
		$generalMeta  = array();
		$generalMeta['name'] 			= $parent_title;
		$generalMeta['sku'] 			= $product['Product SKU'];
		$generalMeta['upc'] 			= $product['UPC / GTIN'];
		$generalMeta['description'] 	= $product['Website Detail Description'];
		$generalMeta['slug'] 			= sanitize_title(trim($product['Website Description']));
		$generalMeta['price'] 			= (!empty($product['Retail Price']))? $product['Retail Price'] : trim(str_replace('$','',$product['Whole Price']));
		$generalMeta['whole_price'] 	= current($whole_price);
		$generalMeta['categories']		= array($product['Product Category']);
		$generalMeta['subcat']			= $product['Sub-Category 1'];
		$generalMeta['subcat2']			= $product['Sub-Category 2'];
		$generalMeta['status']			= (empty($product['Not on Web']))? 'publish' : 'draft';
		$generalMeta['weight']			= $product['Weight'];
		$generalMeta['image']			= $this->mapImagePath($product);
		return $generalMeta;
	}
	
	//map image path according to the folder
	public function mapImagePath($product){
		$url  = get_site_url().'/pics/';
		$url .= rawurlencode($product['Product Category']).'/'.rawurlencode($product['Sub-Category 1']).'/'.$product['Image main'].'.jpg';
		return $url;
	}
	
	//Generate Simple Product
	public function generateSimpleProduct($product){
		// if($product['Stock'] < 1){ return false; }
		$simpleProduct 					= $this->generateGeneralMeta($product);
		$simpleProduct['type'] 			= 'simple';
		$simpleProduct['qty'] 			= $product['Stock'];
		$simpleProduct['categories'] 	= $this->holidayAndSaleCategory($product);
		return $simpleProduct;
	}

	//Generate Variable Product
	public function generateVariableProduct($product){
		$variableProduct 				= $this->generateGeneralMeta($product);
		$variableProduct['type'] 		= 'variable';
		
		$attributes = array();
		$avail_attributes = array();
		foreach($this->variationTypes as $vtype){
			if(!empty(trim($product[$vtype])) && trim($product[$vtype]) != '-'){
				$attributesArray[$vtype] = trim($product[$vtype]);
				array_push($avail_attributes,$vtype);
			}
			
			$variableProduct['categories'] = $this->holidayAndSaleCategory($product);
		}
		
		//generate attributes
		if(sizeof($attributesArray) > 0){
			$attributes[] = $this->makeAttributesarray($attributesArray,$product);
		}
		
		$hasQty = ($product['Stock'] > 0)? 'Yes' : 'No';
		
		//check product has self variant.
		if(!in_array('self',$product['variants'])){
			
			foreach($product['variants'] as $variation){
				
				foreach($this->variationTypes as $vtype){
					if(!empty(trim($variation[$vtype])) && trim($variation[$vtype]) != '-'){
						$vattributesArray[$vtype] = trim($variation[$vtype]);
						array_push($avail_attributes,$vtype);
					}
				}
				
				$attributes[] = $this->makeAttributesarray($vattributesArray,$variation);
				
				if($variation['Stock'] > 0){ $hasQty = 'Yes'; }
				
				$variableProduct['categories'] = $this->holidayAndSaleCategory($product,$variation);
			}
		}
		
		// if($hasQty == 'No'){ return false; }
		
		$variableProduct['available_attributes'] = implode(',',array_unique($avail_attributes));
		
		if(sizeof($attributes) > 0 ){
			$variableProduct['variations'] = $attributes;	
		}
		else{
			$variableProduct = $this->generateSimpleProduct($product);
		}
		
		return $variableProduct;
	}
	
	//generate attribute array for product or variation
	// $type it is variation or product.
	public function makeAttributesarray($attrArray,$type){
		preg_match('!\d+\.*\d*!', $type['Whole Price'], $whole_price);
		return array(
			'attributes' 	=> $attrArray,
			'name'			=> $type['Description'],
			'price'			=> $type['Retail Price'],
			'whole_price'	=> current($whole_price),
			'whole_min'		=> $type['Whole Sale Min '],
			'sku'			=> $type['Product SKU'],
			'upc'			=> $type['UPC / GTIN'],
			'manage_stock'	=> 'yes',
			'qty'			=> $type['Stock'],
			'weight'		=> '',
			'image'			=> $this->mapImagePath($type),
			'unique_id'		=> base64_encode($type['Product SKU'])
		);
	}
	
	//create Holiday or Sale Category Accordingly
	public function holidayAndSaleCategory($product,$variation = array()){
		
		$product['categories'] = array($product['Product Category']);
		
		//check if holiday exists
		if(!empty($product['Holiday']) || !empty($variation['Holiday'])){
			$product['categories'] = array_unique(array_merge($product['categories'],array('holiday')));
		}
		
		//check if price is .99 at last
		if($this->is99($product['Retail Price']) || (isset($variation['Retail Price']) && $this->is99($variation['Retail Price']))){
			$product['categories'] = array_unique(array_merge($product['categories'],array('sale')));
		}
		
		return $product['categories'];
	}
	

	//check price has .99 at end
	function is99($value){
		$value = trim($value);
		$leftover = round($value)-$value;
		return 0.01 == round($leftover, 2);
	}
	
	
	/*
	 * Get all Products function to call directly
	 */
	public function getAllProducts(){
		return $this->products;
	}

}
