<?php
require 'db.php';

function db_cfg_read($args){

    debug(__FILE__,__FUNCTION__,__LINE__, $args);
    $table = 'cfg_'.$args['table'];
    debug(__FILE__,__FUNCTION__,__LINE__, $table);

    $query = " SELECT *
    FROM $table
    ";

    $result = db_execute($query);
    debug(__FILE__,__FUNCTION__,__LINE__, $query);
    succ_return($result);
}
?>