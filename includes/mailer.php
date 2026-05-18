<?php
class Mailer {
    private static function cfg(): array {
        return ['host'=>DB::setting('smtp_host',''),'port'=>(int)DB::setting('smtp_port',587),'user'=>DB::setting('smtp_user',''),'pass'=>DB::setting('smtp_pass',''),'from_name'=>DB::setting('smtp_from_name','Billing Portal'),'from_email'=>DB::setting('smtp_from_email',''),'enc'=>DB::setting('smtp_encryption','tls')];
    }
    public static function send(string $to, string $name, string $subject, string $html): bool {
        $c=self::cfg(); if (empty($c['host'])||empty($c['from_email'])) { error_log("Mailer: SMTP not configured"); return false; }
        try {
            $boundary=md5(uniqid(rand(),true));
            $from=$c['from_name']?'"'.$c['from_name'].'" <'.$c['from_email'].'>'  :$c['from_email'];
            $plain=strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html));
            $headers="From: {$from}\r\nTo: ".($name?"\"$name\" <$to>":$to)."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $body="--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n--{$boundary}--";
            return self::smtpSend($c,$to,$subject,$headers,$body);
        } catch(Exception $e) { error_log("Mailer: ".$e->getMessage()); return false; }
    }
    public static function sendTemplate(string $to, string $name, string $slug, array $vars=[]): bool {
        $t=DB::row("SELECT * FROM email_templates WHERE slug=? AND status='active'",'s',[$slug]);
        if (!$t) { error_log("Mailer: template '{$slug}' not found"); return false; }
        $vars['company_name']=DB::setting('company_name','Billing Portal');
        $vars['site_url']=BASE_URL;
        $subj=self::fill($t['subject'],$vars);
        $body=self::wrap(self::fill($t['body_html'],$vars),$vars['company_name']);
        return self::send($to,$name,$subj,$body);
    }
    private static function fill(string $t, array $v): string {
        foreach ($v as $k=>$val) $t=str_replace('{'.$k.'}',(string)$val,$t);
        return $t;
    }
    private static function wrap(string $content, string $co): string {
        $y=date('Y');
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{margin:0;padding:0;background:#f4f6f9;font-family:-apple-system,sans-serif}.w{max-width:600px;margin:0 auto;padding:32px 16px}.c{background:#fff;border-radius:16px;overflow:hidden}.h{background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:28px 32px;text-align:center}.h h1{color:#fff;margin:0;font-size:22px;font-weight:700}.b{padding:32px}.b p{color:#374151;font-size:15px;line-height:1.7;margin:0 0 16px}.f{padding:20px 32px;text-align:center;background:#f8fafc;border-top:1px solid #f1f5f9}.f p{color:#9ca3af;font-size:12px;margin:0}.btn{display:inline-block;background:#0f172a;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;margin:8px 0}</style></head><body><div class='w'><div class='c'><div class='h'><h1>{$co}</h1></div><div class='b'>{$content}</div><div class='f'><p>&copy; {$y} {$co}. All rights reserved.</p></div></div></div></body></html>";
    }
    private static function smtpSend(array $c, string $to, string $subj, string $headers, string $body): bool {
        $host=($c['enc']==='ssl')?'ssl://'.$c['host']:$c['host'];
        $smtp=@stream_socket_client("{$host}:{$c['port']}",$errno,$errstr,10);
        if (!$smtp) { error_log("SMTP connect: {$errstr}"); return false; }
        stream_set_timeout($smtp,10);
        $r=fn()=>fgets($smtp,515); $w=fn($s)=>fwrite($smtp,$s."\r\n");
        $r(); $w("EHLO ".($_SERVER['HTTP_HOST']??'localhost'));
        while ($l=$r()) { if ($l[3]===' ') break; }
        if ($c['enc']==='tls') {
            $w("STARTTLS"); $r();
            if (!stream_socket_enable_crypto($smtp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) { error_log("STARTTLS fail"); return false; }
            $w("EHLO ".($_SERVER['HTTP_HOST']??'localhost')); while ($l=$r()) { if ($l[3]===' ') break; }
        }
        $w("AUTH LOGIN"); $r(); $w(base64_encode($c['user'])); $r(); $w(base64_encode($c['pass']));
        $resp=$r(); if (strpos($resp,'235')===false) { error_log("SMTP auth: {$resp}"); fclose($smtp); return false; }
        $w("MAIL FROM:<{$c['from_email']}>"); $r(); $w("RCPT TO:<{$to}>"); $r(); $w("DATA"); $r();
        $w("Date: ".date('r')."\r\nSubject: {$subj}\r\n{$headers}\r\n\r\n{$body}\r\n.");
        $resp=$r(); $w("QUIT"); fclose($smtp);
        return strpos($resp,'250')!==false;
    }
}
