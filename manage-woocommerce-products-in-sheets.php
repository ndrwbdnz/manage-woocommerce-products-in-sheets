<?php
/* 
Plugin Name: Manage Woocommerce Products in Sheets
Versiom: 0.1
Description: Load woocommerce products details into google sheets, modify them and save back in the wordpress database.
*/

if (is_Admin()){

/* global variables ------------------------------------------------------------------------------------------------------------------------------------*/

    //ppt
    $service = '';
    $values_array = array();

/* includes and hooks ----------------------------------------------------------------------------------------------------------------------------------*/
    require __DIR__ . '/vendor/autoload.php';
    add_action( 'admin_enqueue_scripts', 'ppt_javascript' );
    add_action( 'admin_enqueue_scripts', 'ppt_custom_wp_admin_style' );
    add_action( 'wp_ajax_ppt_save_prices', 'ppt_save_prices' );
    add_action( 'wp_ajax_ppt_load_prices', 'ppt_load_prices' );
    add_action( 'wp_ajax_ppt_save_sheet_id', 'ppt_save_sheet_id');
    add_action( 'admin_menu', 'ppt_register_submenu_page', 99);
    

/* helper functions ------------------------------------------------------------------------------------------------------------------------------------*/

    function nts($value){return is_null($value) ? "" : $value;}

    function getNameFromNumber($num) {  //https://stackoverflow.com/questions/3302857/algorithm-to-get-the-excel-like-column-name-of-a-number
        $numeric = ($num - 1) % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval(($num - 1) / 26);
        if ($num2 > 0) {
            return getNameFromNumber($num2) . $letter;
        } else {
            return $letter;
        }
    }

/* admin ----------------------------------------------------------------------------------------------------------------------------------------------*/
    function ppt_save_sheet_id(){
        update_option( 'ppt_spreadsheet_id', $_POST['sheet_id'] );
        wp_die();
    }
    
    function ppt_register_submenu_page(){
        add_submenu_page( 'edit.php?post_type=product', __( 'Pricing', 'woocommerce' ), __('Pricing', 'woocommerce' ), 'manage_product_terms', 'pricing', 'ppt_pricing_admin_page' );
    }

    function ppt_pricing_admin_page(){
        $iframe_link = "https://docs.google.com/spreadsheets/d/".get_option('ppt_spreadsheet_id')."/edit?usp=sharing&amp;widget=true&amp;headers=true&amp;embedded=true";
        ?>
        <div class='wrap'>
            <h1>Pricing Admin Page</h1>
            <button id="ppt_save_prices_button" class="button-primary" style="background-color: orange;">Save prices</button>
            <button id="ppt_load_prices_button" class="button-primary" style="background-color: green;">Load prices</button>
            <span id="ppt_spinner" class="spinner" style="float: none;"></span>
            <span class="align-right">
                Sheet ID: <input type="text" name="sheetID" id="ppt_new_sheet_id" value=<?php echo get_option('ppt_spreadsheet_id');?>>
                <span id="ppt_spinner_1" class="spinner"></span>
                <button id="ppt_save_sheet_id" class="button-primary align-right" style="background-color: grey;">Save Sheet ID</button>
            </span>
            <iframe id="sheetiframe" src="<?php echo $iframe_link;?>"></iframe>
        </div>

        <?php
    }

	function ppt_javascript() {
		wp_register_script('ppt_scripts', home_url() . '/wp-content/plugins/poofi-tools/script.js', array( 'jquery' ));
		wp_enqueue_script('ppt_scripts');
	}  
    
    function ppt_custom_wp_admin_style() {
        wp_register_style( 'ppt_wp_admin_css', home_url() . '/wp-content/plugins/poofi-tools/style.css', false, '1.0.0' );
        wp_enqueue_style( 'ppt_wp_admin_css' );
    }
    

/* load, edit, save prices ----------------------------------------------------------------------------------------------------------------------------------*/
    function ppt_authenticate(){
        global $service;
        // get a Google_Client object first to handle auth and api calls, etc.
        $client = new \Google_Client();
        $client->setApplicationName('Manage Woo Products');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        $client->setAuthConfig(__DIR__ . '/credentials.json');

        // With the Google_Client we can get a Google_Service_Sheets service object to interact with sheets
        $service = new \Google_Service_Sheets($client);
    }

    function ppt_get_prices_array(){
        global $values_array;
        $lang = pll_default_language();
        $aepbc = $GLOBALS["wc-aelia-prices-by-country"];
        $aecs = $GLOBALS["woocommerce-aelia-currencyswitcher"];
        $base_currency = $aepbc->base_currency();
        $regions = $aepbc::settings()->get_regions();
        $currencies = $aecs::settings()->get_enabled_currencies();
        $args = array(
            'post_type' => 'product',
            'numberposts' => -1,
            'post_status' => array('publish', 'draft')
        );
        $products = get_posts( $args );
        $r = 0;
        $c = 0;

        $values_array[$r][$c++] = "main language product ID";
        $values_array[$r][$c++] = "product ID";
        $values_array[$r][$c++] = "admin link";
        $values_array[$r][$c++] = "link to image";
        $values_array[$r][$c++] = "product image";
        $values_array[$r][$c++] = "SKU";
        $values_array[$r][$c++] = "product name";
        $values_array[$r][$c++] = "status";
        

        foreach($regions as $region){
            $values_array[$r][$c++] = $region['region_name'] . " - block";
            foreach($currencies as $currency){
                $values_array[$r][$c++] = $region['region_name'] . " - " . $currency . " - Regular";
                $values_array[$r][$c++] = $region['region_name'] . " - " . $currency . " - Sale";
            }
        }

        $r++;

        foreach($products as $product){
            $product_s = wc_get_product( $product->ID );
            $product_prices_by_country = apply_filters('wc_aelia_pbc_product_prices', $aepbc->pricing_manager->get_product_prices_by_country($product->ID), $product->ID);
            $product_unavailability_by_country = apply_filters('wc_aelia_pbc_product_availability', $aepbc->pricing_manager->get_product_unavailability_by_country($product->ID), $product->ID);
            $image_array = wp_get_attachment_image_src( get_post_thumbnail_id( $product->ID ), 'single-post-thumbnail' );
            $r1 = $r+1;
            $c = 0;

            $values_array[$r][$c++] = pll_get_post($product->ID, $lang);                                                                        //polylang main language product id
            $values_array[$r][$c++] = $product->ID;                                                                                             //product ID
            $values_array[$r][$c++] = site_url("/wp-admin/post.php?post=" . $product->ID . "&action=edit");                                     //link to edit in admin
            $values_array[$r][$c++] = nts($image_array[0]);                                                                                          //link to image
            $values_array[$r][$c++] = "=image(B$r1)";                                                                                           //show product image
            $values_array[$r][$c++] = $product_s->get_sku();                                                                                    //SKU
            $values_array[$r][$c++] = $product->post_title;                                                                                     //product name
            $values_array[$r][$c++] = $product_s->get_status();                                                                                 //status
            

            foreach($regions as $region){
                $values_array[$r][$c++] = nts($product_unavailability_by_country[$region['region_id']]);
                foreach($currencies as $currency){
                    $values_array[$r][$c++] = nts($product_prices_by_country[$region['region_id']][$currency]['regular_price']);
                    $values_array[$r][$c++] = nts($product_prices_by_country[$region['region_id']][$currency]['sale_price']);
                }
            }

            $r++;

            $args = array(
                'post_parent'=> $product->ID,
                'post_type' => 'product_variation',
                'numberposts' => -1,
                'post_status' => array('publish', 'draft')
            );
            $variations = get_posts( $args );

            if(!empty($variations)){
                foreach($variations as $variation){
                    $variation_s = wc_get_product( $variation->ID );
                    $var_attr = $variation_s->get_variation_attributes();
                    $var_attr_string = ' (';
                    foreach ( $var_attr as $attr_name => $attr_value ) {
                        $var_attr_string .= str_ireplace( 'attribute_pa_', '', $attr_name ) . ': ' . $attr_value . '; ';
                    }
                    $var_attr_string .= ')';
                    $product_prices_by_country = apply_filters('wc_aelia_pbc_product_prices', $aepbc->pricing_manager->get_product_prices_by_country($variation->ID), $variation->ID);
                    $product_unavailability_by_country = apply_filters('wc_aelia_pbc_product_availability', $aepbc->pricing_manager->get_product_unavailability_by_country($variation->ID), $variation->ID);
                    $image_array = wp_get_attachment_image_src( get_post_thumbnail_id( $variation->ID ), 'single-post-thumbnail' );
                    $r1 = $r+1;
                    $c = 0;

                    $values_array[$r][$c++] = pll_get_post($product->ID, $lang);                                                                        //polylang main language product id
                    $values_array[$r][$c++] = $variation->ID;                                                                                           //variation ID
                    $values_array[$r][$c++] = site_url("/wp-admin/post.php?post=" . $product->ID . "&action=edit");                                     //link to edit in admin
                    $values_array[$r][$c++] = nts($image_array[0]);                                                                                          //link to image
                    $values_array[$r][$c++] = "=image(B$r1)";                                                                                           //show product image
                    $values_array[$r][$c++] = $variation_s->get_sku();                                                                                  //SKU
                    $values_array[$r][$c++] = $product->post_title . $var_attr_string;                                                                  //variation name
                    $values_array[$r][$c++] = $variation_s->get_status();                                                                               //status
                    
                    foreach($regions as $region){
                        $values_array[$r][$c++] = nts($product_unavailability_by_country[$region['region_id']]);
                        foreach($currencies as $currency){
                            $values_array[$r][$c++] = nts($product_prices_by_country[$region['region_id']][$currency]['regular_price']);
                            $values_array[$r][$c++] = nts($product_prices_by_country[$region['region_id']][$currency]['sale_price']);
                        }
                    }

                    $r++;
                }
            }        
        } //end foreach
    }

    function ppt_show_prices(){
        global $service, $values_array;

        $range = "A1";
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values_array
        ]);

        $params = [
            'valueInputOption' => 'USER_ENTERED'
        ];
        
        $result = $service->spreadsheets_values->update(get_option('ppt_spreadsheet_id'), $range, $body, $params);
    }

    function ppt_load_prices(){
        ppt_authenticate();
        ppt_get_prices_array();
        ppt_show_prices();
        wp_die();
    }

    function ppt_save_prices(){
        global $service;
        ppt_authenticate();

        $aepbc = $GLOBALS["wc-aelia-prices-by-country"];
        $aecs = $GLOBALS["woocommerce-aelia-currencyswitcher"];
        $base_currency = $aepbc->base_currency();
        $regions = $aepbc::settings()->get_regions();
        $region_names = array();
        $r = 0;
        foreach($regions as $region){$region_names[$region['region_id']] = $region['region_name'];}
        $currencies = $aecs::settings()->get_enabled_currencies();
        
        $spr = $service->spreadsheets->get(get_option('ppt_spreadsheet_id'));
        $main_sheet = '';
        foreach($spr['sheets'] as $sheet){
            if ($sheet['properties']['title'] == "main"){
                $main_sheet = $sheet;
                $ncols = $main_sheet['basicFilter']['range']['endColumnIndex'];
                $nrows = $main_sheet['basicFilter']['range']['endRowIndex'];
            }
        }

        $range = "main!A1:".getNameFromNumber($ncols).$nrows;
        $rows = $service->spreadsheets_values->get(get_option('ppt_spreadsheet_id'), $range, ['majorDimension' => 'ROWS']);
        $_availability_by_country = array();
        $_prices_by_country = array();

        if (isset($rows['values'])) {
            for ($r = 1; $r < $nrows; $r++){                                         //take each row
                $product_id = '';                                                           //set holders for values that we are looking for
                $region_id = '';
                $currency_id = '';
                $price_type = '';
                $block = false;
                $price_val = '';
                
                for ($c = 0; $c < $ncols; $c++) {                                //take each column in a row
                    $names = explode(" - ", $rows[0][$c]);                                  //take the first row with column names
                    foreach($names as $name){
                        if(in_array($name, array('product ID')) ){                          //if the column contains a product ID
                            $product_id = $rows[$r][$c];
                        }
                        if(in_array($name, $region_names) ){                                //if the column contains a region name
                            $region_id = array_flip($region_names)[$name];
                        }
                        if (in_array($name, $currencies)){                                  //if the column contains a currency
                            $currency_id = $name; 
                        }
                        if (in_array($name, array('Sale', 'Regular'))){                     //if the column contains Sale or Regular price
                            $price_type = strtolower($name) . "_price";
                        }
                        if (in_array($name, array('block'))){                               //if the column contains Sale or Regular price
                            $block = true;
                        }
                    }

                    // if ( $product_id != ''){                           //if the product is in the table, then save his current values
                    //     $_availability_by_country[$product_id] = get_post_meta($product_id, '_availability_by_country');
                    //     $_prices_by_country[$product_id] = get_post_meta($product_id, '_prices_by_country');
                    // }

                    if ( $product_id != '' && $region_id != ''){                           //once we have checked a column - see if we have something to write (i.e. we already found product id and region id)
                        if ($block){
                            $_availability_by_country[$product_id][$region_id] = nts($rows[$r][$c]);         //write to table the value
                            //update_post_meta($product_id, '_availability_by_country', $_availability_by_country[$product_id]);
                            $region_id = '';
                            $block = false;                                                             //set block to false becasue we are going to the next column
                        }
                        if ($currency_id != '' && $price_type != ''){
                            $_prices_by_country[$product_id][$region_id][$currency_id][$price_type] = nts($rows[$r][$c]);        //write value to table
                            //update_post_meta($product_id, '_prices_by_country', $_prices_by_country[$product_id]);
                            $region_id = '';                                                                                //these values have to be set at each column so after writing erase them
                            $currency_id = '';
                            $price_type = '';
                        }

                    }
               
                    // _availability_by_country[955][857ac2a9bde46276e20017fac4d0d4ea]: no
                    // _prices_by_country[955][857ac2a9bde46276e20017fac4d0d4ea][EUR][regular_price]: 111
                    // _prices_by_country[955][857ac2a9bde46276e20017fac4d0d4ea][EUR][sale_price]: 110
                    // _prices_by_country[955][857ac2a9bde46276e20017fac4d0d4ea][PLN][regular_price]: 121
                    // _prices_by_country[955][857ac2a9bde46276e20017fac4d0d4ea][PLN][sale_price]: 120
                    // _prices_by_country[955][857ac2a9bde46276e20017fac4d0d4ea][USD][regular_price]: 131
                    // _prices_by_country[955][857ac2a9bde46276e20017fac4d0d4ea][USD][sale_price]: 130
                }
                
            } //and loop for rows - all data is saved in two arrays

            //now save those arrays to database
            foreach($_availability_by_country as $product_id => $product_av){
                update_post_meta($product_id, '_availability_by_country', $product_av);
            }
            foreach($_prices_by_country as $product_id => $prices_data){
                update_post_meta($product_id, '_prices_by_country', $prices_data);
            }
        }
   
        wp_die();

    }

}