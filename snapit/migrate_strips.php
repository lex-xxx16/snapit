<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
require_admin();
header('Content-Type: text/plain');
function col($c,$t,$n){$r=mysqli_query($c,"SHOW COLUMNS FROM $t LIKE '$n'");return mysqli_num_rows($r)>0;}
$add=['layouts'=>['print_size'=>"VARCHAR(10) NULL"],'frame_designs'=>['bg_color'=>"VARCHAR(20) NOT NULL DEFAULT '#ffffff'",'text_color'=>"VARCHAR(20) NOT NULL DEFAULT '#6f42c1'",'photo_gap'=>"TINYINT NOT NULL DEFAULT 10",'photo_radius'=>"TINYINT NOT NULL DEFAULT 8",'pattern'=>"VARCHAR(20) NULL"]];
foreach($add as $t=>$cols)foreach($cols as $n=>$d){ if(!col($conn,$t,$n)){mysqli_query($conn,"ALTER TABLE $t ADD COLUMN $n $d");echo "added $t.$n\n";} }
$L=[["Strip · 3 Photos", 3, 1, 3, "2x6"], ["Strip · 4 Photos", 4, 1, 4, "2x6"], ["Duo Strip · 2 Photos", 2, 1, 2, "2x6"], ["Wide Strip · 4 Photos", 4, 2, 2, "4x6"], ["Big Strip · 6 Photos", 6, 2, 3, "4x6"]];$F=[["Classic White", "#111111", 1, "#ffffff", "#111111", 8, 0, NULL], ["Midnight Black", "#111111", 2, "#111111", "#ffffff", 8, 2, NULL], ["Blush Pink", "#f8a5c2", 4, "#fde4ec", "#c2185b", 8, 10, NULL], ["Vintage Cream", "#8b6b3d", 2, "#f5ecd7", "#5b4527", 6, 2, "dots"], ["Burgundy Rose", "#7a0c1e", 3, "#7a0c1e", "#f7d6dc", 8, 3, NULL], ["Holiday Noir", "#c0392b", 3, "#14201a", "#f1e4c3", 8, 4, "dots"], ["Ocean Breeze", "#4bb3d9", 4, "#e3f4fb", "#0b5d7a", 8, 12, NULL], ["Film Reel", "#0d0d0d", 2, "#0d0d0d", "#e8d9a8", 12, 0, NULL]];
foreach($L as $l){$s=mysqli_prepare($conn,"SELECT 1 FROM layouts WHERE name=?");mysqli_stmt_bind_param($s,'s',$l[0]);mysqli_stmt_execute($s);
 if(!mysqli_num_rows(mysqli_stmt_get_result($s))){$i=mysqli_prepare($conn,"INSERT INTO layouts (name,photo_count,grid_cols,grid_rows,print_size) VALUES (?,?,?,?,?)");mysqli_stmt_bind_param($i,'siiis',...$l);mysqli_stmt_execute($i);echo "layout: {$l[0]}\n";}}
foreach($F as $f){$s=mysqli_prepare($conn,"SELECT 1 FROM frame_designs WHERE name=?");mysqli_stmt_bind_param($s,'s',$f[0]);mysqli_stmt_execute($s);
 if(!mysqli_num_rows(mysqli_stmt_get_result($s))){$i=mysqli_prepare($conn,"INSERT INTO frame_designs (name,border_color,border_width,bg_color,text_color,photo_gap,photo_radius,pattern) VALUES (?,?,?,?,?,?,?,?)");mysqli_stmt_bind_param($i,'ssissiis',...$f);mysqli_stmt_execute($i);echo "frame: {$f[0]}\n";}}
echo "Done. You can delete migrate_strips.php now.\n";
