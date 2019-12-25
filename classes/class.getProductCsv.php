<?php
/*
 * Class to Get the all products from API and make a new array to map the fields for woocommerce import.
 */
 
class GetProductsfromCSV{
	
	public $csvPath;
	public $product = array();
	public $products = array();
	public $csvproducts = array();
	
	//Autoload function
	public function __construct($csvPath){
		$this->csvPath = $csvPath;
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
				
			if($combined_array['Parent Name'] == $lastarray['Parent Name']){
				$this->csvproducts[$lastParentProduct]['variants'][] = $combined_array;
			}
			else{
				$lastParentProduct = $currentKey;
				//set self if product has variant but single in row CSV
				if((!empty(trim($combined_array['Size 1'])) || !empty(trim($combined_array['Size 2'])))){
					$combined_array['variants'] = array('self');
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
		
		if(is_array($csvproducts) && sizeof($csvproducts) > 0){
			foreach($csvproducts as $product){
				
				
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

	//Generate Products Meta for Simple and Variable Product
	public function generateGeneralMeta($product){
		$generalMeta = array();
		$generalMeta['name'] 			= $product['Parent Name'];
		$generalMeta['sku'] 			= $product['UPC Code'];
		$generalMeta['description'] 	= $product['Parent Name'];
		$generalMeta['vendor'] 			= $product['Vendor'];
		$generalMeta['slug'] 			= sanitize_title($product['Parent Name']);
		$generalMeta['price'] 			= $product['Price'];
		$generalMeta['categories']		= array($product['Category']);
		$generalMeta['subcats']			= array($product['SubCat']);
		$generalMeta['status']			= $product['Website Status'];
		$generalMeta['weight']			= '';
		$generalMeta['images']			= '';
		return $generalMeta;
	}
	
	//Generate Simple Product
	public function generateSimpleProduct($product){
		if($product['OnHand'] < 1){ return false; }
		$simpleProduct 					= $this->generateGeneralMeta($product);
		$simpleProduct['type'] 			= 'simple';
		$simpleProduct['qty'] 			= $product['OnHand'];
		return $simpleProduct;
	}

	//Generate Variable Product
	public function generateVariableProduct($product){
		$variableProduct 				= $this->generateGeneralMeta($product);
		$variableProduct['type'] 		= 'variable';
		$variableProduct['available_attributes'] = 'Size 1';
		
		if(!empty($product['Size 2']) || trim($product['Size 2']) != ''){
			$variableProduct['available_attributes'] = 'Size 1,Size 2';
		}
		
		$attributes = array();
		$Prdoptions = explode(',',$variableProduct['available_attributes']);
		$attributesArray[$Prdoptions[0]] = trim(ltrim(trim($product['Size 1']), '/'));
		$attrSku = $product['Product Code'].sanitize_title($product[$Prdoptions[0]]);
		
		//check if variation has second option or not
		if(!empty($product['Size 2']) || trim($product['Size 2']) != ''){
			$attributesArray[$Prdoptions[1]] = trim(ltrim(trim($product['Size 2']), '/'));
			$attrSku = $attrSku.sanitize_title($product[$Prdoptions[1]]);
		}
		
		if(!empty(trim($product['Size 1'])) && !empty(trim($product['Size 2']))){
			$attributes[] = array(
				'attributes' 	=> $attributesArray,
				'name'			=> $product['Name with Attributes Included'],
				'price'			=> $product['Price'],
				'sku'			=> $attrSku,
				'manage_stock'	=> 'yes',
				'qty'			=> $product['OnHand'],
				'weight'		=> '',
				'unique_id'		=> base64_encode($attrSku)
			);
		}
		
		$hasQty = ($product['OnHand'] > 0)? 'Yes' : 'No';
		
		//check product has self variant.
		if(!in_array('self',$product['variants'])){
			
			foreach($product['variants'] as $variation){
				
				if(empty(trim($variation['Size 1'])) && empty(trim($variation['Size 2']))){
					continue;
				}
				
				$attributesArray[$Prdoptions[0]] = trim(ltrim(trim($variation['Size 1']), '/'));
				$attrSku = $product['Product Code'].sanitize_title($variation[$Prdoptions[0]]);
				
				// if(!isset($Prdoptions[1])){ error_log($product['Description']); }
				
				if(!empty($variation['Size 2']) || trim($variation['Size 2']) != ''){
					$attributesArray[$Prdoptions[1]] = trim(ltrim(trim($variation['Size 2']), '/'));
					$attrSku = $attrSku.sanitize_title($variation[$Prdoptions[1]]);
				}
				
				$attributes[] = array(
					'attributes' 	=> $attributesArray,
					'name'			=> $variation['Name with Attributes Included'],
					'price'			=> $variation['Price'],
					'sku'			=> $attrSku,
					'manage_stock'	=> 'yes',
					'qty'			=> $variation['OnHand'],
					'weight'		=> '',
					'unique_id'		=> base64_encode($attrSku)
				);
				
				if($variation['OnHand'] > 0){ $hasQty = 'Yes'; }
			}
		}
		
		if($hasQty == 'No'){ return false; }
		
		if(sizeof($attributes) > 0 ){
			$variableProduct['variations'] = $attributes;	
		}
		else{
			$variableProduct = $this->generateSimpleProduct($product);
		}
		
		return $variableProduct;
	}
	
	
	/*
	 * Get all Products function to call directly
	 */
	public function getAllProducts(){
		return $this->products;
	}

}
