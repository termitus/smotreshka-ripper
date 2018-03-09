<?php

 $ua = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0";
 $cookfile = "cooks.txt";
 $eol="\r\n";

 if ($argc<4)
 {
  echo "smotreshka.php <email> <password> <playlist_file> [all]\n";
  die();
 }
 $sm_email=$argv[1];
 $sm_password=$argv[2];
 $playlist_file=$argv[3];
 $allq = ($argc>=5) && ($argv[4]=='all');

 function StructArraySearch(array &$a,$field,$value,$default_idx=FALSE)
 {
  foreach($a as $i => $v)
   if ($v->{$field}==$value)
    return $i;
  return $default_idx;
 }

 function CheckHttpCode($curl,$expected_code=200)
 {
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($httpcode!=$expected_code) throw new Exception("http code $httpcode in ".curl_getinfo($curl,CURLINFO_EFFECTIVE_URL));
 }

 $err=0;
 $curl = curl_init();
 $fplaylist = false;
 try
 {
  curl_setopt_array($curl, array(
        CURLOPT_COOKIEFILE => $cookfile,
	CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => $ua,
	CURLOPT_TIMEOUT_MS => 5000,
	CURLOPT_CONNECTTIMEOUT_MS => 5000,
        CURLOPT_SSL_VERIFYPEER=>false
  ));

  curl_setopt ($curl, CURLOPT_POSTFIELDS, "email=$sm_email&password=$sm_password");
  curl_setopt ($curl, CURLOPT_URL, "https://fe.smotreshka.tv/login");
  curl_exec($curl);
  CheckHttpCode($curl);

  $fplaylist=fopen($playlist_file, "w");
  if (!$fplaylist) throw new Exception("could not create $playlist_file");

  curl_setopt ($curl, CURLOPT_POST, false);
  curl_setopt ($curl, CURLOPT_URL, "https://fe.smotreshka.tv/channels");
  $resp = curl_exec($curl);
  CheckHttpCode($curl);
  $json = json_decode($resp);
  if (!isset($json)) throw new Exception("bad channels json");

  fwrite($fplaylist,"#EXTM3U$eol");
  foreach($json->channels as $ch)
  {
   $info = $ch->info;
   if ($info->purchaseInfo->bought)
   {
    $title = $info->metaInfo->title;
    curl_setopt ($curl, CURLOPT_URL, "https://fe.smotreshka.tv/playback-info/".$ch->id);
    $resp = curl_exec($curl);
    CheckHttpCode($curl);
    $json2 = json_decode($resp);
    if (!isset($json2)) throw new Exception("bad playback-info json");
    $lang = StructArraySearch($json2->languages,"id","ru-RU",0);
    if ($allq)
    {
     foreach ($json2->languages[$lang]->renditions as $r)
     {
      $url = $r->url;
      $id = $r->id;
      fwrite($fplaylist,"#EXTINF:-1,$title ($id)$eol$url$eol");
     }
    }
    else
    {
     $rend = StructArraySearch($json2->languages[$lang]->renditions,"id","Auto",0);
     $url = $json2->languages[$lang]->renditions[$rend]->url;
     fwrite($fplaylist,"#EXTINF:-1,$title$eol$url$eol");
    }
   }
  }
 }
 catch (Exception $e)
 {
  echo 'Exception: ',  $e->getMessage(), "\n";
  $err=1;
 }
 finally
 {
  curl_close($curl);
  if ($fplaylist) fclose($fplaylist);
 }
 if ($err) die($err);
?>
