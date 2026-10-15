<?php // POST: package_id, event_date, start_time, duration_hours
require_once __DIR__.'/../../includes/auth.php'; require_login();
$pkg=(int)($_POST['package_id']??0); $date=$_POST['event_date']??''; $time=$_POST['start_time']??''; $dur=(int)($_POST['duration_hours']??0);
$d=DateTime::createFromFormat('Y-m-d',$date); $t=DateTime::createFromFormat('H:i',$time);
if(!$d||$d->format('Y-m-d')!==$date||!$t) json_out(['error'=>'Invalid date or time'],422);
if($date<date('Y-m-d')) json_out(['error'=>'Event date cannot be in the past'],422);
if($dur<6||$dur>12) json_out(['error'=>'Duration must be 6 to 12 hours'],422);
$startMin=$t->format('G')*60+$t->format('i'); $endMin=$startMin+$dur*60;
[$oh,$om]=explode(':',BUSINESS_OPEN); [$ch,$cm]=explode(':',BUSINESS_CLOSE);
if($startMin<$oh*60+$om||$endMin>$ch*60+$cm+1) json_out(['error'=>'Outside business hours'],422);
$pdo=db(); $pdo->beginTransaction();
try{
  $p=$pdo->prepare('SELECT * FROM packages WHERE id=?'); $p->execute([$pkg]); $pk=$p->fetch();
  if(!$pk){ $pdo->rollBack(); json_out(['error'=>'Unknown package'],422); }
  $o=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE event_date=? AND status<>'cancelled'
    AND TIME_TO_SEC(start_time)/60 < ? AND TIME_TO_SEC(start_time)/60 + duration_hours*60 > ? FOR UPDATE");
  $o->execute([$date,$endMin,$startMin]);
  if($o->fetchColumn()>0){ $pdo->rollBack(); json_out(['error'=>'That time overlaps another booking'],409); }
  $pdo->prepare('INSERT INTO bookings(customer_id,package_id,event_date,start_time,duration_hours) VALUES(?,?,?,?,?)')
      ->execute([$_SESSION['uid'],$pkg,$date,$time,$dur]); $bid=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO booking_packages(booking_id,package_id,soft_copy_limit_snapshot,hard_copy_limit_snapshot,has_softcopy_addon_snapshot,price_snapshot) VALUES(?,?,?,?,?,?)')
      ->execute([$bid,$pkg,$pk['soft_copy_limit'],$pk['hard_copy_limit'],$pk['has_softcopy_addon'],$pk['base_price']]);
  $pdo->commit();
}catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); json_out(['error'=>'Booking failed'],500); }
// TODO: PHPMailer confirmation email + notifications_log row
json_out(['ok'=>true,'booking_id'=>$bid]);
