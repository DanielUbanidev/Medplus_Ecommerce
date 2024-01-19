<?php
	
	/* Attempt MySQL server connection.*/
	
	$servername = "localhost";
	$user = "u681625981_medplus";
	$pass = "Medplus4ecommerce";
	$dbname = "u681625981_medplus";
	
	$link = mysqli_connect($servername, $user, $pass, $dbname);
	
	// Check connection
	if($link === false){
		die("ERROR: Could not connect. " . mysqli_connect_error());
	}
	
	
	$condition = $_POST['condition'];
	
	
	
	if($condition == 'updateCustomStatus'){
		// Attempt insert query execution
	
		$newStatus = $_POST['newStatus'];
		$customId = $_POST['customId'];
		
		$sql = "update custom_requests set status = '$newStatus' where id = '$customId'";
		
		if(mysqli_query($link, $sql)){
			echo "Records updated successfully.";
			} else{
			echo "ERROR: Could not able to execute $sql. " . mysqli_error($link);
		}
	}	
	
	
	
	
	
	
	
	
	if($condition == 'getStoreState'){
		// Attempt insert query execution
		
		$query = "select distinct(state) from 0_store_locations where 1";
        
        if ($result = mysqli_query($link, $query)) {
            
            $newArr = array();
            
            while ($db_field = mysqli_fetch_assoc($result)) {
                $newArr[] = $db_field['state'];
                 //echo json_encode($db_field['state']); 
			}
            echo json_encode($newArr); // get all products in json format.    
		}
	};
	
	
		
	if($condition == 'getAllCustomRequest'){
		// Attempt insert query execution
		
		$query = "SELECT id, name_of_customer, phone_no, name_of_product, quantity, status, images, created_at FROM custom_requests";
        
        if ($result = mysqli_query($link, $query)) {
            
            $newArr = array();
            
            while ($db_field = mysqli_fetch_assoc($result)) {
                $newArr[] = $db_field;
			}
            echo json_encode($newArr); // get all products in json format.    
		}
	}
	
	
	
	if($condition == 'getStoreLga'){
		// Attempt insert query execution
		
		$stateData = $_POST['state'];
		
		$query = "select distinct(local_govt) from 0_store_locations where state = '$stateData'";
        
        if ($result = mysqli_query($link, $query)) {
            
            $newArr = array();
            
            while ($db_field = mysqli_fetch_assoc($result)) {
                $newArr[] = $db_field['local_govt'];
			}
             echo json_encode($newArr); // get all products in json format.    
		}
	};
	
	
	
	
	
			
	if($condition == 'customRequest'){
		// Attempt insert query execution
	
		$name_of_customer = $_POST['name_of_customer'];
		$phone_no = $_POST['phone_no'];
		$name_of_product = $_POST['name_of_product'];
		$quantity = $_POST['quantity'];
		$images = $_POST['productImage'];
		
		$sql = "INSERT INTO custom_requests (name_of_customer, phone_no, name_of_product, quantity, status, images) VALUES ('$name_of_customer', '$phone_no', '$name_of_product', '$quantity', '$status', '$images')";
		
		if(mysqli_query($link, $sql)){
			echo "Records inserted successfully.";
			} else{
			echo "ERROR: Could not able to execute $sql. " . mysqli_error($link);
		}
	}	
	
	
	
	
	
	if($condition == 'getStore'){
		// Attempt insert query execution
		
		$lgaData = $_POST['lga'];
		
		$query = "select concat(`store`, ' ', `store_address`) as store from 0_store_locations where local_govt = '$lgaData'";
        
        if ($result = mysqli_query($link, $query)) {
            
            $newArr = array();
            
            while ($db_field = mysqli_fetch_assoc($result)) {
                $newArr[] = $db_field['store'];
			}
             echo json_encode($newArr); // get all products in json format.    
		}
	};
	
	
	
	
	// Close connection
	mysqli_close($link);
?>