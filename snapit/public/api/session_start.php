<?php // POST: booking_id, camera_model_id, layout_id (staff-logged-in booth)
require_once __DIR__.'/../../includes/auth.php'; require_staff();
$bid=(int)($_POST['booking_id']??0);
$b=db()->prepare('SELECT status FROM bookings WHERE id=?'); $b->execute([$bid]);
if(!in_array($b->fetchColumn(),['confirmed','paid'],true)) json_out(['error'=>'Booking must be confirmed first'],403);
db()->prepare('INSERT INTO sessions(booking_id,camera_model_id,layout_id) VALUES(?,?,?)')->execute([$bid,(int)$_POST['camera_model_id'],(int)$_POST['layout_id']]);
json_out(['ok'=>true,'session_id'=>(int)db()->lastInsertId()]);
