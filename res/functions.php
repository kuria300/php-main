<?php 

function is_master_user(){
    return isset($_SESSION["admin_type"]) && $_SESSION["admin_type"] === "master";
}

?>