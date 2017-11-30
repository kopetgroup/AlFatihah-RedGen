<?php
include 'config.php';

if(file_exists('./sitemap')){}else{
  mkdir('./sitemap');
}

if(PHP_SAPI=='cli'){
	
	if(isset($GLOBALS['argv'][1])){
	  
	  if($GLOBALS['argv'][1]=='-all'){
	  	posts($redis,$domain,$mlab);
	  	attachment($redis,$domain,$mlab);
	  	sitemap($redis,$domain,$mlab);
	  }elseif($GLOBALS['argv'][1]=='-sitemap'){
	  	sitemap($redis,$domain,$mlab);
	  }elseif($GLOBALS['argv'][1]=='-redis'){
	  	posts($redis,$domain,$mlab);
	  	attachment($redis,$domain,$mlab);
	  }else{
	  	posts($redis,$domain,$mlab);
	  	attachment($redis,$domain,$mlab);
	  	sitemap($redis,$domain,$mlab);
	  }
	  
	}else{
	  echo "\n";
	  echo "command:\n";
	  echo "-all rebuild all\n";
	  echo "-sitemap rebuild sitemap\n";
	  echo "-redis rebuild redis\n";
	  echo "\n";
	  exit;
	}
  $argv = $GLOBALS['argv'];
	
}else{
	echo 'cli mode only, sorry';
}
exit;

function posts($redis,$domain,$mlab){
	
	/*
	  Posts
	*/
	echo 'delete link_posts: '.$redis->del('link_posts')."\n";
	echo 'delete posts: '.$redis->del('posts')."\n";
	echo "\n";
	$data = file_get_contents('https://api.mlab.com/api/1/databases/adadisini/collections/posts?apiKey='.$mlab.'&l=10000000&f={%22permalink%22:%201}');
	$data = json_decode($data);
	$es = [];
	foreach($data as $d){
	  $w = 'http://'.$domain.'/'.$d->permalink;
	  //echo 'generate link: '.."\n";
	  $redis->lpush('link_posts',$w);
	}
	echo 'link post inserted'."\n";
	
	$data = file_get_contents('https://api.mlab.com/api/1/databases/adadisini/collections/posts?apiKey='.$mlab.'&l=10000000');
	$data = json_decode($data);
	$es = [];
	foreach($data as $d){
	  //echo 'insert post: '.."\n";
	  $redis->lpush('posts',json_encode($d));
	}
	echo 'post inserted'."\n";
	echo "================\n";
	echo "Result:\n";
	echo "link posts: ".$redis->llen('link_posts')."\n";
	echo "posts: ".$redis->llen('posts')."\n";
	echo "done\n";
	echo "================\n";

}
function attachment($redis,$domain,$mlab){
	
	/*
	  Attachment
	*/
	echo 'delete link_attachment: '.$redis->del('link_attachment')."\n";
	echo 'delete attachment: '.$redis->del('attachment')."\n";
	echo "\n";
	$data = file_get_contents('https://api.mlab.com/api/1/databases/adadisini/collections/attachment?apiKey='.$mlab.'&l=10000000&f={%22permalink%22:%201,%22path%22:1,%22title%22:1,%22txt%22:1}');
	$data = json_decode($data);
	$es = [];
	foreach($data as $d){
	   $w = [
	  	'permalink' => 'http://'.$domain.'/'.$d->permalink,
	  	'title' => $d->title,
	  	'caption' => $d->txt,
	  	'path' => 'http://'.$domain.'/'.$d->path
		];
	 	$redis->lpush('link_attachment',json_encode($w))."\n";
	}
	echo 'link attachment inserted'."\n";
	
	$data = file_get_contents('https://api.mlab.com/api/1/databases/adadisini/collections/attachment?apiKey='.$mlab.'&l=10000000');
	$data = json_decode($data);
	$es = [];
	foreach($data as $d){
	  //echo 'insert post: '.."\n";
	  $redis->lpush('attachment',json_encode($d));
	}
	echo 'attachment inserted'."\n";
	echo "================\n";
	echo "Result:\n";
	echo "link attachment: ".$redis->llen('link_attachment')."\n";
	echo "attachment: ".$redis->llen('attachment')."\n";
	echo "done\n";
	echo "================\n";
	
}
function sitemap($redis,$domain,$mlab){
	
	echo "generating sitemap:\n";
	
	/*
	  Sitemap Gallery:
	*/
	$tot = $redis->llen('link_attachment');
	echo "total attachment: ".number_format($tot)."\n";
	$tcp = ceil($tot/1000);
	for($i=0;$i<$tcp;$i++){
		
		$s = $i*1000;
		$l = ($s+1000)-1;
		
		$kwt = $redis->lrange('link_attachment',$s,$l);
		$smp = '';
		foreach($kwt as $k){
			$s = json_decode($k);
			if(strpos($s->path,'%')!==false){}else{
				$smp .= '<url>'.
				  '<loc>'.$s->permalink.'</loc>'.
				  '<image:image>'.
				    '<image:loc>'.$s->path.'</image:loc>'.
				    '<image:caption>'.$s->caption.'</image:caption>'.
				    '<image:title>'.$s->title.'</image:title>'.
				  '</image:image>'.
				'</url>';
			}
		}
		$stmp = '<?xml version="1.0" encoding="UTF-8"?>'.
		'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'
		  .$smp.
		'</urlset>';
		echo 'generating sitemap attachment '.($i+1).' --> '.file_put_contents('./sitemap/attachment-'.($i+1).'.xml',$stmp)." line.\n";
	}
	
	/*
	  Sitemap Posts:
	*/
	$tot = $redis->llen('link_posts');
	echo "total posts: ".number_format($tot)."\n";
	$tcp = ceil($tot/5000);
	for($i=0;$i<$tcp;$i++){
		
		$s = $i*5000;
		$l = ($s+5000)-1;
		
		$kwt = $redis->lrange('link_posts',$s,$l);
		$smp = '';
		foreach($kwt as $k){
			$smp .= '<url>'.
			  '<loc>'.$k.'</loc>'.
			'</url>';
		}
		$stmp = '<?xml version="1.0" encoding="UTF-8"?>'.
	  '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
	    $smp.
	  '</urlset>';
		echo 'generating sitemap posts '.($i+1).' --> '.file_put_contents('./sitemap/posts-'.($i+1).'.xml',$stmp)." line.\n";
	}
	
	

}



