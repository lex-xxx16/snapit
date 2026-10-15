<?php
require_once __DIR__.'/db.php';
if (session_status()===PHP_SESSION_NONE) session_start();
function json_out($d,int $c=200){ http_response_code($c); header('Content-Type: application/json'); echo json_encode($d); exit; }
function require_login(){ if(empty($_SESSION['uid'])) json_out(['error'=>'Login required'],401); }
function require_staff(){ require_login();
  $s=db()->prepare('SELECT role FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]);
  if(($s->fetchColumn())!=='staff') json_out(['error'=>'Staff only'],403); }
function login(string $email,string $pw): bool {
  $s=db()->prepare('SELECT id,password_hash FROM users WHERE email=?'); $s->execute([$email]); $u=$s->fetch();
  if($u && password_verify($pw,$u['password_hash'])){ session_regenerate_id(true); $_SESSION['uid']=$u['id']; return true; } return false; }
