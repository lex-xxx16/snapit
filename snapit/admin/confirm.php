<?php // POST: booking_id, action=confirm|reject (staff only)
require_once __DIR__.'/../includes/auth.php'; require_staff();
$new=($_POST['action']??'')==='confirm'?'confirmed':'cancelled';
$s=db()->prepare("UPDATE bookings SET status=? WHERE id=? AND status='pending'"); $s->execute([$new,(int)($_POST['booking_id']??0)]);
json_out(['ok'=>$s->rowCount()===1,'status'=>$new]);
