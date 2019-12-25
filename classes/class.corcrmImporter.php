<?php

class Corcrm_Import extends WP_Importer {

	var $file;
	var $log = array();
	
	
	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Import Products from Corcrm CSV').'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		$out = '';
		$out .= '<div class="narrow">';
		$out .= '<p>'.__('This importer allows you to <strong>extract products from CORCRM CSV</strong> to your woocommerce products. Lots of additional product data will be imported and added to products as <a href="http://codex.wordpress.org/Using_Custom_Fields">Custom Fields</a>.').'</p>';
		$out .= '<ul class="ul-disc">';
		$out .= '<li>'.__('<strong>If a product does not yet exist</strong>, it will be created.').'</li>';
		$out .= '<li>'.__('<strong>If a product already exists</strong>, it will be updated.').'</li>';
		$out .= '<li>'.__('<strong>Updating Methods:</strong> By Slug or By SKU.').'</li>';
		$out .= '</ul>';
		echo $out;
		$this->import_upload_form("admin.php?import=corcrm&amp;step=1");
		echo '</div>';
	}

	function import_upload_form( $action ) {
		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size = size_format( $bytes );
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) :
			?><div class="error"><p><?php _e('Before you can upload your import file, you will need to fix the following error:'); ?></p>
			<p><strong><?php echo $upload_dir['error']; ?></strong></p></div><?php
		else :
	?>
	<div class="widefat form-table">
		<div class="wrap">
			<h3>Import Corcrm Inventory.csv</h3>
			<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr(wp_nonce_url($action, 'import-upload')); ?>">
			<p>
				<label for="upload"><?php _e( 'Choose a .csv file from your computer:' ); ?> <small class="description">(<?php printf( __('Maximum size: %s' ), $size ); ?>)</small><br /><input type="file" id="upload" name="import" size="25" /></label>
				<input type="hidden" name="action" value="save" />
				<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
			</p>
			<p class="submit"><input type="submit" class="submit button-primary" value="Upload file and import" /></p>
			</form>
		</div>
	</div>
	<?php
		endif;
	}

	function import() {
		
		// $file = wp_import_handle_upload();
		$fileID = 36224;
		$file = array(
			'id'	=> $fileID,
			'file' 	=> wp_get_attachment_url($fileID)
		);
		
		if ( isset($file['error']) ) {
			echo $file['error'];
			return;
		}
		
		$result = $this->post($file);
		
		if ( is_wp_error( $result ) )
			return $result;
		
		
		do_action('import_done', 'Corcrm');
		echo '<h3>';
		printf(__('All done. <a href="%s">Have fun!</a>'), get_option('home'));
		echo '</h3>';
	}
	
	// Handle POST submission
	function post($file) {
		$output = '';
		$fileID = $file['id'];
		$file 	= $file['file'];
		if (empty($file)) {
			$this->log['error'][] = 'No file uploaded, aborting.';
			$messages = $this->print_messages();
			echo $messages[0];
			echo $messages[1];
			return;
		}
		$output = '<ol>';
		
		$time_start = microtime(true);
		
		// WordPress sets the correct timezone for date functions somewhere
		// in the bowels of wp_insert_post(). We need strtotime() to return
		// correct time before the call to wp_insert_post().
		$tz = get_option('timezone_string');
		if ($tz && function_exists('date_default_timezone_set')) {
			date_default_timezone_set($tz);
		}
		
		$skipped  = 0;
		$imported = 0;
		$comments = 0;
		
		$this->stripBOM($file);
		$getProductsfromCSV = new GetProductsfromCSV($file);
		$products = $getProductsfromCSV->getAllProducts();
		// echo '<pre>'.print_r($products,true).'</pre>'; exit;
		
		
		//execute the csv products list and create products
		if(is_array($products) && sizeof($products) > 0){
			foreach($products as $prcount => $product){
				
				//comment
				//continue from the specific key point
				// if($prcount > 1000){ continue; }
				// if($prcount < 6000 || $prcount > 7000){ continue; }
				// if($prcount == 6494){
					//update variable products only
					// if($product['type'] == 'variable'){
						
						if($output .= $this->create_product($product)){
							
							//insert log into debug.log file
							// error_log('Completed: #'.$prcount);
							
							// if($prcount == 0){print_r($output); exit;}
							$imported++;
						}
						else{
							$skipped++;
						}
						
					/* }
					else{
						continue;
					} */
				/* }
				else{
					continue;
				} */
			}
		}
		
		//remove imported File and Data
		// wp_import_cleanup( $fileID );
		
		$exec_time = microtime(true) - $time_start;
		
		if ($skipped) {
			$this->log['notice'][] = "<b>Skipped {$skipped} products (most likely due to empty title, body and excerpt).</b>";
		}
		$this->log['notice'][] = sprintf("<b>Imported {$imported} products and {$comments} comments in %.2f seconds.</b>", $exec_time);
		$output .= '</ol>';
	   
		$messages = $this->print_messages();
		$output = $messages[0].$messages[1].$output;
		echo $output;
	}
	
	
	function create_product($product) {
		$output = "<li>".__('Importing post... ');
		
		$productsWOO = new createProductsWooCSV($product);
		// echo '<pre>'.print_r($productsWOO,true).'</pre>';
		$id = $productsWOO->pID;	
		if($id == 0){
			$output .= 'Skipped !'.$product['name'];
		}
		elseif($productsWOO->action == 'updated'){
			$output .= "Updated ! <b>#{$id}</b> (View) <a href='".get_permalink($id)."'>{$product['name']}</a>";
		}
		else{		
			$output .= "Done ! <b>#{$id}</b> (View) <a href='".get_permalink($id)."'>{$product['name']}</a>";
		}
		
		$output .= '</li>';

		return $output;
	}
 
	
	// delete BOM from UTF-8 file
	function stripBOM($fname) {
		$res = fopen($fname, 'rb');
		if (false !== $res) {
			$bytes = fread($res, 3);
			if ($bytes == pack('CCC', 0xef, 0xbb, 0xbf)) {
				$this->log['notice'][] = 'Getting rid of byte order mark...';
				fclose($res);

				$contents = file_get_contents($fname);
				if (false === $contents) {
					trigger_error('Failed to get file contents.', E_USER_WARNING);
				}
				$contents = substr($contents, 3);
				$success = file_put_contents($fname, $contents);
				if (false === $success) {
					trigger_error('Failed to put file contents.', E_USER_WARNING);
				}
			} else {
				fclose($res);
			}
		} else {
			$this->log['error'][] = 'Failed to open file, aborting.';
		}
	}
	
	function dispatch() {
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		$this->header();

		switch ($step) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import-upload');
				$result = $this->import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}

		$this->footer();
	}
	
	function print_messages() {
		$errors = $notices = '';
		if (!empty($this->log)) {
			if (!empty($this->log['error'])) {
		
				$errors =  '<div class="error">';
			
				foreach ($this->log['error'] as $error) {
				   $errors .= '<p>'.$error.'</p>';
				}
				$errors .= '</div>';
			}
					
			if (!empty($this->log['notice'])) {
		
			$notices = '<div class="updated fade">';
				foreach ($this->log['notice'] as $notice) {
					$notices .= '<p>'.$notice.'</p>';
				}
			$notices .= '</div>';
		
			}
		}
		return(array($errors,$notices));
		$this->log = array();
	}
	
}