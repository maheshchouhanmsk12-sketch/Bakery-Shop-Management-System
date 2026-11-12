<?php
session_start();
if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0){
    header("Location: index.php");
    exit;
}
require_once('DBConnection.php');
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$db_connected = ($conn && $db->is_connected());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN | Bakery Shop Management System</title>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <script src="./js/jquery-3.6.0.min.js"></script>
    <script src="./js/popper.min.js"></script>
    <script src="./js/bootstrap.min.js"></script>
    <script src="./js/script.js"></script>
    <style>
        html, body{
            height:100%;
        }
        body{
            background-image:url('./images/wallpaper.jfif') !important;
            background-size:cover;
            background-repeat:no-repeat;
            background-position:center center;
        }
        h1#sys_title {
            font-size: 4rem;
            text-shadow: 3px 3px 10px #000000;
        }
        @media (max-width:700px){
            h1#sys_title {
                font-size: 2rem !important;
            }
        }
    </style>
</head>
<body class="">
   <div class="h-100 d-flex justify-content-center align-items-center">
       <div class='w-100'>
        <h1 class="py-4 text-center text-light px-4" id="sys_title">Bakery Shop Management System</h1>
        <div class="card my-3 col-11 col-sm-8 col-md-5 col-lg-4 mx-auto shadow-lg">
            <div class="card-body">
                <?php if(!$db_connected): ?>
                <div class="alert alert-warning py-2 small mb-3">
                    <strong>Notice:</strong> Database is not connected or tables are not imported yet. 
                    <a href="db_setup.php" class="alert-link fw-bold">Open DB Setup Tool</a>
                </div>
                <?php endif; ?>
                <form action="" id="login-form">
                    <center><small class="text-muted">Please enter your credentials.</small></center>
                    <div class="form-group mb-2">
                        <label for="username" class="control-label">Username</label>
                        <input type="text" id="username" autofocus name="username" class="form-control form-control-sm rounded-0" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="password" class="control-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control form-control-sm rounded-0" required>
                    </div>
                    <div class="form-group d-flex w-100 justify-content-between align-items-center">
                        <a href="db_setup.php" class="text-decoration-none small text-muted">DB Setup</a>
                        <button class="btn btn-sm btn-primary rounded-0 px-3">Login</button>
                    </div>
                </form>
            </div>
        </div>
       </div>
   </div>
</body>
<script>
    $(function(){
        $('#login-form').submit(function(e){
            e.preventDefault();
            $('.pop_msg').remove();
            var _this = $(this);
            var _el = $('<div>');
            _el.addClass('pop_msg');
            _this.find('button').attr('disabled',true);
            _this.find('button[type="submit"]').text('Logging in...');
            $.ajax({
                url:'./Actions.php?a=login',
                method:'POST',
                data:$(this).serialize(),
                dataType:'JSON',
                error:err=>{
                    console.log(err);
                    _el.addClass('alert alert-danger mb-2');
                    _el.text("An error occurred. Check database connection.");
                    _this.prepend(_el);
                    _el.show('slow');
                    _this.find('button').attr('disabled',false);
                    _this.find('button[type="submit"]').text('Login');
                },
                success:function(resp){
                    if(resp.status == 'success'){
                        _el.addClass('alert alert-success mb-2');
                        _el.text(resp.msg);
                        _this.prepend(_el);
                        _el.show('slow');
                        setTimeout(() => {
                            location.replace('index.php');
                        }, 1000);
                    }else{
                        _el.addClass('alert alert-danger mb-2');
                        _el.text(resp.msg);
                        _this.prepend(_el);
                        _el.show('slow');
                        _this.find('button').attr('disabled',false);
                        _this.find('button[type="submit"]').text('Login');
                    }
                }
            });
        });
    });
</script>
</html>
