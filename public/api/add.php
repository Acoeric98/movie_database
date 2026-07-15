<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); $user=require_login(true); verify_csrf(); $d=request_json();
$type=(string)($d['media_type']??'movie'); if(!in_array($type,['movie','tv'],true))$type='movie';
$ids=[];
if(isset($d['tmdb_ids']) && is_array($d['tmdb_ids'])) $ids=array_map('intval',$d['tmdb_ids']);
else $ids=[(int)($d['tmdb_id']??0)];
$ids=array_values(array_filter(array_unique($ids), static fn(int $id): bool => $id>0));
if(!$ids)json_response(['error'=>'Érvénytelen cím.'],422);
$added=0; $duplicates=0;
try{
    $stmt=db()->prepare('INSERT INTO movies(group_id,tmdb_id,media_type,title,original_title,release_date,poster_path,overview,status,added_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
    foreach($ids as $id){
        $endpoint=$type.'/'.$id;
        $details=tmdb_request($endpoint,['language'=>'hu-HU']);
        try{
            $stmt->execute([(int)$user['group_id'],$id,$type,$details['title']??$details['name']??$details['original_title']??$details['original_name']??'Ismeretlen',$details['original_title']??$details['original_name']??'',$details['release_date']??$details['first_air_date']??'',$details['poster_path']??null,$details['overview']??'','watchlist',(int)$user['id']]);
            $added++;
        }catch(PDOException $e){
            if(str_contains($e->getMessage(),'UNIQUE')){$duplicates++; continue;}
            throw $e;
        }
    }
    if($added===0 && $duplicates>0)json_response(['error'=>'A kiválasztott cím(ek) már szerepelnek ezen a listán.'],409);
    json_response(['ok'=>true,'added'=>$added,'duplicates'=>$duplicates],201);
}catch(PDOException $e){json_response(['error'=>'Adatbázishiba.'],500);}catch(Throwable $e){json_response(['error'=>$e->getMessage()],502);}
