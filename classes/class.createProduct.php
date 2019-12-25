<?php
/*
 * Class to Create the Woocommerce products from the Shopify API 
 */
class createProductsWooCSV{
	
	public $type = 'simple';
	public $action = 'created';
	public $product_data;
	public $pID; //product ID
	public $featured_image;
	public $product_gallery;
	
	
	//Autoload function
	public function __construct($productData){
		$this->product_data = $productData;
		$this->createProduct();  
	}
	
	/*
	 * Create Product into store and saved data.
	 * Create products type @Varible and @Simple
	 */
	public function createProduct(){
		
		// echo '<pre>'.print_r($this->product_data,true).'</pre>'; exit;
		
		$post = array( // Set up the basic post data to insert for our product
			'post_author'  => 1,
			'post_content' => $this->product_data['description'],
			'post_status'  => $this->product_data['status'],
			'post_title'   => $this->product_data['name'],
			'post_parent'  => '',
			'post_type'    => 'product'
		);
		
		//check if product exists by SKU
		$product_ID = wc_get_product_id_by_sku($this->product_data['sku']);
		
		//check if Product exists by Product Slug
		if($product_ID == 0 && isset($this->product_data['slug'])){
			$slug = $this->product_data['slug'];
			$product_ID = $this->get_productid_by_slug($slug);
		}
		
		//set product as draft
		if($product_ID !== 0){
			wp_update_post( array(
				'ID' => $product_ID,
				'post_status' => $this->product_data['status'],
			) );
		}
		
		// Insert the product if not Exists
		if($product_ID == 0){
			$this->action = 'created';
			$product_ID = wp_insert_post($post); 
		}
		else{
			$this->action = 'updated';
			$post['ID'] = $product_ID;
			wp_update_post($post);
		}
		
		// Insert the post returning the new post id
		$this->pID = $product_ID; 

		// If there is no post id something has gone wrong so don't proceed
		if (!$product_ID){
			return false;
		}

		// Set its SKU
		update_post_meta($product_ID, '_sku', $this->product_data['sku']); 
		
		// Set the product to visible, if not it won't show on the front end
		update_post_meta($product_ID,'_visibility','visible'); 
		
		// Set the product to visible, if not it won't show on the front end
		update_post_meta($product_ID,'vendor',$this->product_data['vendor']); 
		update_post_meta($product_ID, '_upccode', $this->product_data['sku']);
		
		$this->setProductType();
		$this->createCatsAndTags();
		$this->createSubCats();
		
		//check if the product is variable
		if($this->isVariable()){
			// Add attributes passing the new post id, attributes & variations
			$available_attributes = explode(',',$this->product_data['available_attributes']);
			$this->insert_attributes($available_attributes, $this->product_data['variations']); 
			
			// Insert variations passing the new post id & variations   
			$this->insert_variations($this->product_data['variations']); 
		}
		else{
			// Set it to a variable product type
			wp_set_object_terms($this->pID, 'simple', 'product_type'); 
			
			// Set the product to visible, if not it won't show on the front end
			update_post_meta($product_ID,'_price',$this->product_data['price']); 
			update_post_meta($product_ID,'_regular_price',$this->product_data['price']); 
			update_post_meta($product_ID,'_manage_stock','yes'); 
			update_post_meta($product_ID,'_weight',$this->product_data['weight']); 
			update_post_meta($product_ID,'_stock',$this->product_data['qty']); 
		}
		
		//import all images for the product
		$this->importImages();
	}
	
	/*
	 * Set current product type
	 */
	public function setProductType(){
		if($this->product_data['type'] == 'variable'){
			$this->type = 'variable';
			wp_set_object_terms($this->pID, 'variable', 'product_type'); // Set it to a variable product type
		}
	}
	
	/*
	 * Check if current product is variable or simple
	 */
	public function isVariable(){
		if($this->type == 'variable'){
			return true;
		}
		return false;
	}
	
	/*
	 * Create Tags and Categories for current product
	 */
	public function createCatsAndTags(){
		// Set up its categories and Tags
		wp_set_object_terms($this->pID, $this->product_data['categories'], 'product_cat');
		
		if(isset($this->product_data['tags'])){
			wp_set_object_terms($this->pID, explode(',',$this->product_data['tags']), 'product_tag');
		}
	}

	/*
	 * Create Subcategories for the product
	 */
	public function createSubCats(){
		if(isset($this->product_data['subcats']) && isset($this->product_data['categories'])){
			$subcats = $this->product_data['subcats'];
			
			//Get Parent Category ID.
			$parentCat 		= $this->product_data['categories'][0];
			$parentCatobj	= get_term_by( 'name', $parentCat, 'product_cat' );
			$parent_termID 	= (isset($parentCatobj->term_id))? $parentCatobj->term_id : 0;
			
			if($parent_termID != 0){
				foreach($subcats as $subcat){
					if(!term_exists($subcat,'product_cat')) {
						wp_insert_term(
							$subcat,
							'product_cat',
							array(
								'slug'		=> sanitize_title($subcat),
								'parent'	=> $parent_termID
							)
						);
					}

				}
			}
		}
	}
	
	/*
	 * Insert attributes for current product.
	 */
	public function insert_attributes($available_attributes, $variations)  {
		
		$post_id = $this->pID;
		
		// Go through each attribute
		foreach ($available_attributes as $attribute){
			
			$values = array(); // Set up an array to store the current attributes values.

			// Loop each variation in the file
			foreach ($variations as $variation){
				
				// Get the keys for the current variations attributes
				$attribute_keys = array_keys($variation['attributes']); 

				// Loop through each key
				foreach ($attribute_keys as $key){
					// If this attributes key is the top level attribute add the value to the $values array
					if ($key === $attribute){
						$values[] = $variation['attributes'][$key];
					}
				}
			}

			// Essentially we want to end up with something like this for each attribute:
			// $values would contain: array('small', 'medium', 'medium', 'large');

			$values = array_unique($values); // Filter out duplicate values

			// Store the values to the attribute on the new post, for example without variables:
			// wp_set_object_terms(6, 'small', 'pa_size');
			wp_set_object_terms($post_id, $values, 'pa_' . sanitize_title($attribute));
		}

		$product_attributes_data = array(); // Setup array to hold our product attributes data

		// Loop round each attribute
		foreach ($available_attributes as $attribute){
			
			$attribute = sanitize_title($attribute);
			// Set this attributes array to a key to using the prefix 'pa'
			$product_attributes_data['pa_'.$attribute] = array(

				'name'         	=> 'pa_'.$attribute,
				'value'        	=> '',
				'position'	   	=> '0',
				'is_visible'  	=> '1',
				'is_variation' 	=> '1',
				'is_taxonomy'  	=> '1'

			);
		}
		
		// Attach the above array to the new posts meta data key '_product_attributes'
		update_post_meta($post_id, '_product_attributes', $product_attributes_data); 

	}


	/*
	 * Insert Variations for current product.
	 */
	public function insert_variations($variations){
		
		$post_id = $this->pID;
		
		foreach ($variations as $index => $variation){
			
			
			$variation_post = array( // Setup the post data for the variation

				'post_title'  => 'Variation #'.$index.' of '.count($variations).' for product#'. $post_id,
				'post_name'   => 'product-'.$post_id.'-variation-'.$index,
				'post_status' => 'publish',
				'post_parent' => $post_id,
				'post_type'   => 'product_variation',
				'guid'        => home_url() . '/?product_variation=product-' . $post_id . '-variation-' . $index
			);

			$metaQuery = array(array('key' => '_unique_id','value' => $variation['unique_id'],'compare' => '='));
			$variation_post_id = $this->VariationExists($metaQuery,$post_id);
			
			if($variation_post_id == 0){
				$variation_post_id = wp_insert_post($variation_post); // Insert the variation
			}
			

			$vunique_id = '';
			
			// Loop through the variations attributes
			foreach ($variation['attributes'] as $attribute => $value){

				$attribute = sanitize_title($attribute);			
				// We need to insert the slug not the name into the variation post meta
				$attribute_term = get_term_by('name', $value, 'pa_'.$attribute); 

				//check if attribute exists else set empty value to save from error.
				$attributeSlug = (isset($attribute_term->slug))? $attribute_term->slug: '';
				
				// echo $value.' - '.$attribute.' - '.$attributeSlug.' - '.$variation_post_id.'<br>'; 
				
				// Again without variables: update_post_meta(25, 'attribute_pa_size', 'small')
				update_post_meta($variation_post_id, 'attribute_pa_'.$attribute, $attributeSlug);
				
				
				$vunique_id .= $value;
			}

			update_post_meta($variation_post_id, '_unique_id', $variation['unique_id']);
			update_post_meta($variation_post_id, '_manage_stock', $variation['manage_stock']);
			update_post_meta($variation_post_id, '_stock', $variation['qty']);
			update_post_meta($variation_post_id, '_price', $variation['price']);
			update_post_meta($variation_post_id, '_regular_price', $variation['price']);
			update_post_meta($variation_post_id, '_weight', $variation['weight']);
			update_post_meta($variation_post_id, '_upccode', $variation['sku']);
		}
	}
	
	/*
	 * Check if Variation is already exists or not.
	 * @return VariationID or 0
	 */
	public function VariationExists($metaQuery,$parentID){
		$variationEx = new WP_Query( array( 'post_type' => 'product_variation','meta_query' => $metaQuery,'post_parent' => $parentID) );
		// echo '<pre>'.print_r($variationEx,true).'</pre>';
		if($variationEx->post_count > 0){
			return $variationEx->posts[0]->ID;
		}
		return 0;
	}
	
	/*
	 * Import current product images into wordpress
	 */
	public function importImages(){

		//single file
		if(!empty($this->product_data['image'])){
			$featureImage = explode('?',$this->product_data['image']);
			$featureImage = $featureImage[0];
			$this->featured_image = $featureImage;
			
			if(!empty($this->featured_image)){
				$this->save_featured_image();
			}
		}
		

		//product Gallery
		$productGallery = $this->product_data['images'];
		$productGalleryImgs = array();
		if(isset($productGallery) && is_array($productGallery)){
			foreach($productGallery as $productGalleryImage){
				$productGImgSrc = explode('?',$productGalleryImage);
				$productGImgSrc = $productGImgSrc[0];
				array_push($productGalleryImgs,$productGImgSrc);
			}
		}
		$this->product_gallery = $productGalleryImgs;
		
		if(sizeof($this->product_gallery) > 0){
			$this->save_product_gallery();
		}
	}
	
	
	/*
	 * Save product featured image
	 */
	public function save_featured_image(){
		
		$imageID = false;
		$imgName = pathinfo($this->featured_image);
		$imageExists = $this->checkIfImageExists($imgName['filename']);
		
		if($imageExists){
			$imageID = $imageExists;
		}
		elseif($this->is_valid_url($this->featured_image)){
			$imageID = $this->save_image_with_url($this->featured_image);
		}
		
		if ($imageID)
			set_post_thumbnail( $this->pID, $imageID );	
	}

	
	/*
	 * Save product Gallery Images
	 */
	public function save_product_gallery(){	
	
		$post_id = $this->pID;
		$images = $this->product_gallery;
		$gallery = (isset($gallery))? array() : false;
		foreach ($images as $image) {
			
			$imgName = pathinfo($image);
			$imageExists = $this->checkIfImageExists($imgName['filename']);
			
			if($imageExists){
				$imageID = $imageExists;
			}
			elseif($this->is_valid_url($image)){
				$imageID = $this->save_image_with_url($image);
			}
			
			if ($imageID)
				$gallery[] = $imageID;
		}
		if ($gallery) {
			$meta_value = implode(',', $gallery);
			update_post_meta($post_id, '_product_image_gallery', $meta_value);
		}
		else{
			update_post_meta($post_id, '_product_image_gallery', '');
			// delete_post_meta($post_id, '_product_image_gallery');
		}
		
	}
	
	
	/*
	 * Download image and assigned to products
	 */
	function save_image_with_url($url) {
		
		$tmp = download_url( $url , 10 );
		$post_id = $this->pID;
		$desc = "";
		$file_array = array();
		$id = false;
	
		// Set variables for storage
		// fix file filename for query strings
		@preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
		if (!$matches) {
			return $id;			
		}
		
		$file_array['name'] = basename($matches[0]);
		$file_array['tmp_name'] = $tmp;
		$desc = $file_array['name'];
		
		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
			return $id;
		}
	
		// do the validation and storage stuff
		$id = media_handle_sideload( $file_array, $post_id, $desc );
		
		if(is_wp_error($id)){
			echo $id->get_error_message(); exit;
		}
	
		// If error storing permanently, unlink
		if ( is_wp_error($id) ) {
			@unlink($file_array['tmp_name']);
			return $id;
		}
		
		return $id;
	}

	
	/*
	 * Check if Image already added
	 */
	public function checkIfImageExists($image){
		
		global $wpdb;

		/* use  get_posts to retreive image instead of query direct!*/
		
		//set up the args
		$args = array(
            'numberposts'	=> 1,
            'orderby'		=> 'post_date',
			'order'			=> 'DESC',
            'post_type'		=> 'attachment',
            'post_mime_type'=> 'image',
            'post_status' =>'any',
		    'meta_query' => array(
		        array(
		            'key' => '_wp_attached_file',
		            'value' => sanitize_file_name($image),
		            'compare' => 'LIKE'
		        )
		    )
		);
		//get the images
        $images = get_posts($args);

        if (!empty($images)) {
        //we found a match, return it!
	        return (int)$images[0]->ID;
        } else {
        //no image found with the same name, return false
	        return false;
        }
		
	}
	
	/*
	 * Get Product ID by Slug
	 */
	public function get_productid_by_slug($slug) {
		$product = get_page_by_path( $slug, OBJECT, 'product' );
		if($product) {
			return $product->ID;
		} else {
			return 0;
		}
	} 

	/*
	 * @helper
	 * Check if given url is valid!
	 */
	public function is_valid_url($url){
		// alternative way to check for a valid url
		if  (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false; else return true;

	}

	
}