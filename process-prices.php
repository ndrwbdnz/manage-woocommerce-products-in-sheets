<?php
//process importing and exporting prices

function ppt_process_csv_file(){

    if(isset($_POST["ppt_import"])){
            
        $filename=$_FILES["file"]["tmp_name"];

        if($_FILES["file"]["size"] > 0)
        {
            $file = fopen($filename, "r");
            while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
            {

            $sql = "INSERT into employeeinfo (emp_id,firstname,lastname,email,reg_date) 
                values ('".$getData[0]."','".$getData[1]."','".$getData[2]."','".$getData[3]."','".$getData[4]."')";
                $result = mysqli_query($con, $sql);
                if(!isset($result))
                {
                    echo "<script type=\"text/javascript\">
                            alert(\"Invalid File:Please Upload CSV File.\");
                            window.location = \"index.php\"
                        </script>";		
                }
                else {
                    echo "<script type=\"text/javascript\">
                        alert(\"CSV File has been successfully Imported.\");
                        window.location = \"index.php\"
                    </script>";
                }
            }
            fclose($file);	
        }
    }
}

function ppt_get_records(){
    if(isset($_POST["ppt_import"])){
        echo "success";
    } else {
        echo "failure";
    }

}


?>