<?php
/** @var array $order    */
/** @var array $giftCard */
/** @var array $items    */
/** @var string $qrCid   CID do QR Code embutido */
$appUrl      = env('APP_URL', 'https://hochheim.com.br');
$logoUrl     = $appUrl . '/logo.png';
$validateUrl = $appUrl . '/validar/' . $giftCard['code'];
$validUntil  = date('d/m/Y', strtotime($giftCard['valid_until']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seu Cartão Presente — Clínica Hochheim SPA</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;400;500;600;700&display=swap');
  body { margin:0; padding:0; background:#9F9D9D; font-family:'Poppins', Arial, sans-serif; color:#004567; }
  .wrapper { max-width:600px; margin:20px auto; background:#FFFFFF; border-radius:12px; overflow:hidden; }
  .header { background:#004567; padding:40px; text-align:center; }
  .header img { max-width:160px; margin-bottom:12px; }
  .header h1 { color:#FDFEFF; margin:0 0 4px; font-size:24px; font-weight:500; letter-spacing:2px; text-transform:uppercase; }
  .header p  { color:#92DEFD; margin:0; font-size:12px; letter-spacing:1px; font-weight:200; }
  .gift-card { margin:0 24px; background:#1B7C9F; border-radius:12px; padding:28px; text-align:center; margin-top:-20px; position:relative; box-shadow:0 8px 32px rgba(0,0,0,.15); }
  .gift-card .code { font-size:28px; font-weight:700; color:#FDFEFF; letter-spacing:6px; font-family:monospace; margin:0 0 8px; }
  .gift-card .validity { font-size:12px; color:#FDFEFF; opacity:0.8; margin:0; letter-spacing:1px; }
  .body { padding:32px 40px; }
  .body h2 { font-size:20px; font-weight:600; margin:0 0 12px; color:#004567; }
  .body p  { font-size:14px; line-height:1.7; color:#1B7C9F; margin:0 0 16px; }
  .services { background:#F8F8F8; border-radius:8px; padding:20px; margin:20px 0; border: 1px solid #E5F3F8; }
  .services h3 { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#1B7C9F; margin:0 0 12px; font-weight:600; }
  .service-item { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #E5F3F8; font-size:14px; }
  .service-item:last-child { border-bottom:none; }
  .service-name { color:#004567; font-weight:500; }
  .service-meta { color:#1B7C9F; font-size:12px; margin-top: 2px; }
  .qr-section { text-align:center; padding:24px 0; }
  .qr-section img { width:160px; height:160px; border:4px solid #FDFEFF; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.08); }
  .qr-section p { font-size:12px; color:#1B7C9F; margin:12px 0 0; font-weight:500; }
  .cta { display:block; background:#1B7C9F; color:#FDFEFF !important; text-decoration:none; text-align:center; padding:16px 28px; border-radius:30px; font-size:15px; font-weight:600; letter-spacing:1px; margin:20px 0; transition:all 0.3s; }
  .footer { background:#004567; padding:32px 40px; text-align:center; }
  .footer p { font-size:12px; color:#F8F8F8; margin:4px 0; font-weight:200; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Clínica Hochheim">
    <h1>Day Spa Hochheim</h1>
    <p>Seu presente de bem-estar chegou!</p>
  </div>

  <div class="gift-card">
    <p class="code"><?= htmlspecialchars($giftCard['code'], ENT_QUOTES, 'UTF-8') ?></p>
    <p class="validity">Válido até <?= $validUntil ?></p>
  </div>

  <div class="body">
    <h2>Olá, <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p>Seu cartão presente da <strong>Clínica Hochheim SPA</strong> está pronto. Apresente o código ou o QR Code ao chegar à clínica.</p>

    <div class="services">
      <h3>Serviços incluídos</h3>
      <?php foreach ($items as $item): ?>
        <div class="service-item">
          <div>
            <div class="service-name"><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="service-meta"><?= $item['service_category'] ?> &bull; <?= $item['service_duration'] ?> min</div>
          </div>
          <div style="font-weight:600;color:#004567;">x<?= $item['quantity'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($qrCid): ?>
    <div class="qr-section">
      <img src="cid:<?= htmlspecialchars($qrCid, ENT_QUOTES, 'UTF-8') ?>" alt="QR Code do Cartão">
      <p>Escaneie para validar seu cartão</p>
    </div>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($validateUrl, ENT_QUOTES, 'UTF-8') ?>" class="cta">Ver meu Cartão Online</a>

    <p style="font-size:12px;color:#1B7C9F;text-align:center;margin-top:24px;">O PDF do cartão está anexo a este e-mail. Válido por 30 dias a partir da compra.</p>
  </div>

  <div class="footer">
    <p>Clínica Hochheim SPA — Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
