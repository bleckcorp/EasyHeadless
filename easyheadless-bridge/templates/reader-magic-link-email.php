<?php
if (!defined('ABSPATH')) { exit; }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo $brand; ?></title></head>
<body style="margin:0;padding:0;background:#f4f2ed;color:#171717;">
<div style="display:none;max-height:0;overflow:hidden;">Your secure sign-in link. Continue reading with <?php echo $brand; ?>.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f2ed;"><tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff;">
<tr><td align="center" style="padding:30px 24px;background:#050505;border-bottom:4px solid #ff8b1a;"><?php echo $logo; ?></td></tr>
<tr><td style="padding:40px 32px 12px;font-family:Arial,sans-serif;">
<p style="margin:0 0 18px;color:#a84c00;font-size:12px;font-weight:bold;letter-spacing:1px;">READER ACCESS</p>
<h1 style="margin:0 0 24px;font:normal 36px/1.15 Georgia,serif;"><?php echo $heading; ?></h1>
<div style="font-size:17px;line-height:1.6;"><?php echo $message; ?></div>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0;"><tr><td bgcolor="#ff8b1a" style="padding:16px 28px;"><a href="<?php echo $url; ?>" style="display:inline-block;color:#050505;font: bold 16px Arial,sans-serif;text-decoration:none;"><?php echo $button; ?></a></td></tr></table>
<p style="font-size:14px;line-height:1.6;color:#575757;">This link expires in <?php echo (int) self::CODE_MINUTES; ?> minutes and works once. On the next screen, confirm your sign-in to continue reading.</p>
<p style="font-size:14px;line-height:1.6;color:#575757;">If you didn’t request this email, you can ignore it. Signing in does not subscribe you to our newsletter.</p>
</td></tr>
<tr><td style="padding:20px 32px 32px;font:12px/1.6 Arial,sans-serif;color:#686868;border-top:1px solid #e7e4de;">
<p style="margin:0 0 10px;">Button not working? Copy this entire link into your browser:</p>
<a href="<?php echo $url; ?>" style="color:#a84c00;word-break:break-all;overflow-wrap:anywhere;"><?php echo esc_html($link); ?></a>
</td></tr>
<tr><td align="center" style="padding:24px;background:#050505;color:#ffffff;font:13px/1.5 Arial,sans-serif;"><?php echo $footer; ?></td></tr>
</table></td></tr></table></body></html>
