<?php 

 $db_server="localhost";
 $db_user= "root";
 $db_pass= "";
 $db_name= "sms";
 $port ="3306";
 
 $connect=mysqli_connect($db_server,
                         $db_user,
                         $db_pass,
                         $db_name,
                         $port);
if(!$connect){
   die("connection failed".mysqli_connect_error());
}
?>