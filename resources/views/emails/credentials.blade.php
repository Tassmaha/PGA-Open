<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#0D1117;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0D1117;padding:40px 20px;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#161B24;border-radius:12px;border:1px solid rgba(255,255,255,.07);">
  <tr><td style="padding:28px 32px 0;text-align:center;">
    <div style="font-size:18px;font-weight:700;color:#2ECC7A;">{{ $appName }}</div>
    <div style="font-size:11px;color:#5A6578;margin-top:4px;">{{ $country }}</div>
  </td></tr>
  <tr><td style="padding:24px 32px;">
    <h2 style="color:#E8EDF5;font-size:18px;margin:0 0 16px;">Vos identifiants de connexion</h2>
    <p style="color:#8A97AC;font-size:14px;line-height:1.6;margin:0 0 20px;">
      Bonjour <strong style="color:#E8EDF5;">{{ $nom }}</strong>,<br>
      Un compte a &eacute;t&eacute; cr&eacute;&eacute; pour vous sur la plateforme {{ $appName }}.
    </p>
    <div style="background:#1E2530;border-radius:8px;padding:18px 20px;margin:0 0 20px;">
      <div style="font-size:11px;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;">Email</div>
      <div style="font-size:14px;color:#E8EDF5;font-weight:600;margin:4px 0 12px;">{{ $email }}</div>
      <div style="font-size:11px;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;">Mot de passe temporaire</div>
      <div style="font-family:'Courier New',monospace;font-size:18px;color:#2ECC7A;font-weight:700;margin:4px 0 12px;letter-spacing:.1em;">{{ $password }}</div>
      <div style="font-size:11px;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;">R&ocirc;le</div>
      <div style="font-size:14px;color:#E8EDF5;margin:4px 0;">{{ $role }}</div>
    </div>
    <div style="text-align:center;margin:0 0 20px;">
      <a href="{{ $url }}" style="display:inline-block;padding:12px 28px;background:#2ECC7A;color:#0D1117;border-radius:8px;font-size:14px;font-weight:700;text-decoration:none;">Se connecter</a>
    </div>
    <div style="background:rgba(240,160,48,.1);border-radius:6px;padding:12px 16px;font-size:12px;color:#F0A030;">
      &#9888; Changez votre mot de passe d&egrave;s la premi&egrave;re connexion.
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px 20px;text-align:center;border-top:1px solid rgba(255,255,255,.05);">
    <div style="font-size:10px;color:#5A6578;">{{ $org }}</div>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
