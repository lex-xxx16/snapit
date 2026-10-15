<?php require_once __DIR__.'/../includes/auth.php';
if($_SERVER['REQUEST_METHOD']==='POST' && login($_POST['email']??'',$_POST['password']??'')){ header('Location: index.php'); exit; }
?><!doctype html><meta charset=utf-8><form method=post style="max-width:300px;margin:80px auto;font:15px system-ui"><h3>Staff sign in</h3>
<input name=email type=email placeholder=Email required style="width:100%;margin:4px 0"><input name=password type=password placeholder=Password required style="width:100%;margin:4px 0"><button>Sign in</button></form>
