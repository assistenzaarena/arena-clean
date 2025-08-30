<?php
// Base32 decode (per segreti compatibili con Google Authenticator)
function base32_decode_custom(string $b32): string {
  $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b32=strtoupper($b32);
  $buf=0; $bits=0; $out='';
  for($i=0;$i<strlen($b32);$i++){
    $v=strpos($alphabet,$b32[$i]); if($v===false) continue;
    $buf=($buf<<5)|$v; $bits+=5;
    if($bits>=8){ $bits-=8; $out.=chr(($buf>>$bits)&0xFF); }
  } return $out;
}
function hotp(string $key,int $ctr,int $digits=6): int {
  $bin=pack('N*',0).pack('N*',$ctr); $h=hash_hmac('sha1',$bin,$key,true);
  $o=ord($h[19])&0x0F;
  $code=((ord($h[$o])&0x7F)<<24)|((ord($h[$o+1])&0xFF)<<16)|((ord($h[$o+2])&0xFF)<<8)|((ord($h[$o+3])&0xFF));
  return $code % (10**$digits);
}
function totp_now(string $secret_b32,int $period=30,int $digits=6): int {
  return hotp(base32_decode_custom($secret_b32),(int)floor(time()/$period),$digits);
}
function totp_verify(string $secret_b32,string $userCode,int $period=30,int $digits=6,int $window=1): bool {
  $userCode=preg_replace('/\D+/','',$userCode); if($userCode==='') return false;
  $key=base32_decode_custom($secret_b32); $ctr=floor(time()/$period);
  for($i=-$window;$i<=$window;$i++) if(hotp($key,(int)($ctr+$i),$digits)===(int)$userCode) return true;
  return false;
}
function generate_base32_secret(int $len=16): string {
  $a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s=''; for($i=0;$i<$len;$i++) $s.=$a[random_int(0,strlen($a)-1)]; return $s;
}
