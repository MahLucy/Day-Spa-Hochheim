<?php
/** @var array $order    */
/** @var array $giftCard */
/** @var array $items    */
/** @var string $qrCid   */
$appUrl     = env('APP_URL', 'https://hochheim.com.br');
$validUntil = date('d/m/Y', strtotime($giftCard['valid_until']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cartão Presente Impresso — Day Spa Hochheim</title>
<style>
  body { margin:0; padding:0; background:#f5f0eb; font-family:'Helvetica Neue',Arial,sans-serif; color:#3d2c2c; }
  .wrapper { max-width:600px; margin:0 auto; background:#fff; }
  .header { background:#3d2c2c; padding:40px; text-align:center; }
  .header h1 { color:#c9a96e; margin:0 0 4px; font-size:22px; font-weight:300; letter-spacing:4px; text-transform:uppercase; }
  .header p  { color:#ffffff60; margin:0; font-size:12px; letter-spacing:2px; }
  .body { padding:40px; }
  .body h2 { font-size:18px; font-weight:400; margin:0 0 8px; }
  .body p  { font-size:14px; line-height:1.7; color:#5a4a4a; margin:0 0 16px; }
  .info-box { background:#fff8f0; border-left:3px solid #c9a96e; padding:16px 20px; margin:20px 0; border-radius:0 8px 8px 0; }
  .info-box p { margin:4px 0; font-size:13px; }
  .step { display:flex; gap:12px; margin:12px 0; align-items:flex-start; }
  .step-num { background:#c9a96e; color:#fff; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; line-height:24px; text-align:center; }
  .step-text { font-size:13px; color:#5a4a4a; padding-top:3px; line-height:1.5; }
  .services { background:#f9f6f2; border-radius:8px; padding:20px; margin:20px 0; }
  .services h3 { font-size:11px; text-transform:uppercase; letter-spacing:2px; color:#c9a96e; margin:0 0 12px; }
  .service-item { padding:8px 0; border-bottom:1px solid #e8ddd0; font-size:13px; }
  .service-item:last-child { border-bottom:none; }
  .footer { background:#f5f0eb; padding:24px 40px; text-align:center; }
  .footer p { font-size:12px; color:#9e8070; margin:4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Day Spa Hochheim</h1>
    <p>Confirmação — Cartão Presente Impresso</p>
  </div>

  <div class="body">
    <h2>Olá, <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p>Seu pedido de <strong>cartão presente impresso</strong> foi confirmado. Veja as informações abaixo e os próximos passos para retirada.</p>

    <div class="info-box">
      <p><strong>Pedido:</strong> #<?= $order['id'] ?></p>
      <p><strong>Código do cartão:</strong> <span style="font-family:monospace;font-size:16px;color:#c9a96e;font-weight:700;"><?= htmlspecialchars($giftCard['code'], ENT_QUOTES, 'UTF-8') ?></span></p>
      <p><strong>Válido até:</strong> <?= $validUntil ?></p>
      <p><strong>Formato:</strong> Impresso (retirada presencial)</p>
    </div>

    <div class="services">
      <h3>Serviços incluídos</h3>
      <?php foreach ($items as $item): ?>
        <div class="service-item">
          <strong><?= htmlspecialchars($item['service_name'], ENT_QUOTES, 'UTF-8') ?></strong>
          — <?= $item['service_category'] ?>, <?= $item['service_duration'] ?> min (x<?= $item['quantity'] ?>)
        </div>
      <?php endforeach; ?>
    </div>

    <h3 style="font-size:14px;font-weight:600;margin:24px 0 12px;">Próximos passos</h3>
    <div class="step">
      <div class="step-num">1</div>
      <div class="step-text">Entre em contato pelo <strong>(47) 3037-1707</strong> ou WhatsApp para agendar a retirada do cartão impresso.</div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div class="step-text">Passe no spa para retirar o envelope com o cartão físico. Leve um documento de identificação.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div class="step-text">Entregue o cartão para quem você quiser presentear. O recebedor poderá agendar qualquer serviço dentro da validade.</div>
    </div>
  </div>

  <div class="footer">
    <p>Day Spa Hochheim — Rua Itaiópolis, 102, Blumenau – SC</p>
    <p>(47) 3037-1707 &bull; Seg–Sex: 13h às 21h</p>
  </div>
</div>
</body>
</html>
