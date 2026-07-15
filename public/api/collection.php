<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require_installation(); require_login(true);
$id=(int)($_GET['id']??0); if($id<1)json_response(['error'=>'Érvénytelen filmsorozat.'],422);
try{
    $data=tmdb_request('collection/'.$id,['language'=>'hu-HU']);
    $parts=$data['parts']??[];
    usort($parts, static fn(array $a,array $b): int => strcmp((string)($a['release_date']??''),(string)($b['release_date']??'')));
    $items=array_map(static fn(array $m): array => [
        'tmdb_id'=>(int)$m['id'],'media_type'=>'movie','title'=>(string)($m['title']??$m['original_title']??'Ismeretlen'),
        'original_title'=>(string)($m['original_title']??''),'release_date'=>(string)($m['release_date']??''),
        'poster_url'=>poster_url($m['poster_path']??null,'w185'),'vote_average'=>round((float)($m['vote_average']??0),1)
    ],$parts);
    json_response(['id'=>$id,'name'=>(string)($data['name']??'Filmsorozat'),'parts'=>$items]);
}catch(Throwable $e){json_response(['error'=>$e->getMessage()],502);}
