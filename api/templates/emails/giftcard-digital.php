<?php
/** @var array $order    */
/** @var array $giftCard */
/** @var array $items    */
/** @var string $qrCid   CID do QR Code embutido */
$appUrl      = env('APP_URL', 'https://hochheim.com.br');
$validateUrl = $appUrl . '/validar/' . $giftCard['code'];
$validUntil  = date('d/m/Y', strtotime($giftCard['valid_until']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seu Cartão Presente — Day Spa Hochheim</title>
<style>
  body { margin:0; padding:0; background:#f5f0eb; font-family:'Helvetica Neue',Arial,sans-serif; color:#3d2c2c; }
  .wrapper { max-width:600px; margin:0 auto; background:#fff; }
  .header { background:linear-gradient(135deg,#3d2c2c 0%,#5a3e3e 100%); padding:40px; text-align:center; }
  .header h1 { color:#c9a96e; margin:0 0 4px; font-size:24px; font-weight:300; letter-spacing:4px; text-transform:uppercase; }
  .header p  { color:#ffffff60; margin:0; font-size:12px; letter-spacing:2px; }
  .gift-card { margin:0 24px; background:linear-gradient(135deg,#3d2c2c,#5a3e3e); border-radius:12px; padding:28px; text-align:center; margin-top:-20px; position:relative; box-shadow:0 8px 32px rgba(0,0,0,.2); }
  .gift-card .code { font-size:28px; font-weight:700; color:#c9a96e; letter-spacing:6px; font-family:monospace; margin:0 0 8px; }
  .gift-card .validity { font-size:12px; color:#ffffff70; margin:0; letter-spacing:1px; }
  .body { padding:32px 40px; }
  .body h2 { font-size:18px; font-weight:400; margin:0 0 8px; }
  .body p  { font-size:14px; line-height:1.7; color:#5a4a4a; margin:0 0 16px; }
  .services { background:#f9f6f2; border-radius:8px; padding:20px; margin:20px 0; }
  .services h3 { font-size:11px; text-transform:uppercase; letter-spacing:2px; color:#c9a96e; margin:0 0 12px; }
  .service-item { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e8ddd0; font-size:13px; }
  .service-item:last-child { border-bottom:none; }
  .service-name { color:#3d2c2c; }
  .service-meta { color:#9e8070; font-size:12px; }
  .qr-section { text-align:center; padding:24px 0; }
  .qr-section img { width:160px; height:160px; border:4px solid #f5f0eb; border-radius:8px; }
  .qr-section p { font-size:12px; color:#9e8070; margin:8px 0 0; }
  .cta { display:block; background:#c9a96e; color:#fff !important; text-decoration:none; text-align:center; padding:14px 28px; border-radius:6px; font-size:14px; font-weight:600; letter-spacing:1px; margin:16px 0; }
  .footer { background:#f5f0eb; padding:24px 40px; text-align:center; }
  .footer p { font-size:12px; color:#9e8070; margin:4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Day Spa Hochheim</h1>
    <p>Seu presente de bem-estar chegou!</p>
  </div>

  <div class="gift-card">
    <p class="code"><?= htmlspecialchars($giftCard['code'], ENT_QUOTES, 'UTF-8') ?></p>
    <p class="validity">Válido até <?= $validUntil ?></p>
  </div>

  <div class="body">
    <h2>Olá, <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p>Seu cartão presente do <strong>Day Spa Hochheim</strong> está pronto. Apresente o código ou o QR Code ao chegar ao spa.</p>

    <div class="services">
      <h3>Serviços incluídos</h3>
      <?php foreach ($items as $item): ?>
        <div class="service-item">
          <div>
            <div class="service-name"><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="service-meta"><?= $item['service_category'] ?> &bull; <?= $item['service_duration'] ?> min</div>
          </div>
          <div style="font-weight:600;color:#c9a96e;">x<?= $item['quantity'] ?></div>
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

    <p style="font-size:12px;color:#9e8070;text-align:center;">O PDF do cartão está anexo a este e-mail. Válido por 30 dias a partir da compra.</p>
  </div>

  <div class="footer">
    <p>Day Spa Hochheim — Rua Itaiópolis, 102, Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
