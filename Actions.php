<?php 
session_start();
require_once('DBConnection.php');

Class Actions extends DBConnection{
    function __construct(){
        parent::__construct();
    }
    function __destruct(){
        parent::__destruct();
    }
    function login(){
        extract($_POST);
        $resp = [];
        if (!$this->db || !$this->is_connected()) {
            $resp['status'] = "failed";
            $err_msg = $this->get_error() ?: "Cannot connect to MySQL server";
            $resp['msg'] = "Database is not connected ({$err_msg}). Please open db_setup.php to configure database.";
            return json_encode($resp);
        }
        $username = $this->db->real_escape_string($username ?? '');
        $password = $password ?? '';
        $sql = "SELECT * FROM user_list where username = '{$username}' and `password` = '".md5($password)."' ";
        $qry = @$this->db->query($sql);
        if(!$qry || !($qry instanceof mysqli_result)){
            $resp['status'] = "failed";
            $db_err = $this->db->error;
            if(strpos($db_err, "doesn't exist") !== false || strpos($db_err, "user_list") !== false){
                $resp['msg'] = "Tables not imported yet! Please open db_setup.php and click '1-Click Import DB'.";
            } else {
                $resp['msg'] = "Database query error: {$db_err}";
            }
        }else if($qry->num_rows == 0){
            $resp['status'] = "failed";
            $resp['msg'] = "Invalid username or password. Default is admin / admin123.";
        }else{
            $row = $qry->fetch_array();
            $resp['status'] = "success";
            $resp['msg'] = "Login successfully.";
            foreach($row as $k => $v){
                if(!is_numeric($k))
                $_SESSION[$k] = $v;
            }
        }
        return json_encode($resp);
    }
    function logout(){
        session_destroy();
        header("location: login.php");
        exit;
    }
    function customer_logout(){
        session_destroy();
        header("location: login.php");
        exit;
    }
    function save_user(){
        extract($_POST);
        $data = "";
        $cols = [];
        $values = [];
        foreach($_POST as $k => $v){
            if(!in_array($k,array('id'))){
                $v = $this->db ? $this->db->real_escape_string($v) : addslashes(trim($v));
                if(!empty($id)){
                    if(!empty($data)) $data .= ",";
                    $data .= " `{$k}` = '{$v}' ";
                }else{
                    $cols[] = $k;
                    $values[] = "'{$v}'";
                }
            }
        }
        if(empty($id)){
            $cols[] = 'password';
            $values[] = "'".md5($username)."'";
        }
        if(isset($cols) && isset($values)){
            $data = "(".implode(',',$cols).") VALUES (".implode(',',$values).")";
        }
        
        $chk_q = @$this->db->query("SELECT count(user_id) as `count` FROM user_list where `username` = '{$username}' ".($id > 0 ? " and user_id != '{$id}' " : ""));
        $check = ($chk_q && $chk_q instanceof mysqli_result) ? ($chk_q->fetch_array()['count'] ?? 0) : 0;
        if($check > 0){
            $resp['status'] = 'failed';
            $resp['msg'] = "Username already exists.";
        }else{
            if(empty($id)){
                $sql = "INSERT INTO `user_list` {$data}";
            }else{
                $sql = "UPDATE `user_list` set {$data} where user_id = '{$id}'";
            }
            @$save = $this->db->query($sql);
            if($save){
                $resp['status'] = 'success';
                if(empty($id))
                $resp['msg'] = 'New User successfully saved.';
                else
                $resp['msg'] = 'User Details successfully updated.';
            }else{
                $resp['status'] = 'failed';
                $resp['msg'] = 'Saving User Details Failed. Error: '.($this->db ? $this->db->error : 'No DB');
                $resp['sql'] =$sql;
            }
        }
        return json_encode($resp);
    }
    function delete_user(){
        extract($_POST);
        $id = intval($id);
        @$delete = $this->db->query("DELETE FROM `user_list` where user_id = '{$id}'");
        if($delete){
            $resp['status']='success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'User successfully deleted.';
        }else{
            $resp['status']='failed';
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
    function update_credentials(){
        extract($_POST);
        $data = "";
        foreach($_POST as $k => $v){
            if(!in_array($k,array('id','old_password')) && !empty($v)){
                $v = $this->db ? $this->db->real_escape_string($v) : addslashes(trim($v));
                if(!empty($data)) $data .= ",";
                if($k == 'password') $v = md5($v);
                $data .= " `{$k}` = '{$v}' ";
            }
        }
        if(!empty($password) && md5($old_password) != $_SESSION['password']){
            $resp['status'] = 'failed';
            $resp['msg'] = "Old password is incorrect.";
        }else{
            $sql = "UPDATE `user_list` set {$data} where user_id = '{$_SESSION['user_id']}'";
            @$save = $this->db->query($sql);
            if($save){
                $resp['status'] = 'success';
                $_SESSION['flashdata']['type'] = 'success';
                $_SESSION['flashdata']['msg'] = 'Credential successfully updated.';
                foreach($_POST as $k => $v){
                    if(!in_array($k,array('id','old_password')) && !empty($v)){
                        if(!empty($data)) $data .= ",";
                        if($k == 'password') $v = md5($v);
                        $_SESSION[$k] = $v;
                    }
                }
            }else{
                $resp['status'] = 'failed';
                $resp['msg'] = 'Updating Credentials Failed. Error: '.($this->db ? $this->db->error : 'No DB');
                $resp['sql'] =$sql;
            }
        }
        return json_encode($resp);
    }
    function save_category(){
        extract($_POST);
        $data = "";
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if(!in_array($k,array('id'))){
                $v = $this->db ? $this->db->real_escape_string(trim($v)) : addslashes(trim($v));
                if(empty($id)){
                    $cols[] = "`{$k}`";
                    $vals[] = "'{$v}'";
                }else{
                    if(!empty($data)) $data .= ", ";
                    $data .= " `{$k}` = '{$v}' ";
                }
            }
        }
        $cols_join = implode(",",$cols);
        $vals_join = implode(",",$vals);
        if(empty($id)){
            $sql = "INSERT INTO `category_list` ({$cols_join}) VALUES ($vals_join)";
        }else{
            $sql = "UPDATE `category_list` set {$data} where category_id = '{$id}'";
        }
        $chk_q = @$this->db->query("SELECT COUNT(category_id) as count from `category_list` where `name` = '{$name}' ".($id > 0 ? " and category_id != '{$id}'" : ""));
        $check = ($chk_q && $chk_q instanceof mysqli_result) ? ($chk_q->fetch_array()['count'] ?? 0) : 0;
        if($check > 0){
            $resp['status'] ='failed';
            $resp['msg'] = 'Category already exists.';
        }else{
            @$save = $this->db->query($sql);
            if($save){
                $resp['status']="success";
                if(empty($id))
                    $resp['msg'] = "Category successfully saved.";
                else
                    $resp['msg'] = "Category successfully updated.";
            }else{
                $resp['status']="failed";
                if(empty($id))
                    $resp['msg'] = "Saving New Category Failed.";
                else
                    $resp['msg'] = "Updating Category Failed.";
                $resp['error']=$this->db ? $this->db->error : 'No DB';
            }
        }
        return json_encode($resp);
    }
    function delete_category(){
        extract($_POST);
        $id = intval($id);
        @$update = $this->db->query("UPDATE `category_list` set `delete_flag` = 1 where category_id = '{$id}'");
        if($update){
            $resp['status']='success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Category successfully deleted.';
        }else{
            $resp['status']='failed';
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
    function save_product(){
        extract($_POST);
        $data = "";
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if(!in_array($k,array('id'))){
                $v = $this->db ? $this->db->real_escape_string(trim($v)) : addslashes(trim($v));
                if(empty($id)){
                    $cols[] = "`{$k}`";
                    $vals[] = "'{$v}'";
                }else{
                    if(!empty($data)) $data .= ", ";
                    $data .= " `{$k}` = '{$v}' ";
                }
            }
        }
        $cols_join = implode(",",$cols);
        $vals_join = implode(",",$vals);
        if(empty($id)){
            $sql = "INSERT INTO `product_list` ({$cols_join}) VALUES ($vals_join)";
        }else{
            $sql = "UPDATE `product_list` set {$data} where product_id = '{$id}'";
        }
        $chk1 = @$this->db->query("SELECT COUNT(product_id) as count from `product_list` where `product_code` = '{$product_code}' and delete_flag = 0 ".($id > 0 ? " and product_id != '{$id}'" : ""));
        $check = ($chk1 && $chk1 instanceof mysqli_result) ? ($chk1->fetch_array()['count'] ?? 0) : 0;

        $chk2 = @$this->db->query("SELECT COUNT(product_id) as count from `product_list` where `name` = '{$name}' and delete_flag = 0 ".($id > 0 ? " and product_id != '{$id}'" : ""));
        $check2 = ($chk2 && $chk2 instanceof mysqli_result) ? ($chk2->fetch_array()['count'] ?? 0) : 0;

        if($check > 0){
            $resp['status'] ='failed';
            $resp['msg'] = 'Product Code already exists.';
        }elseif($check2 > 0){
            $resp['status'] ='failed';
            $resp['msg'] = 'Product Name already exists.';
        }else{
            @$save = $this->db->query($sql);
            if($save){
                $resp['status']="success";
                if(empty($id))
                    $resp['msg'] = "Product successfully saved.";
                else
                    $resp['msg'] = "Product successfully updated.";
            }else{
                $resp['status']="failed";
                if(empty($id))
                    $resp['msg'] = "Saving New Product Failed.";
                else
                    $resp['msg'] = "Updating Product Failed.";
                $resp['error']=$this->db ? $this->db->error : 'No DB';
            }
        }
        return json_encode($resp);
    }
    function delete_product(){
        extract($_POST);
        $id = intval($id);
        @$update = $this->db->query("UPDATE `product_list` set delete_flag = 1 where product_id = '{$id}'");
        if($update){
            $resp['status']='success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Product successfully deleted.';
        }else{
            $resp['status']='failed';
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
    function save_stock(){
        extract($_POST);
        $data = "";
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if(!in_array($k,array('id'))){
                $v = $this->db ? $this->db->real_escape_string(trim($v)) : addslashes(trim($v));
                if(empty($id)){
                    $cols[] = "`{$k}`";
                    $vals[] = "'{$v}'";
                }else{
                    if(!empty($data)) $data .= ", ";
                    $data .= " `{$k}` = '{$v}' ";
                }
            }
        }
        $cols_join = implode(",",$cols);
        $vals_join = implode(",",$vals);
        if(empty($id)){
            $sql = "INSERT INTO `stock_list` ({$cols_join}) VALUES ($vals_join)";
        }else{
            $sql = "UPDATE `stock_list` set {$data} where stock_id = '{$id}'";
        }
        
        @$save = $this->db->query($sql);
        if($save){
            $resp['status']="success";
            if(empty($id))
                $resp['msg'] = "Stock successfully saved.";
            else
                $resp['msg'] = "Stock successfully updated.";
        }else{
            $resp['status']="failed";
            if(empty($id))
                $resp['msg'] = "Saving New Stock Failed.";
            else
                $resp['msg'] = "Updating Stock Failed.";
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
    function delete_stock(){
        extract($_POST);
        $id = intval($id);
        @$delete = $this->db->query("DELETE FROM `stock_list` where stock_id = '{$id}'");
        if($delete){
            $resp['status']='success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Stock successfully deleted.';
        }else{
            $resp['status']='failed';
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
    function save_transaction(){
        extract($_POST);
        $data = "";
        $receipt_no = time();
        $i = 0;
        while(true){
            $i++;
            $chk_q = @$this->db->query("SELECT count(transaction_id) `count` FROM `transaction_list` where receipt_no = '{$receipt_no}' ");
            $chk = ($chk_q && $chk_q instanceof mysqli_result) ? ($chk_q->fetch_array()['count'] ?? 0) : 0;
            if($chk > 0){
                $receipt_no = time().$i;
            }else{
                break;
            }
        }
        $_POST['receipt_no'] = $receipt_no;
        $_POST['user_id'] = $_SESSION['user_id'] ?? 1;
        $cols = [];
        $vals = [];
        foreach($_POST as $k => $v){
            if(!in_array($k,array('id')) && !is_array($_POST[$k])){
                $v = $this->db ? $this->db->real_escape_string(trim($v)) : addslashes(trim($v));
                if(empty($id)){
                    $cols[] = "`{$k}`";
                    $vals[] = "'{$v}'";
                }else{
                    if(!empty($data)) $data .= ", ";
                    $data .= " `{$k}` = '{$v}' ";
                }
            }
        }
        $cols_join = implode(",",$cols);
        $vals_join = implode(",",$vals);
        if(empty($id)){
            $sql = "INSERT INTO `transaction_list` ({$cols_join}) VALUES ($vals_join)";
        }else{
            $sql = "UPDATE `transaction_list` set {$data} where stock_id = '{$id}'";
        }
        
        @$save = $this->db->query($sql);
        if($save){
            $resp['status']="success";
            $_SESSION['flashdata']['type']="success";
            if(empty($id))
                $_SESSION['flashdata']['msg'] = "Transaction successfully saved.";
            else
                $_SESSION['flashdata']['msg'] = "Transaction successfully updated.";
            $last_id = $this->db->insert_id;
            $tid = empty($id) ? $last_id : $id;
            $data ="";
            if(isset($product_id) && is_array($product_id)){
                foreach($product_id as $k => $v){
                    $v = intval($v);
                    $q = floatval($quantity[$k] ?? 0);
                    $p = floatval($price[$k] ?? 0);
                    if(!empty($data)) $data .=",";
                    $data .= "('{$tid}','{$v}','{$q}','{$p}')";
                }
            }
            if(!empty($data)){
                $this->db->query("DELETE FROM transaction_items where transaction_id = '{$tid}'");
                $sql = "INSERT INTO transaction_items (`transaction_id`,`product_id`,`quantity`,`price`) VALUES {$data}";
                $this->db->query($sql);
            }
            $resp['transaction_id'] = $tid;
        }else{
            $resp['status']="failed";
            if(empty($id))
                $resp['msg'] = "Saving New Transaction Failed.";
            else
                $resp['msg'] = "Updating Transaction Failed.";
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
    function delete_transaction(){
        extract($_POST);
        $id = intval($id);
        @$delete = $this->db->query("DELETE FROM `transaction_list` where transaction_id = '{$id}'");
        if($delete){
            $resp['status']='success';
            $_SESSION['flashdata']['type'] = 'success';
            $_SESSION['flashdata']['msg'] = 'Transaction successfully deleted.';
        }else{
            $resp['status']='failed';
            $resp['error']=$this->db ? $this->db->error : 'No DB';
        }
        return json_encode($resp);
    }
}

$a = isset($_GET['a']) ? $_GET['a'] : '';
$action = new Actions();
switch($a){
    case 'login':
        echo $action->login();
    break;
    case 'customer_login':
        echo $action->customer_login();
    break;
    case 'logout':
        echo $action->logout();
    break;
    case 'customer_logout':
        echo $action->customer_logout();
    break;
    case 'save_user':
        echo $action->save_user();
    break;
    case 'delete_user':
        echo $action->delete_user();
    break;
    case 'update_credentials':
        echo $action->update_credentials();
    break;
    case 'save_category':
        echo $action->save_category();
    break;
    case 'delete_category':
        echo $action->delete_category();
    break;
    case 'save_product':
        echo $action->save_product();
    break;
    case 'delete_product':
        echo $action->delete_product();
    break;
    case 'save_stock':
        echo $action->save_stock();
    break;
    case 'delete_stock':
        echo $action->delete_stock();
    break;
    case 'save_transaction':
        echo $action->save_transaction();
    break;
    case 'delete_transaction':
        echo $action->delete_transaction();
    break;
    default:
    break;
}
